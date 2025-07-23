<?php
/**
 * Yoast Importer Class
 *
 * Handles importing data from Yoast SEO plugin.
 *
 * @package SureRank\Inc\Importers
 * @since   1.1.0
 */

namespace SureRank\Inc\Importers;

use Exception;
use SureRank\Inc\API\Onboarding;
use SureRank\Inc\Functions\Settings;
use SureRank\Inc\Traits\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements Yoast → SureRank migration.
 */
class Yoast extends BaseImporter {

	use Logger;

	/**
	 * Yoast social settings to be imported.
	 *
	 * @var array<string, mixed>
	 */
	private array $yoast_social_settings = [];

	/**
	 * Yoast global robots settings.
	 *
	 * @var array<string, string>
	 */
	private array $yoast_global_robots = YoastConstants::GLOBAL_ROBOTS;

	/**
	 * Source settings from Yoast.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return YoastConstants::PLUGIN_NAME;
	}

	/**
	 * Detect whether the source plugin has data for the given term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{success: bool, message: string}
	 */
	public function detect_term( int $term_id ): array {
		$term = get_term( $term_id );

		if ( ! $term || is_wp_error( $term ) ) {
			return ImporterUtils::build_response(
				sprintf(
					// translators: %d: term ID.
					__( 'Invalid term ID %d.', 'surerank' ),
					$term_id
				),
				false,
				[],
				true
			);
		}
		$this->type = $term->taxonomy && in_array( $term->taxonomy, array_keys( $this->taxonomies ), true ) ? $term->taxonomy : '';
		$meta       = get_option( 'wpseo_taxonomy_meta', [] );

		// Validate meta is array before accessing.
		if ( ! is_array( $meta ) ) {
			$meta = [];
		}

		$term_meta = isset( $meta[ $this->type ] ) && is_array( $meta[ $this->type ] ) && isset( $meta[ $this->type ][ $term_id ] )
			? $meta[ $this->type ][ $term_id ]
			: [];

		if ( ! empty( $term_meta ) ) {
			return ImporterUtils::build_response(
				sprintf(
					// translators: %d: term ID.
					__( 'Yoast SEO data detected for term %d.', 'surerank' ),
					$term_id
				),
				true
			);
		}

		ImporterUtils::update_surerank_migrated( $term_id, false );

		return ImporterUtils::build_response(
			sprintf(
				// translators: %d: term ID.
				__( 'No Yoast SEO data found for term %d.', 'surerank' ),
				$term_id
			),
			false,
			[],
			true
		);
	}

	/**
	 * Import meta-robots settings for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_post_meta_robots( int $post_id ): array {
		$robot_data = $this->yoast_global_robots;

		try {
			// Archive setting.
			if ( ! empty( $this->source_meta['_yoast_wpseo_meta-robots-adv'] )
				&& is_array( $this->source_meta['_yoast_wpseo_meta-robots-adv'] )
				&& ! empty( $this->source_meta['_yoast_wpseo_meta-robots-adv'][0] )
				&& is_string( $this->source_meta['_yoast_wpseo_meta-robots-adv'][0] )
				&& str_contains( $this->source_meta['_yoast_wpseo_meta-robots-adv'][0], 'noarchive' ) ) {
				$robot_data['noarchive'] = 'yes';
			}

			// Follow setting.
			if ( ! empty( $this->source_meta['_yoast_wpseo_meta-robots-nofollow'] ) ) {
				$robot_data['nofollow'] = 'yes';
			}

			// Index setting.
			$robot_index = $this->source_meta['_yoast_wpseo_meta-robots-noindex'] ?? null;

			if ( null === $robot_index ) {
				$meta_key = "noindex-{$this->type}";
				if ( ! empty( $this->source_meta[ $meta_key ] ) ) {
					$robot_data['noindex'] = 'yes';
				}
			} elseif ( is_array( $robot_index ) && ! empty( $robot_index[0] ) && '1' === $robot_index[0] ) {
				$robot_data['noindex'] = 'yes';
			}

			// Apply settings.
			foreach ( $robot_data as $key => $value ) {
				$this->default_surerank_meta[ YoastConstants::ROBOTS_MAPPING[ $key ] ] = $value;
			}

			return ImporterUtils::build_response(
				sprintf(
					// translators: %d: post ID.
					__( 'Meta-robots imported for post %d.', 'surerank' ),
					$post_id
				),
				true
			);
		} catch ( Exception $e ) {
			$this->log_error(
				sprintf(
					/* translators: %d: post ID, %s: error message. */
					__( 'Error importing meta-robots for post %1$d: %2$s', 'surerank' ),
					$post_id,
					$e->getMessage()
				)
			);
			return ImporterUtils::build_response( $e->getMessage(), false );
		}
	}

	/**
	 * Import meta-robots settings for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_term_meta_robots( int $term_id ): array {
		$robot_data = $this->yoast_global_robots;

		try {
			if ( ! empty( $this->source_meta['wpseo_noindex'] ) && 'index' !== $this->source_meta['wpseo_noindex'] ) {
				$robot_data['noindex'] = 'yes';
			} elseif ( ! empty( $this->source_meta[ "noindex-{$this->type}" ] ) ) {
				$robot_data['noindex'] = 'yes';
			}

			// Apply settings.
			foreach ( $robot_data as $key => $value ) {
				$this->default_surerank_meta[ YoastConstants::ROBOTS_MAPPING[ $key ] ] = $value;
			}

			return ImporterUtils::build_response(
				sprintf(
					// translators: %d: term ID.
					__( 'Meta-robots imported for term %d.', 'surerank' ),
					$term_id
				),
				true
			);
		} catch ( Exception $e ) {
			$this->log_error(
				sprintf(
					/* translators: %d: term ID, %s: error message. */
					__( 'Error importing meta-robots for term %1$d: %2$s', 'surerank' ),
					$term_id,
					$e->getMessage()
				)
			);
			return ImporterUtils::build_response( $e->getMessage(), false );
		}
	}

	/**
	 * Import general SEO settings for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_post_general_settings( int $post_id ): array {
		$mapping = [
			'_yoast_wpseo_title'     => [ 'title', 'page_title' ],
			'_yoast_wpseo_metadesc'  => [ 'metadesc', 'page_description' ],
			'_yoast_wpseo_canonical' => [ '', 'canonical_url' ],
		];

		$imported = $this->process_meta_mapping( $mapping );
		// translators: %d: post ID.
		$message = $imported ? __( 'General settings imported for post %d.', 'surerank' ) : __( 'No general settings to import for post %d.', 'surerank' );

		return ImporterUtils::build_response(
			sprintf(
				// translators: %d: post ID.
				$message,
				$post_id
			),
			$imported
		);
	}

	/**
	 * Import general SEO settings for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_term_general_settings( int $term_id ): array {
		$mapping = [
			'wpseo_title'     => [ 'title-tax', 'page_title' ],
			'wpseo_metadesc'  => [ 'metadesc-tax', 'page_description' ],
			'wpseo_desc'      => [ 'metadesc-tax', 'page_description' ],
			'wpseo_canonical' => [ '', 'canonical_url' ],
		];

		$imported = $this->process_meta_mapping( $mapping );
		// translators: %d: term ID.
		$message = $imported ? __( 'General settings imported for term %d.', 'surerank' ) : __( 'No general settings to import for term %d.', 'surerank' );

		return ImporterUtils::build_response(
			sprintf(
				// translators: %d: term ID.
				$message,
				$term_id
			),
			$imported
		);
	}

	/**
	 * Import social metadata for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_post_social( int $post_id ): array {

		$this->default_surerank_meta['twitter_same_as_facebook'] = ! (
		! empty( $this->source_meta['_yoast_wpseo_twitter-title'] ) ||
		( ! empty( $this->source_meta['_yoast_wpseo_twitter-description'] ) ) ||
		( ! empty( $this->source_meta['_yoast_wpseo_twitter-image'] ) ) ||
		( ! empty( $this->source_meta['_yoast_wpseo_twitter-image-id'] ) )
		);

		$imported = $this->process_meta_mapping( YoastConstants::SOCIAL_MAPPING );
		// translators: %d: post ID.

		$message = $imported ? __( 'Social metadata imported for post %d.', 'surerank' ) : __( 'No social metadata to import for post %d.', 'surerank' );

		return ImporterUtils::build_response(
			sprintf(
				// translators: %d: post ID.
				$message,
				$post_id
			),
			$imported
		);
	}

	/**
	 * Import social metadata for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_term_social( int $term_id ): array {
		$this->default_surerank_meta['twitter_same_as_facebook'] = ! (
			! empty( $this->source_meta['wpseo_twitter-title'] ) ||
			( ! empty( $this->source_meta['wpseo_twitter-description'] ) ) ||
			( ! empty( $this->source_meta['wpseo_twitter-image'] ) ) ||
			( ! empty( $this->source_meta['wpseo_twitter-image-id'] ) )
		);
		$term_social_mapping                                     = YoastConstants::TERM_SOCIAL_MAPPING;
		$imported = $this->process_meta_mapping( $term_social_mapping );
		// translators: %d: post ID.

		$message = $imported ? __( 'Social metadata imported for term %d.', 'surerank' ) : __( 'No social metadata to import for term %d.', 'surerank' );

		return ImporterUtils::build_response(
			sprintf(
				// translators: %d: term ID.
				$message,
				$term_id
			),
			$imported
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function import_global_settings(): array {
		$this->source_settings       = get_option( 'wpseo_titles', [] );
		$this->yoast_social_settings = get_option( 'wpseo_social', [] );

		// Ensure source_settings is array.
		if ( ! is_array( $this->source_settings ) ) {
			$this->source_settings = [];
		}

		// Ensure yoast_social_settings is array.
		if ( ! is_array( $this->yoast_social_settings ) ) {
			$this->yoast_social_settings = [];
		}

		if ( empty( $this->source_settings ) ) {
			return ImporterUtils::build_response(
				__( 'No Yoast SEO global settings found to import.', 'surerank' ),
				false
			);
		}
		$this->surerank_settings = Settings::get();

		$this->update_robot_settings( YoastConstants::ROBOT_KEYS_MAPPING );
		$this->update_description_and_title( YoastConstants::TITLE_DESC_MAPPING );
		$this->update_archive_settings( YoastConstants::ARCHIVE_SETTINGS_MAPPING );
		$this->update_twitter_card_type();
		$this->update_other_social_profiles();
		$this->update_social_settings( YoastConstants::OG_SETTINGS_MAPPING );
		$this->update_sitemap_settings( YoastConstants::SITEMAP_MAPPING );
		$this->update_site_details();
		try {
			ImporterUtils::update_global_settings( $this->surerank_settings );
			return ImporterUtils::build_response(
				__( 'Yoast SEO global settings imported successfully.', 'surerank' ),
				true
			);
		} catch ( Exception $e ) {
			$this->log_error(
				sprintf(
					/* translators: %s: error message. */
					__( 'Error importing Yoast SEO global settings: %s', 'surerank' ),
					$e->getMessage()
				)
			);
			return ImporterUtils::build_response( $e->getMessage(), false );
		}
	}

	/**
	 * Get the keys that should be excluded from the import.
	 *
	 * @param array<string>               $taxonomies Term types to check.
	 * @param array<string, \WP_Taxonomy> $taxonomies_objects Taxonomy objects to check.
	 * @param int                         $batch_size Number of terms to fetch in one batch.
	 * @param int                         $offset     Offset for pagination.
	 * @return array{total_items: int, term_ids: array<int>}
	 * @since 1.1.0
	 */
	public function get_count_and_terms( $taxonomies, $taxonomies_objects, $batch_size, $offset ) {

		$result      = YoastConstants::get_term_ids( $taxonomies_objects );
		$term_ids    = $result['term_ids'];
		$total_terms = $result['total_items'];
		return [
			'total_items' => $total_terms,
			'term_ids'    => $term_ids,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_not_allowed_types(): array {
		return YoastConstants::NOT_ALLOWED_TYPES;
	}

	/**
	 * Get the source meta data for a post or term.
	 *
	 * @param int    $id          The ID of the post or term.
	 * @param bool   $is_taxonomy Whether it is a taxonomy.
	 * @param string $type        The type of post or term.
	 * @return array<string, mixed>
	 */
	protected function get_source_meta_data( int $id, bool $is_taxonomy, string $type = '' ): array {
		return YoastConstants::yoast_meta_data( $id, $is_taxonomy, $type );
	}

	/**
	 * Get the meta key prefix for the importer.
	 *
	 * @return string
	 */
	protected function get_meta_key_prefix(): string {
		return YoastConstants::META_KEY_PREFIX;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_excluded_meta_keys(): array {
		return YoastConstants::EXCLUDED_META_KEYS;
	}

	/**
	 * Process meta mapping for general and social settings.
	 *
	 * @param array<string, array<int, string>> $mapping The mapping of old to new keys.
	 * @return bool
	 */
	private function process_meta_mapping( array $mapping ): bool {
		$imported = false;
		foreach ( $mapping as $old_key => $new_key ) {
			$global_yoast_key = ! empty( $new_key[0] ) ? $new_key[0] . '-' . $this->type : '';
			$surerank_key     = $new_key[1];
			$value            = null;

			if ( isset( $this->source_meta[ $old_key ] ) ) {
				$value = $this->source_meta[ $old_key ];
			} elseif ( ! empty( $global_yoast_key ) && isset( $this->source_meta[ $global_yoast_key ] ) ) {
				$value = $this->source_meta[ $global_yoast_key ];
			}

			if ( null !== $value ) {
				$this->default_surerank_meta[ $surerank_key ] = YoastConstants::replace_placeholders( $value, $this->source_meta['separator'] ?? '-' );
				$imported                                     = true;
			}
		}
		return $imported;
	}

	/**
	 * Update robot settings.
	 *
	 * @param array<string, string> $robot_keys_mapping Mapping of Yoast keys to SureRank keys.
	 * @return void
	 */
	private function update_robot_settings( array $robot_keys_mapping ): void {
		foreach ( $this->post_types as $post_type ) {
			if ( ! isset( $robot_keys_mapping[ 'noindex-' . $post_type ] ) ) {
				$robot_keys_mapping[ 'noindex-' . $post_type ] = $post_type;
			}
		}
		foreach ( $this->taxonomies as $taxonomy => $object ) {
			if ( ! isset( $robot_keys_mapping[ 'noindex-tax-' . $taxonomy ] ) ) {
				$robot_keys_mapping[ 'noindex-tax-' . $taxonomy ] = $taxonomy;
			}
		}
		// Ensure no_index array exists.
		if ( ! isset( $this->surerank_settings['no_index'] ) || ! is_array( $this->surerank_settings['no_index'] ) ) {
			$this->surerank_settings['no_index'] = [];
		}

		foreach ( $robot_keys_mapping as $yoast_key => $surerank_key ) {

			if ( isset( $this->source_settings[ $yoast_key ] ) ) {
				$set_no_index = $this->source_settings[ $yoast_key ];
				$in_array     = in_array( $surerank_key, $this->surerank_settings['no_index'], true );
				if ( $set_no_index && ! $in_array ) {
					$this->surerank_settings['no_index'][] = $surerank_key;
				} elseif ( ! $set_no_index && $in_array ) {
					// Remove from no_index if it exists.
					$this->surerank_settings['no_index'] = array_values( array_diff( $this->surerank_settings['no_index'], [ $surerank_key ] ) );
				}
			}
		}
	}

	/**
	 * Update description and title.
	 *
	 * @param array<string, string> $mapping Mapping of Yoast keys to SureRank keys.
	 * @return void
	 */
	private function update_description_and_title( array $mapping ): void {
		foreach ( $mapping as $yoast_key => $surerank_key ) {
			if ( ! empty( $this->source_settings[ $yoast_key ] ) ) {
				$this->surerank_settings[ $surerank_key ] = YoastConstants::replace_placeholders( $this->source_settings[ $yoast_key ], $this->source_settings['separator'] ?? '-' );
			}
		}
	}

	/**
	 * Update archive settings.
	 *
	 * @param array<string, string> $archive_settings Archive settings mapping.
	 * @return void
	 */
	private function update_archive_settings( array $archive_settings ): void {
		foreach ( $archive_settings as $yoast_key => $surerank_key ) {
			if ( isset( $this->source_settings[ $yoast_key ] ) ) {
				$this->surerank_settings[ $surerank_key ] = $this->source_settings[ $yoast_key ] ? 0 : 1;
			}
		}
	}

	/**
	 * Update Twitter card type.
	 *
	 * @return void
	 */
	private function update_twitter_card_type(): void {
		if ( ! empty( $this->yoast_social_settings['twitter_card_type'] ) ) {
			$this->surerank_settings['twitter_card_type'] = 'summary' === $this->yoast_social_settings['twitter_card_type'] ? 'summary_card' : 'summary_large_image';
		}
	}

	/**
	 * Update social settings.
	 *
	 * @param array<string, string> $og_settings Open Graph settings mapping.
	 * @return void
	 */
	private function update_social_settings( array $og_settings ): void {
		foreach ( $og_settings as $yoast_key => $surerank_key ) {
			if ( ! empty( $this->yoast_social_settings[ $yoast_key ] ) ) {
				if ( 'twitter_site' === $yoast_key ) {
					$this->surerank_settings[ $surerank_key ] = 'https://x.com/' . $this->yoast_social_settings[ $yoast_key ];
					continue;
				}
				$this->surerank_settings[ $surerank_key ] = $this->yoast_social_settings[ $yoast_key ];
			}
		}
	}

	/**
	 * Update other social profiles.
	 *
	 * @return void
	 */
	private function update_other_social_profiles() {
		$other_social_profiles                      = isset( $this->yoast_social_settings['other_social_urls'] ) && is_array( $this->yoast_social_settings['other_social_urls'] )
			? $this->yoast_social_settings['other_social_urls']
			: [];
		$this->surerank_settings['social_profiles'] = ImporterUtils::get_mapped_social_profiles( $other_social_profiles, $this->surerank_settings['social_profiles'] ?? [] );
	}

	/**
	 * Update sitemap settings.
	 *
	 * @param array<string, string> $site_map_mapping Site map mapping.
	 * @return void
	 */
	private function update_sitemap_settings( array $site_map_mapping ): void {
		$sitemap_settings = get_option( 'wpseo', [] );

		// Ensure sitemap settings is array.
		if ( ! is_array( $sitemap_settings ) ) {
			$sitemap_settings = [];
		}

		if ( ! empty( $sitemap_settings ) ) {
			foreach ( $site_map_mapping as $yoast_key => $surerank_key ) {
				$this->surerank_settings[ $surerank_key ] = ! empty( $sitemap_settings[ $yoast_key ] ) ? 1 : 0;
			}
		}
	}

	/**
	 * Update site details based on the source settings.
	 *
	 * @return void
	 */
	private function update_site_details(): void {
		$knowledgegraph_type = ! empty( $this->source_settings['company_or_person'] ) ? $this->source_settings['company_or_person'] : '';
		$logo_key            = 'company' === $knowledgegraph_type ? 'company_logo' : 'person_logo';
		$site_data           = [
			'website_name'        => ! empty( $this->source_settings['website_name'] ) ? $this->source_settings['website_name'] : '',
			'organization_type'   => $knowledgegraph_type === 'company' ? 'Organization' : 'Person',
			'website_logo'        => ! empty( $this->source_settings[ $logo_key ] ) ? $this->source_settings[ $logo_key ] : '',
			'website_type'        => $knowledgegraph_type === 'company' ? 'organization' : 'person',
			'website_owner_phone' => ! empty( $this->source_settings['org-phone'] ) ? $this->source_settings['org-phone'] : '',
		];
		Onboarding::update_common_onboarding_data( $site_data );
	}

}
