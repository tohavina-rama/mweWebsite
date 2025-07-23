<?php
/**
 * RankMath Importer Class
 *
 * Handles importing data from RankMath SEO plugin.
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
 * Implements RankMath → SureRank migration.
 */
class RankMath extends BaseImporter {

	use Logger;
	/**
	 * RankMath global robots settings.
	 *
	 * @var array<string, string>
	 */
	private array $rank_math_global_robots = RankMathConstants::GLOBAL_ROBOTS;

	/**
	 * Get the source plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return RankMathConstants::PLUGIN_NAME;
	}

	/**
	 * Detect whether the source plugin has data for the given term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{success: bool, message: string}
	 */
	public function detect_term( int $term_id ): array {
		$meta = get_term_meta( $term_id );

		if ( $this->has_source_meta( $meta ) ) {
			return ImporterUtils::build_response(
				sprintf(
					// translators: %d: term ID.
					__( 'RankMath data detected for term %d.', 'surerank' ),
					$term_id
				),
				true
			);
		}

		ImporterUtils::update_surerank_migrated( $term_id, false );

		return ImporterUtils::build_response(
			sprintf(
				// translators: %d: term ID.
				__( 'No RankMath data found for term %d.', 'surerank' ),
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
		return $this->import_post_taxo_robots( $post_id, false );
	}

	/**
	 * Import meta-robots settings for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_term_meta_robots( int $term_id ): array {
		return $this->import_post_taxo_robots( $term_id, true );
	}

	/**
	 * Import general SEO settings for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_post_general_settings( int $post_id ): array {
		return $this->import_post_taxo_general_settings( $post_id, false );
	}

	/**
	 * Import general SEO settings for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_term_general_settings( int $term_id ): array {
		return $this->import_post_taxo_general_settings( $term_id, true );
	}

	/**
	 * Import social metadata for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_post_social( int $post_id ): array {
		return $this->import_post_taxo_social( $post_id, false );
	}

	/**
	 * Import social metadata for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{success: bool, message: string}
	 */
	public function import_term_social( int $term_id ): array {
		return $this->import_post_taxo_social( $term_id, true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function import_global_settings(): array {
		$this->source_settings = get_option( 'rank-math-options-titles', [] );
		if ( empty( $this->source_settings ) || ! is_array( $this->source_settings ) ) {
			return ImporterUtils::build_response(
				__( 'No RankMath global settings found to import.', 'surerank' ),
				false
			);
		}
		$this->surerank_settings = Settings::get();

		$this->update_global_robot_settings();
		$this->update_robot_settings( RankMathConstants::ROBOT_KEYS_MAPPING );
		$this->update_homepage_robots();
		$this->update_description_and_title( RankMathConstants::TITLE_DESC_MAPPING );
		$this->update_archive_settings( RankMathConstants::ARCHIVE_SETTINGS_MAPPING );
		$this->update_twitter_card_type();
		$this->update_social_mapping( RankMathConstants::SOCIAL_SETTINGS_MAPPING );
		$this->update_sitemap_settings( RankMathConstants::SITEMAP_MAPPING );
		$this->update_site_details();

		try {
			ImporterUtils::update_global_settings( $this->surerank_settings );
			return ImporterUtils::build_response(
				__( 'RankMath global settings imported successfully.', 'surerank' ),
				true
			);
		} catch ( Exception $e ) {
			$this->log_error(
				sprintf(
					/* translators: %s: error message. */
					__( 'Error importing RankMath global settings: %s', 'surerank' ),
					$e->getMessage()
				)
			);
			return ImporterUtils::build_response( $e->getMessage(), false );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_not_allowed_types(): array {
		return RankMathConstants::NOT_ALLOWED_TYPES;
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
		return RankMathConstants::rank_math_meta_data( $id, $is_taxonomy );
	}

	/**
	 * Get the meta key prefix for the importer.
	 *
	 * @return string
	 */
	protected function get_meta_key_prefix(): string {
		return RankMathConstants::META_KEY_PREFIX;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_excluded_meta_keys(): array {
		return RankMathConstants::EXCLUDED_META_KEYS;
	}

	/**
	 * Import meta-robots settings for a post or term.
	 *
	 * @param int  $id         Post or Term ID.
	 * @param bool $is_taxonomy Whether the ID is for a taxonomy term.
	 * @return array{success: bool, message: string}
	 */
	private function import_post_taxo_robots( int $id, bool $is_taxonomy = false ): array {
		try {
			$robot_data = $this->source_meta['rank_math_robots'] ?? null;
			if ( $robot_data === null ) {
				$get_robot_key = RankMathConstants::get_robot_key( $this->type, $this->source_meta, $is_taxonomy );
				$robot_data    = $this->source_meta[ $get_robot_key ] ?? null;
			} else {
				if ( is_array( $robot_data ) ) {
					$robot_data = maybe_unserialize( $robot_data[0] );
				}
			}

			$robot_data = RankMathConstants::get_mapped_robots( $robot_data );

			if ( empty( $robot_data ) ) {
				return ImporterUtils::build_response(
					sprintf(
						// translators: %d: ID, %s: type.
						__( 'No meta-robots settings to import for %1$s %2$d.', 'surerank' ),
						$is_taxonomy ? 'term' : 'post',
						$id
					),
					false
				);
			}

			foreach ( $robot_data as $key => $value ) {
				$this->default_surerank_meta[ RankMathConstants::ROBOTS_MAPPING[ $key ] ] = $value === 'yes' ? 'yes' : 'no';
			}

			return ImporterUtils::build_response(
				sprintf(
					// translators: %d: ID, %s: type.
					__( 'Meta-robots imported for %1$s %2$d.', 'surerank' ),
					$is_taxonomy ? 'term' : 'post',
					$id
				),
				true
			);
		} catch ( Exception $e ) {
			$this->log_error(
				sprintf(
					/* translators: 1: ID, 2: type, 3: error message. */
					__( 'Error importing meta-robots for %2$s %1$d: %3$s', 'surerank' ),
					$id,
					$is_taxonomy ? 'term' : 'post',
					$e->getMessage()
				)
			);
			return ImporterUtils::build_response( $e->getMessage(), false );
		}
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
			$global_rank_math_key = $new_key[0];
			$surerank_key         = $new_key[1];
			$value                = null;

			if ( isset( $this->source_meta[ $old_key ] ) ) {
				$value = $this->source_meta[ $old_key ][0];
			} elseif ( ! empty( $global_rank_math_key ) && isset( $this->source_meta[ $global_rank_math_key ] ) ) {
				$value = $this->source_meta[ $global_rank_math_key ];
			}

			if ( null !== $value ) {
				$this->default_surerank_meta[ $surerank_key ] = RankMathConstants::replace_placeholders( $value, $this->source_meta['title_separator'] ?? '-' );
				$imported                                     = true;
			}
		}
		return $imported;
	}

	/**
	 * Import general SEO settings for a post or term.
	 *
	 * @param int  $id         Post or Term ID.
	 * @param bool $is_taxonomy Whether the ID is for a taxonomy term.
	 * @return array{success: bool, message: string}
	 */
	private function import_post_taxo_general_settings( int $id, bool $is_taxonomy = false ): array {
		$page_title_description = RankMathConstants::get_page_title_description( $this->type, $is_taxonomy );
		$page_title             = $page_title_description['page_title'];
		$page_description       = $page_title_description['page_description'];
		$mapping                = [
			'rank_math_title'         => [ $page_title, 'page_title' ],
			'rank_math_description'   => [ $page_description, 'page_description' ],
			'rank_math_canonical_url' => [ '', 'canonical_url' ],
		];

		$imported = $this->process_meta_mapping( $mapping );

		if ( $imported ) {
			return ImporterUtils::build_response(
				sprintf(
					// translators: %d: ID, %s: type.
					__( 'General settings imported for %1$s %2$d.', 'surerank' ),
					$is_taxonomy ? 'term' : 'post',
					$id
				),
				true
			);
		}

		return ImporterUtils::build_response(
			sprintf(
				// translators: %d: ID, %s: type.
				__( 'No general settings to import for %1$s %2$d.', 'surerank' ),
				$is_taxonomy ? 'term' : 'post',
				$id
			),
			false
		);
	}

	/**
	 * Import social metadata for a post or term.
	 *
	 * @param int  $id         Post or Term ID.
	 * @param bool $is_taxonomy Whether the ID is for a taxonomy term.
	 * @return array{success: bool, message: string}
	 */
	private function import_post_taxo_social( int $id, bool $is_taxonomy = false ): array {
		$same_as_facebook                                        = $this->source_meta['rank_math_twitter_use_facebook'][0] ?? 'on';
		$this->default_surerank_meta['twitter_same_as_facebook'] = 'on' === $same_as_facebook ? true : false;

		$imported = $this->process_meta_mapping( RankMathConstants::SOCIAL_MAPPING );

		if ( $imported ) {
			return ImporterUtils::build_response(
				sprintf(
					// translators: %d: ID, %s: type.
					__( 'Social metadata imported for %1$s %2$d.', 'surerank' ),
					$is_taxonomy ? 'term' : 'post',
					$id
				),
				true
			);
		}

		return ImporterUtils::build_response(
			sprintf(
				// translators: %d: ID, %s: type.
				__( 'No social metadata to import for %1$s %2$d.', 'surerank' ),
				$is_taxonomy ? 'term' : 'post',
				$id
			),
			false
		);
	}

	/**
	 * Update robot settings.
	 *
	 * @param array<string, array<int, string>> $robot_keys_mapping Mapping of RankMath keys to SureRank keys.
	 * @return void
	 */
	private function update_robot_settings( $robot_keys_mapping ): void {
		$robot_data = [
			'noindex'   => 'no_index',
			'nofollow'  => 'no_follow',
			'noarchive' => 'no_archive',
		];
		$post_types = $this->post_types;
		foreach ( $post_types as $post_type ) {
			$custom_robots                            = "pt_{$post_type}_robots";
			$custom_robots_key                        = 'pt_' . $post_type . '_custom_robots';
			$robot_keys_mapping[ $custom_robots_key ] = [ $custom_robots, $post_type ];
		}
		$taxonomies = $this->taxonomies;
		foreach ( $taxonomies as $taxonomy ) {
			$custom_robots                            = "tax_{$taxonomy->name}_robots";
			$custom_robots_key                        = 'tax_' . $taxonomy->name . '_custom_robots';
			$robot_keys_mapping[ $custom_robots_key ] = [ $custom_robots, $taxonomy->name ];
		}

		foreach ( $robot_keys_mapping as $rankmath_key => $surerank_key ) {
			$first       = $surerank_key[0];
			$second      = $surerank_key[1];
			$is_custom   = isset( $this->source_settings[ $rankmath_key ] ) && 'on' === $this->source_settings[ $rankmath_key ];
			$robot_rules = $is_custom ? ( $this->source_settings[ $first ] ?? [] ) : $this->rank_math_global_robots;

			if ( $is_custom ) {
				$robot_rules = RankMathConstants::get_mapped_robots( $robot_rules );
			}

			if ( empty( $robot_rules ) ) {
				continue;
			}

			foreach ( $robot_rules as $key => $value ) {
				if ( ! isset( $robot_data[ $key ] ) ) {
					continue;
				}
				$surerank_robot_key = $robot_data[ $key ];
				$in_array           = in_array( $second, $this->surerank_settings[ $surerank_robot_key ], true );

				if ( 'yes' === $value && ! $in_array ) {
					$this->surerank_settings[ $surerank_robot_key ][] = $second;
				} elseif ( 'no' === $value && $in_array ) {
					$this->surerank_settings[ $surerank_robot_key ] = array_values( array_diff( $this->surerank_settings[ $surerank_robot_key ], [ $second ] ) );
				}
			}
		}
	}

	/**
	 * Update homepage robots.
	 *
	 * @return void
	 */
	private function update_homepage_robots(): void {
		$is_custom   = isset( $this->source_settings['homepage_custom_robots'] ) && 'on' === $this->source_settings['homepage_custom_robots'];
		$robot_rules = $is_custom ? ( $this->source_settings['homepage_robots'] ?? [] ) : $this->rank_math_global_robots;

		if ( $is_custom ) {
			$robot_rules = RankMathConstants::get_mapped_robots( $robot_rules );
		}

		if ( empty( $robot_rules ) ) {
			return;
		}

		foreach ( $robot_rules as $key => $value ) {
			$in_array = in_array( $key, $this->surerank_settings['home_page_robots']['general'], true );
			if ( 'yes' === $value && ! $in_array ) {
				$this->surerank_settings['home_page_robots']['general'][] = $key;
			} elseif ( 'no' === $value && $in_array ) {
				$this->surerank_settings['home_page_robots']['general'] = array_values( array_diff( $this->surerank_settings['home_page_robots']['general'], [ $key ] ) );
			}
		}
	}

	/**
	 * Update description and title.
	 *
	 * @param array<string, string> $mapping Mapping of RankMath keys to SureRank keys.
	 * @return void
	 */
	private function update_description_and_title( array $mapping ): void {
		foreach ( $mapping as $rankmath_key => $surerank_key ) {
			if ( isset( $this->source_settings[ $rankmath_key ] ) && ! empty( $this->source_settings[ $rankmath_key ] ) ) {
				$this->surerank_settings[ $surerank_key ] = RankMathConstants::replace_placeholders( $this->source_settings[ $rankmath_key ], $this->source_settings['title_separator'] ?? '-' );
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
		foreach ( $archive_settings as $rankmath_key => $surerank_key ) {
			if ( isset( $this->source_settings[ $rankmath_key ] ) && ! empty( $this->source_settings[ $rankmath_key ] ) ) {
				$this->surerank_settings[ $surerank_key ] = 'on' === $this->source_settings[ $rankmath_key ] ? 0 : 1;
			}
		}
	}

	/**
	 * Update Twitter card type.
	 *
	 * @return void
	 */
	private function update_twitter_card_type(): void {
		if ( isset( $this->source_settings['twitter_card_type'] ) && ! empty( $this->source_settings['twitter_card_type'] ) ) {
			$this->surerank_settings['twitter_card_type'] = 'summary_card' === $this->source_settings['twitter_card_type'] ? 'summary' : 'summary_large_image';
		}
	}

	/**
	 * Update social mapping.
	 *
	 * @param array<string, string> $social_mapping Social mapping.
	 * @return void
	 */
	private function update_social_mapping( array $social_mapping ): void {

		$other_social_profiles                      = $this->source_settings['social_additional_profiles'] ?? [];
		$this->surerank_settings['social_profiles'] = ImporterUtils::get_mapped_social_profiles( $other_social_profiles, $this->surerank_settings['social_profiles'] ?? [] );

		foreach ( $social_mapping as $rankmath_key => $surerank_key ) {
			if ( 'twitter_author_names' === $rankmath_key && ! empty( $this->source_settings[ $rankmath_key ] ) ) {
				$this->surerank_settings[ $surerank_key ] = 'https://x.com/' . $this->source_settings[ $rankmath_key ];
				continue;
			}

			if ( isset( $this->source_settings[ $rankmath_key ] ) && ! empty( $this->source_settings[ $rankmath_key ] ) ) {
				$this->surerank_settings[ $surerank_key ] = RankMathConstants::replace_placeholders( $this->source_settings[ $rankmath_key ], $this->source_settings['title_separator'] ?? '-' );
			}
		}
	}

	/**
	 * Update sitemap settings.
	 *
	 * @param array<string, string> $site_map_mapping Site map mapping.
	 * @return void
	 */
	private function update_sitemap_settings( array $site_map_mapping ): void {
		$get_sitemap_settings = get_option( 'rank-math-options-sitemap', [] );
		if ( ! empty( $get_sitemap_settings ) && is_array( $get_sitemap_settings ) ) {
			foreach ( $site_map_mapping as $rankmath_key => $surerank_key ) {
				$this->surerank_settings[ $surerank_key ] = isset( $get_sitemap_settings[ $rankmath_key ] ) && 'on' === $get_sitemap_settings[ $rankmath_key ] ? 1 : 0;
			}
		}
	}

	/**
	 * Update site details based on the source settings.
	 *
	 * @return void
	 */
	private function update_site_details(): void {
		$knowledgegraph_type = ! empty( $this->source_settings['knowledgegraph_type'] ) ? $this->source_settings['knowledgegraph_type'] : '';
		$site_data           = [
			'website_name'      => ! empty( $this->source_settings['website_name'] ) ? $this->source_settings['website_name'] : '',
			'organization_type' => $knowledgegraph_type === 'company' ? 'Organization' : 'Person',
			'website_logo'      => ! empty( $this->source_settings['knowledgegraph_logo'] ) ? $this->source_settings['knowledgegraph_logo'] : '',
			'website_type'      => $knowledgegraph_type === 'company' ? 'organization' : 'person',
		];
		Onboarding::update_common_onboarding_data( $site_data );
	}

	/**
	 * Update global robot settings.
	 *
	 * @return void
	 */
	private function update_global_robot_settings(): void {
		$global_robots                 = $this->source_settings['robots_global'] ?? [];
		$this->rank_math_global_robots = RankMathConstants::get_mapped_robots( $global_robots );
	}
}
