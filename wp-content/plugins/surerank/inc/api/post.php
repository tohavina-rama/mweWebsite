<?php
/**
 * Post class
 *
 * Handles post related REST API endpoints for the SureRank plugin.
 *
 * @package SureRank\Inc\API
 */

namespace SureRank\Inc\API;

use SureRank\Inc\Functions\Defaults;
use SureRank\Inc\Functions\Get;
use SureRank\Inc\Functions\Helper;
use SureRank\Inc\Functions\Send_Json;
use SureRank\Inc\Functions\Settings;
use SureRank\Inc\Functions\Update;
use SureRank\Inc\Traits\Get_Instance;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Post
 *
 * Handles post related REST API endpoints.
 */
class Post extends Api_Base {
	use Get_Instance;

	/**
	 * Route Get Post Seo Data
	 */
	protected const POST_SEO_DATA = '/post-seo-data';

	/**
	 * Route Get Post Content
	 */
	protected const POST_CONTENT = '/post-content';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Register API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->get_api_namespace(),
			self::POST_SEO_DATA,
			[
				'methods'             => WP_REST_Server::READABLE, // GET Post Seo Data.
				'callback'            => [ $this, 'get_post_seo_data' ],
				'permission_callback' => [ $this, 'validate_permission' ],
				'args'                => [
					'post_id'   => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'post_type' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			self::POST_SEO_DATA,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_post_seo_data' ],
				'permission_callback' => [ $this, 'validate_permission' ],
				'args'                => [
					'post_id'  => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'metaData' => [
						'type'              => 'object',
						'required'          => true,
						'sanitize_callback' => [ $this, 'sanitize_array_data' ],
					],
				],

			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			self::POST_CONTENT,
			[
				'methods'             => WP_REST_Server::READABLE, // Get Post Content.
				'callback'            => [ $this, 'get_post_type_data' ],
				'permission_callback' => [ $this, 'validate_permission' ],

			]
		);
	}

	/**
	 * Get post seo data
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 * @return void
	 */
	public function get_post_seo_data( $request ) {

		$post_id     = $request->get_param( 'post_id' );
		$post_type   = $request->get_param( 'post_type' );
		$is_taxonomy = $request->get_param( 'is_taxonomy' );

		$data        = self::get_post_data_by_id( $post_id, $post_type, $is_taxonomy );
		$decode_data = Utils::decode_html_entities_recursive( $data ) ?? $data;
		Send_Json::success( $decode_data );
	}

	/**
	 * Get post data by id
	 *
	 * @param int    $post_id Post id.
	 * @param string $post_type Post type.
	 * @param bool   $is_taxonomy Is taxonomy.
	 * @return array<string, mixed>
	 */
	public static function get_post_data_by_id( $post_id, $post_type = 'post', $is_taxonomy = false ) {
		$all_options = Settings::format_array( Defaults::get_instance()->get_post_defaults( false ) );
		return [
			'data'           => array_intersect_key( Settings::prep_post_meta( $post_id, $post_type, $is_taxonomy ), $all_options ),
			'global_default' => Settings::get(),
		];
	}

	/**
	 * Update seo data
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 * @return void
	 */
	public function update_post_seo_data( $request ) {

		$post_id = $request->get_param( 'post_id' );
		$data    = $request->get_param( 'metaData' );

		self::update_post_meta_common( $post_id, $data );

		if ( is_wp_error( $this->run_checks( $post_id ) ) ) {
			Send_Json::error( [ 'message' => __( 'Error while running SEO Checks.', 'surerank' ) ] );
		}

		Send_Json::success( [ 'message' => __( 'Data updated', 'surerank' ) ] );
	}

	/**
	 * Update post meta common
	 * This function updates the post meta for a given post ID with the provided data.
	 * It merges existing post meta with the new data, ensuring that all options are updated correctly.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data Data to update.
	 * @since 1.0.0
	 * @return void
	 */
	public static function update_post_meta_common( int $post_id, array $data ): void {
		$all_options = Defaults::get_instance()->get_post_defaults( false );

		/** Getting post meta if exists, otherwise getting all options(defaults) */
		$post_meta = Get::all_post_meta( $post_id );
		if ( ! empty( $post_meta ) ) {
			$data = array_merge( $post_meta, $data );
		}

		$processed_options = Utils::process_option_values( $all_options, $data );

		foreach ( $processed_options as $option_name => $new_option_value ) {
			Update::post_meta( $post_id, 'surerank_settings_' . $option_name, $new_option_value );
		}
	}

	/**
	 * Get post types
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 * @return void
	 */
	public function get_post_type_data( $request ) {

		$data           = [];
		$settings       = Settings::get();
		$author_archive = $settings['author_archive'] ?? false;
		$date_archive   = $settings['date_archive'] ?? false;

		// Get all post types.
		$post_types = \get_post_types( [ 'public' => true ], 'objects' );
		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			// Get key and label.
			$data['post_types'] = array_map(
				static function ( $post_type ) {
					return ! empty( $post_type->label ) ? $post_type->label : '';
				},
				$post_types
			);
		}

		// Get all taxonomies.
		$taxonomies = \get_taxonomies( [ 'public' => true ], 'objects' );
		if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
			// Get key and label.
			$data['taxonomies'] = array_map(
				static function ( $taxonomy ) {
					return $taxonomy->label;
				},
				$taxonomies
			);
		}

		$archives = [];

		if ( $author_archive ) {
			$archives['author'] = __( 'Author pages', 'surerank' );
		}
		if ( $date_archive ) {
			$archives['date'] = __( 'Date archives', 'surerank' );
		}

		$archives['search'] = __( 'Search pages', 'surerank' );

		$data['archives'] = $archives;

		// Get user roles.
		$roles = Helper::get_role_names();
		if ( ! empty( $roles ) ) {
			$data['roles'] = $roles;
		}

		Send_Json::success( [ 'data' => $data ] );
	}

	/**
	 * Run checks
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Error|array<string, mixed>
	 */
	public function run_checks( $post_id ) {
		if ( ! $post_id ) {
			return new WP_Error( 'no_post_id', __( 'No post ID provided.', 'surerank' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'no_post', __( 'No post found.', 'surerank' ) );
		}

		return apply_filters( 'surerank_run_post_seo_checks', $post_id, $post );
	}
}
