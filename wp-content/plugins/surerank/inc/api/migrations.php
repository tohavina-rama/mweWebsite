<?php
/**
 * Migration API Class
 *
 * Handles migration-related REST API endpoints for the plugin.
 *
 * @package SureRank\Inc\API
 * @since   1.1.0
 */

namespace SureRank\Inc\API;

use SureRank\Inc\Functions\Send_Json;
use SureRank\Inc\Importers\ImporterInterface;
use SureRank\Inc\Importers\ImporterUtils;
use SureRank\Inc\Importers\RankMath;
use SureRank\Inc\Importers\Yoast;
use SureRank\Inc\Traits\Get_Instance;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrations
 *
 * Handles the REST API endpoints for migrations from other SEO plugins
 * and for retrieving all post IDs.
 */
class Migrations extends Api_Base {

	use Get_Instance;

	/**
	 * API endpoint for migrating posts.
	 */
	private const ENDPOINT_POSTS = '/migrate/posts';

	/**
	 * API endpoint for migrating terms.
	 */
	private const ENDPOINT_TERMS = '/migrate/terms';

	/**
	 * API endpoint for migrating global settings.
	 */
	private const ENDPOINT_GLOBAL = '/migrate/global-settings';

	/**
	 * API endpoint for deactivating a plugin.
	 *
	 * @since 1.1.0
	 */
	private const ENDPOINT_DEACTIVATE = '/migrate/deactivate-plugin';

	/**
	 * Batch size for processing large datasets.
	 */
	private const BATCH_SIZE = 100;

	/**
	 * Map of slug ⇒ importer class.
	 *
	 * @var array<string, class-string>
	 */
	private array $importers = [
		'rankmath' => RankMath::class,
		'yoast'    => Yoast::class,
	];

	/**
	 * Register the /migrate/posts, /migrate/terms, /migrate/global-settings routes.
	 */
	public function register_routes(): void {
		$namespace = $this->get_api_namespace();

		// -------- Migrate posts -------- .
		register_rest_route(
			$namespace,
			self::ENDPOINT_POSTS,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'migrate_posts' ],
					'permission_callback' => [ $this, 'validate_permission' ],
					'args'                => [
						'plugin_slug' => [
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'Plugin slug to migrate from (e.g. rankmath).', 'surerank' ),
							'enum'        => array_keys( $this->importers ),
						],
						'post_ids'    => [
							'type'              => [ 'array', 'integer' ],
							'required'          => true,
							'description'       => __( 'Post IDs to migrate.', 'surerank' ),
							'validate_callback' => fn( $param) => $this->validate_ids( $param ),
						],
						'cleanup'     => [
							'type'        => 'boolean',
							'required'    => false,
							'default'     => false,
							'description' => __( 'Whether to clean up source data after import.', 'surerank' ),
						],
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_all_posts' ],
					'permission_callback' => [ $this, 'validate_permission' ],
					'args'                => [
						'page'        => [
							'type'        => 'integer',
							'required'    => false,
							'default'     => 1,
							'description' => __( 'Page number for pagination.', 'surerank' ),
						],
						'plugin_slug' => [
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'Plugin slug to filter posts by (e.g. rankmath).', 'surerank' ),
							'enum'        => array_keys( $this->importers ),
							'default'     => 'rankmath',
						],
					],
				],
			]
		);

		// -------- Migrate terms --------.
		register_rest_route(
			$namespace,
			self::ENDPOINT_TERMS,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'migrate_terms' ],
					'permission_callback' => [ $this, 'validate_permission' ],
					'args'                => [
						'plugin_slug' => [
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'Plugin slug to migrate from (e.g. rankmath).', 'surerank' ),
							'enum'        => array_keys( $this->importers ),
						],
						'term_ids'    => [
							'type'              => [ 'array', 'integer' ],
							'required'          => true,
							'description'       => __( 'Term IDs to migrate.', 'surerank' ),
							'validate_callback' => fn( $param) => $this->validate_ids( $param ),
						],
						'cleanup'     => [
							'type'        => 'boolean',
							'required'    => false,
							'default'     => false,
							'description' => __( 'Whether to clean up source data after import.', 'surerank' ),
						],
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_all_terms' ],
					'permission_callback' => [ $this, 'validate_permission' ],
					'args'                => [
						'page'        => [
							'type'        => 'integer',
							'required'    => false,
							'default'     => 1,
							'description' => __( 'Page number for pagination.', 'surerank' ),
						],
						'plugin_slug' => [
							'type'        => 'string',
							'required'    => true,
							'default'     => 'rankmath',
							'description' => __( 'Plugin slug to filter terms by (e.g. rankmath).', 'surerank' ),
							'enum'        => array_keys( $this->importers ),
						],
					],
				],
			]
		);

		// -------- Migrate global settings --------.
		register_rest_route(
			$namespace,
			self::ENDPOINT_GLOBAL,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'migrate_global_settings' ],
				'permission_callback' => [ $this, 'validate_permission' ],
				'args'                => [
					'plugin_slug' => [
						'type'        => 'string',
						'required'    => true,
						'description' => __( 'Plugin slug to migrate global settings from (e.g. rankmath, yoast).', 'surerank' ),
						'enum'        => array_keys( $this->importers ),
					],
					'cleanup'     => [
						'type'        => 'boolean',
						'required'    => false,
						'default'     => false,
						'description' => __( 'Whether to clean up source global data after import.', 'surerank' ),
					],
				],
			]
		);

		// -------- Deactivate plugin --------.
		register_rest_route(
			$namespace,
			self::ENDPOINT_DEACTIVATE,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'deactivate_plugin' ],
				'permission_callback' => [ $this, 'validate_permission' ],
				'args'                => [
					'plugin_slug' => [
						'type'        => 'string',
						'required'    => true,
						'description' => __( 'Plugin slug to deactivate.', 'surerank' ),
						'enum'        => array_keys( $this->importers ),
					],
				],
			]
		);
	}

	/**
	 * Handle the migration request for posts.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 * @return void
	 */
	public function migrate_posts( $request ) {
		$ids_raw = $request->get_param( 'post_ids' );
		$cleanup = (bool) $request->get_param( 'cleanup' );
		$ids     = is_array( $ids_raw ) ? $ids_raw : [ $ids_raw ];

		$importer = $this->validate_and_get_importer( $request );

		if ( ! $this->validate_importer_methods( $importer, 'post' ) ) {
			Send_Json::error(
				[ 'message' => __( 'Invalid importer methods.', 'surerank' ) ]
			);
		}

		$results = $this->process_migration( $ids, $importer, 'post' );
		$results = array_merge( $results, $this->handle_cleanup( $importer, $cleanup, $results['success'] ) );
		$results = $this->format_response( $results, $importer, $ids, 'posts' );

		Send_Json::success( $results );
	}

	/**
	 * Handle the migration request for terms.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 * @return void
	 */
	public function migrate_terms( $request ) {
		$ids_raw = $request->get_param( 'term_ids' );
		$cleanup = (bool) $request->get_param( 'cleanup' );
		$ids     = is_array( $ids_raw ) ? $ids_raw : [ $ids_raw ];

		$importer = $this->validate_and_get_importer( $request );

		if ( ! $this->validate_importer_methods( $importer, 'term' ) ) {
			Send_Json::error(
				[ 'message' => __( 'Invalid importer methods.', 'surerank' ) ]
			);
		}

		$results = $this->process_migration( $ids, $importer, 'term' );
		$results = array_merge( $results, $this->handle_cleanup( $importer, $cleanup, $results['success'] ) );
		$results = $this->format_response( $results, $importer, $ids, 'terms' );

		Send_Json::success( $results );
	}

	/**
	 * Handle the migration request for global settings.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 */
	public function migrate_global_settings( $request ): void {
		$plugin_slug = $request->get_param( 'plugin_slug' );
		$cleanup     = (bool) $request->get_param( 'cleanup' );

		$importer_class = $this->importers[ $plugin_slug ];

		/**
		 * The importer class must implement ImporterInterface.
		 *
		 * @var ImporterInterface $importer
		 * */
		$importer = new $importer_class();

		// Validate that the importer implements the required interface.
		if ( ! $importer instanceof ImporterInterface ) {
			Send_Json::error(
				[
					'message' => sprintf(
						/* translators: %s: importer class name */
						__( 'Invalid importer class: %s does not implement ImporterInterface.', 'surerank' ),
						$importer_class
					),
				]
			);
		}

		$results = $importer->import_global_settings();

		// Ensure results is an array.
		if ( ! is_array( $results ) ) {
			$results = [
				'success' => false,
				'message' => __( 'Invalid response from importer.', 'surerank' ),
			];
		}

		// Only run cleanup if migration was successful.
		if ( $cleanup && $results['success'] && method_exists( $importer, 'cleanup' ) ) {
			$cleanup_resp = $importer->cleanup();

			// Ensure cleanup response is valid.
			if ( is_array( $cleanup_resp ) ) {
				$results['cleanup']         = $cleanup_resp['success'];
				$results['cleanup_message'] = $cleanup_resp['message'];
			}
		}

		$plugin_name        = method_exists( $importer, 'get_plugin_name' ) ? $importer->get_plugin_name() : 'Unknown';
		$results['message'] = sprintf(
			/* translators: 1: import status, 2: plugin name */
			__( 'Global settings %1$s from %2$s.', 'surerank' ),
			$results['success'] ? __( 'imported successfully', 'surerank' ) : __( 'failed to import', 'surerank' ),
			$plugin_name
		);

		if ( $results['success'] ) {
			Send_Json::success( $results );
		} else {
			Send_Json::error( $results );
		}
	}

	/**
	 * Retrieve all post IDs grouped by post type, excluding those already migrated.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 */
	public function get_all_posts( $request ): void {
		$page        = (int) $request->get_param( 'page' );
		$plugin_slug = $request->get_param( 'plugin_slug' );
		$batch_size  = self::BATCH_SIZE;

		$importer_class = $this->importers[ $plugin_slug ];

		/**
		 * The importer class must implement ImporterInterface.
		 *
		 * @var ImporterInterface $importer
		 * */
		$importer   = new $importer_class();
		$post_types = get_post_types(
			[
				'public'  => true,
				'show_ui' => true,
			],
			'names'
		);

		unset( $post_types['attachment'] );
		$post_types = array_values( $post_types );

		// Calculate offset for pagination.
		$offset = ( $page - 1 ) * $batch_size;

		$post_id_and_total_items = $importer->get_count_and_posts( $post_types, $batch_size, $offset );
		$total_items             = $post_id_and_total_items['total_items'];
		$post_ids                = $post_id_and_total_items['post_ids'];
		$grouped                 = $this->group_items( $post_ids );

		Send_Json::success(
			[
				'data'       => $grouped,
				'pagination' => [
					'current_page' => $page,
					'total_pages'  => (int) ceil( $total_items / $batch_size ),
					'total_items'  => $total_items,
					'per_page'     => self::BATCH_SIZE,
				],
			]
		);
	}

	/**
	 * Retrieve all term IDs grouped by taxonomy, excluding those already migrated.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 */
	public function get_all_terms( $request ): void {
		$page        = (int) $request->get_param( 'page' );
		$plugin_slug = $request->get_param( 'plugin_slug' );
		$batch_size  = self::BATCH_SIZE;

		// Initialize response data.
		$grouped            = [];
		$total_terms        = 0;
		$term_ids           = [];
		$taxonomies_objects = $this->get_public_taxonomies();
		if ( empty( $taxonomies_objects ) ) {
			Send_Json::error(
				[
					'message' => __( 'No public taxonomies found.', 'surerank' ),
				]
			);
			return;
		}
			$importer_class = $this->importers[ $plugin_slug ];
			/**
			 * The importer class must implement ImporterInterface.
			 *
			 * @var ImporterInterface $importer
			 */
			$importer = new $importer_class();

			$taxonomies = array_map(
				static function ( $taxonomy ) {
					return $taxonomy->name ?? '';
				},
				$taxonomies_objects
			);
			$taxonomies = array_filter( $taxonomies );

			// Calculate offset for pagination.
			$offset = ( $page - 1 ) * $batch_size;

			// Fetch term IDs with batching.
			$term_ids_and_total_items = $importer->get_count_and_terms(
				$taxonomies,
				$taxonomies_objects,
				$batch_size,
				$offset
			);
			$total_terms              = $term_ids_and_total_items['total_items'] ?? 0;
			$term_ids                 = $term_ids_and_total_items['term_ids'] ?? [];
			$grouped                  = $this->group_items( $term_ids, true, $taxonomies_objects );

		Send_Json::success(
			[
				'data'       => $grouped,
				'pagination' => [
					'current_page' => $page,
					'total_pages'  => 'yoast' === $plugin_slug ? 1 : ( $total_terms > 0 ? (int) ceil( $total_terms / $batch_size ) : 1 ),
					'total_items'  => $total_terms,
					'per_page'     => self::BATCH_SIZE,
				],
			]
		);
	}

	/**
	 * Get a list of SEO plugins available for migration.
	 *
	 * Returns a list of installed plugins that are available for migration.
	 * The results are cached for the duration of the request.
	 *
	 * @return array<string, string> Array of plugin slugs => plugin names that are available for migration.
	 * @since 1.1.0
	 */
	public function get_available_plugins(): array {
		// Early return if no importers are configured.
		if ( empty( $this->importers ) ) {
			return [];
		}

		// Use static cache to avoid repeated processing.
		static $available_plugins = null;
		if ( null !== $available_plugins ) {
			return $available_plugins;
		}

		// Map of supported plugin slugs to their details.
		$supported_plugins = $this->get_supported_plugins();

		$installed_plugins = get_plugins();
		$available_plugins = [];

		// Single pass through the data to build the final array.
		foreach ( $supported_plugins as $key => $plugin ) {
			if ( isset( $this->importers[ $key ] ) && isset( $installed_plugins[ $plugin['slug'] ] ) && $this->is_plugin_active( $plugin['slug'] ) ) {
				$available_plugins[ $key ] = $plugin['name'];
			}
		}

		return $available_plugins;
	}

	/**
	 * Check if the plugin is active or not.
	 *
	 * @param string $plugin Slug of the plugin to check.
	 * @return bool True if the plugin is active, false otherwise.
	 */
	public function is_plugin_active( $plugin ) {
		switch ( $plugin ) {
			case 'seo-by-rank-math/rank-math.php':
				return defined( 'RANK_MATH_VERSION' );
			case 'wordpress-seo/wp-seo.php':
				return defined( 'WPSEO_VERSION' );
			case 'all-in-one-seo-pack/all_in_one_seo_pack.php':
				return defined( 'AIOSEOP_VERSION' );
			case 'wp-seopress/seopress.php':
				return defined( 'SEOPRESS_VERSION' );
			default:
				return false;
		}
	}

	/**
	 * Deactivate a plugin after migration.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 *
	 * @return void
	 */
	public function deactivate_plugin( $request ): void {
		$plugin_slug = $request->get_param( 'plugin_slug' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			Send_Json::error( [ 'message' => __( 'You do not have permission to deactivate plugins.', 'surerank' ) ] );
		}

		$plugin_path = $this->get_plugin_path_from_slug( (string) $plugin_slug );

		if ( ! $plugin_path ) {
			Send_Json::error( [ 'message' => __( 'Plugin not found.', 'surerank' ) ] );
		}

		if ( ! $this->is_plugin_active( (string) $plugin_path ) ) {
			Send_Json::success( [ 'message' => __( 'Plugin is already inactive.', 'surerank' ) ] );
			return;
		}

		deactivate_plugins( (string) $plugin_path );

		Send_Json::success( [ 'message' => __( 'Plugin deactivated successfully.', 'surerank' ) ] );
	}

	/**
	 * Validates the plugin slug and returns the importer instance.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 * @return ImporterInterface The validated importer instance.
	 */
	private function validate_and_get_importer( $request ) {
		$plugin_slug = $request->get_param( 'plugin_slug' );

		if ( ! is_string( $plugin_slug ) ) {
			Send_Json::error(
				[ 'message' => __( 'Invalid plugin slug specified.', 'surerank' ) ]
			);
		}

		$importer_class = $this->importers[ $plugin_slug ];
		/**
		 * The importer class must implement ImporterInterface.
		 *
		 * @var ImporterInterface $importer
		 */
		$importer = new $importer_class();

		if ( ! $importer instanceof ImporterInterface ) {
			Send_Json::error(
				[
					'message' => sprintf(
						// translators: %s: importer class name.
						__( 'Invalid importer class: %s does not implement ImporterInterface.', 'surerank' ),
						$importer_class
					),
				]
			);
		}

		return $importer;
	}

	/**
	 * Get supported plugins for migration.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_supported_plugins(): array {
		return [
			'rankmath' => [
				'name' => 'Rank Math SEO',
				'slug' => 'seo-by-rank-math/rank-math.php',
			],
			'yoast'    => [
				'name' => 'Yoast SEO',
				'slug' => 'wordpress-seo/wp-seo.php',
			],
			'aioseo'   => [
				'name' => 'All in One SEO',
				'slug' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
			],
			'seopress' => [
				'name' => 'SEO Press',
				'slug' => 'wp-seopress/seopress.php',
			],
		];
	}

	/**
	 * Get plugin path from slug.
	 *
	 * @param string $slug The plugin slug.
	 * @return string|null The plugin path or null if not found.
	 */
	private function get_plugin_path_from_slug( string $slug ): ?string {
		$supported_plugins = $this->get_supported_plugins();
		return $supported_plugins[ $slug ]['slug'] ?? null;
	}

	/**
	 * Validates the importer methods for the given type (post or term).
	 *
	 * @param ImporterInterface $importer The importer instance.
	 * @param string            $type The type of item ('post' or 'term').
	 * @return bool True if methods exist, false otherwise.
	 */
	private function validate_importer_methods( ImporterInterface $importer, string $type ): bool {
		$detect_method = 'detect_' . $type;
		$import_method = 'import_' . $type;
		return method_exists( $importer, $detect_method ) && method_exists( $importer, $import_method );
	}

	/**
	 * Processes items (posts or terms) for migration.
	 *
	 * @param array<int>        $ids The array of item IDs.
	 * @param ImporterInterface $importer The importer instance.
	 * @param string            $type The type of item ('post' or 'term').
	 * @return array<int|string, mixed> The results array with success count, failed items, and import data.
	 */
	private function process_migration( array $ids, ImporterInterface $importer, string $type ): array {
		$results          = [
			'success'      => 0,
			'failed_items' => [],
		];
		$send_import_data = [];
		$detect_method    = 'detect_' . $type;
		$import_method    = 'import_' . $type;

		foreach ( $ids as $id ) {
			try {
				$detect = $importer->$detect_method( (int) $id );

				if ( is_array( $detect ) && isset( $detect['no_data_found'] ) && $detect['no_data_found'] ) {
					$send_import_data[ $id ] = $detect['message'] ?? __( 'No data found for this item.', 'surerank' );
					$results['success']++;
					continue;
				}

				if ( ! $detect['success'] ) {
					$results['failed_items'][ $id ] = $detect['message'] ?? __( 'Detection failed.', 'surerank' );
					continue;
				}

				$import = $importer->$import_method( (int) $id );

				if ( ! is_array( $import ) || ! isset( $import['success'] ) ) {
					$results['failed_items'][ $id ] = __( 'Invalid import response.', 'surerank' );
					continue;
				}

				$send_import_data[ $id ] = isset( $import['data'] ) && is_array( $import['data'] ) ? $import['data'] : [];

				if ( $import['success'] ) {
					$results['success']++;
				} else {
					$results['failed_items'][ $id ] = $import['message'] ?? __( 'Import failed.', 'surerank' );
				}
			} catch ( \Exception $e ) {
				$results['failed_items'][ $id ] = sprintf(
					// translators: %s: error message.
					__( 'Error: %s', 'surerank' ),
					$e->getMessage()
				);
			}
		}

		$results['passed_items'] = $send_import_data;
		return $results;
	}

	/**
	 * Handles cleanup after successful imports.
	 *
	 * @param ImporterInterface $importer The importer instance.
	 * @param bool              $cleanup Whether cleanup is requested.
	 * @param int               $success_count The number of successful imports.
	 * @return array<string, mixed> The cleanup results.
	 */
	private function handle_cleanup( ImporterInterface $importer, bool $cleanup, int $success_count ): array {
		$results = [];
		if ( $cleanup && $success_count > 0 && method_exists( $importer, 'cleanup' ) ) {
			$cleanup_resp = $importer->cleanup();
			if ( is_array( $cleanup_resp ) ) {
				$results['cleanup']         = $cleanup_resp['success'];
				$results['cleanup_message'] = $cleanup_resp['message'];
			}
		}
		return $results;
	}

	/**
	 * Formats the final migration response.
	 *
	 * @param array<int|string, mixed> $results The migration results.
	 * @param ImporterInterface        $importer The importer instance.
	 * @param array<int>               $ids The array of item IDs.
	 * @param string                   $item_type The type of item ('posts' or 'terms').
	 * @return array<string, mixed> The formatted results array.
	 */
	private function format_response( array $results, ImporterInterface $importer, array $ids, string $item_type ): array {
		$plugin_name        = method_exists( $importer, 'get_plugin_name' ) ? $importer->get_plugin_name() : 'Unknown';
		$results['message'] = sprintf(
			// translators: 1: imported count, 2: total count, 3: item type, 4: plugin name.
			__( 'Imported %1$d of %2$d %3$s from %4$s.', 'surerank' ),
			$results['success'],
			count( $ids ),
			$item_type,
			$plugin_name
		);
		return $results;
	}

	/**
	 * Helper to validate ID arrays/values.
	 *
	 * @param mixed $param Incoming param.
	 * @return bool
	 */
	private function validate_ids( $param ): bool {
		// Accept both array and single integer.
		if ( is_numeric( $param ) && (int) $param > 0 ) {
			return true;
		}

		if ( ! is_array( $param ) || empty( $param ) ) {
			return false;
		}

		// Validate all array elements are positive integers.
		return array_reduce(
			$param,
			static fn( $valid, $id) => $valid && is_numeric( $id ) && (int) $id > 0,
			true
		);
	}

	/**
	 * Fetch all public taxonomies, excluding unsupported ones.
	 *
	 * @return array<string, object> Array of taxonomy objects.
	 */
	private function get_public_taxonomies(): array {

		return ImporterUtils::get_excluded_taxonomies();
	}

	/**
	 * Group IDs by taxonomy or post type.
	 *
	 * @param array<int|string>     $ids Array of term IDs or post IDs.
	 * @param bool                  $is_taxonomy True to group terms, False to group posts.
	 * @param array<string, object> $taxonomies_objects Array of taxonomy objects (required if $is_taxonomy is true).
	 *
	 * @return array<string, array<mixed>> Grouped data.
	 */
	private function group_items( array $ids, bool $is_taxonomy = false, array $taxonomies_objects = [] ): array {
		$grouped = [];

		foreach ( $ids as $id ) {
			if ( $is_taxonomy ) {
				$term = get_term( (int) $id );
				if ( is_wp_error( $term ) || ! $term ) {
					continue;
				}
				$type  = $term->taxonomy;
				$label = $taxonomies_objects[ $type ]->label ?? $type;
				$key   = 'term_ids';
			} else {
				$type = get_post_type( (int) $id );
				if ( false === $type ) {
					continue;
				}
				$object = get_post_type_object( $type );
				$label  = isset( $object->labels ) ? $object->labels->name : $type;
				$key    = 'post_ids';
			}

			if ( ! isset( $grouped[ $type ] ) ) {
				$grouped[ $type ] = [
					'count' => 0,
					'title' => $label,
					$key    => [],
				];
			}

			$grouped[ $type ][ $key ][] = (int) $id;
			$grouped[ $type ]['count']++;
		}

		return $grouped;
	}
}
