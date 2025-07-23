<?php
/**
 * Yoast Constants
 *
 * Defines constants and utility functions for Yoast SEO plugin importer.
 *
 * @package SureRank\Inc\Importers
 * @since   1.1.0
 */

namespace SureRank\Inc\Importers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class YoastConstants
 */
class YoastConstants {
	/**
	 * Human-readable plugin name.
	 */
	public const PLUGIN_NAME = 'Yoast SEO';

	/**
	 * Prefix for Yoast meta keys.
	 */
	public const META_KEY_PREFIX = '_yoast_wpseo_';

	/**
	 * Yoast global robots settings.
	 */
	public const GLOBAL_ROBOTS = [
		'noindex'   => 'no',
		'nofollow'  => 'no',
		'noarchive' => 'no',
	];

	/**
	 * Allowed post and term types for import.
	 */
	public const NOT_ALLOWED_TYPES = [
		'elementor_library',
		'product_shipping_class',
	];

	/**
	 * Mapping of Yoast robots to SureRank robots.
	 */
	public const ROBOTS_MAPPING = [
		'noindex'   => 'post_no_index',
		'nofollow'  => 'post_no_follow',
		'noarchive' => 'post_no_archive',
	];

	public const EXCLUDED_META_KEYS = [
		'_yoast_wpseo_primary_category',
		'_yoast_wpseo_content_score',
		'_yoast_wpseo_estimated-reading-time-minutes',
		'_yoast_wpseo_keywordsynonyms',
		'_yoast_wpseo_focuskeywords',
	];

	/**
	 * Mapping of Yoast social meta to SureRank social meta.
	 */
	public const SOCIAL_MAPPING = [
		'_yoast_wpseo_opengraph-title'       => [ 'social-title', 'facebook_title' ],
		'_yoast_wpseo_opengraph-description' => [ 'social-description', 'facebook_description' ],
		'_yoast_wpseo_opengraph-image'       => [ 'social-image-url', 'facebook_image_url' ],
		'_yoast_wpseo_opengraph-image-id'    => [ 'social-image-id', 'facebook_image_id' ],
		'_yoast_wpseo_twitter-title'         => [ '', 'twitter_title' ],
		'_yoast_wpseo_twitter-description'   => [ '', 'twitter_description' ],
		'_yoast_wpseo_twitter-image'         => [ '', 'twitter_image_url' ],
		'_yoast_wpseo_twitter-image-id'      => [ '', 'twitter_image_id' ],
	];

	/**
	 * Mapping of Yoast placeholders to SureRank placeholders.
	 */
	public const PLACEHOLDERS_MAPPING = [
		'%%sitename%%'         => '%site_name%',
		'%%modified%%'         => '%modified%',
		'%%date%%'             => '%published%',
		'%%sep%%'              => '-',
		'%%page%%'             => '%page%',
		'%%currenttime%%'      => '%currenttime%',
		'%%currentyear%%'      => '%currentyear%',
		'%%currentmonth%%'     => '%currentmonth%',
		'%%currentday%%'       => '%currentday%',
		'%%currentdate%%'      => '%currentdate%',
		'%%org_name%%'         => '%org_name%',
		'%%org_url%%'          => '%org_url%',
		'%%org_logo%%'         => '%org_logo%',
		'%%name%%'             => '%author_name%',
		'%%post_url%%'         => '%post_url%',
		'%%title%%'            => '%title%',
		'%%excerpt%%'          => '%excerpt%',
		'%%term_title%%'       => '%term_title%',
		'%%term_description%%' => '%term_description%',
		'%%sitedesc%%'         => '%tagline%',
	];

	/**
	 * Mapping for global title and description settings.
	 */
	public const TITLE_DESC_MAPPING = [
		'title-home-wpseo'           => 'home_page_title',
		'metadesc-home-wpseo'        => 'home_page_description',
		'open_graph_frontpage_title' => 'home_page_facebook_title',
		'open_graph_frontpage_desc'  => 'home_page_facebook_description',
		'open_graph_frontpage_image' => 'home_page_facebook_image_url',
	];

	public const OG_SETTINGS_MAPPING = [
		'twitter_card_type'      => 'twitter_card_type',
		'twitter_site'           => 'twitter_profile_username',
		'facebook_site'          => 'facebook_page_url',
		'facebook_default_image' => 'fallback_image',
	];

	/**
	 * Mapping for archive settings.
	 */
	public const ARCHIVE_SETTINGS_MAPPING = [
		'disable-author'       => 'author_archive',
		'disable-date'         => 'date_archive',
		'noindex-author-wpseo' => 'noindex_paginated_pages',
	];

	/**
	 * Mapping for sitemap settings.
	 */
	public const SITEMAP_MAPPING = [
		'enable_xml_sitemap' => 'enable_xml_sitemap',
	];

	/**
	 * Mapping for robot settings.
	 */
	public const ROBOT_KEYS_MAPPING = [
		'noindex-post'            => 'post',
		'noindex-page'            => 'page',
		'noindex-attachment'      => 'attachment',
		'noindex-tax-category'    => 'category',
		'noindex-tax-post_tag'    => 'post_tag',
		'noindex-tax-post_format' => 'post_format',
		'noindex-author-wpseo'    => 'author',
		'nodeindx-date-archive'   => 'date_archive',
	];

	/**
	 * Mapping for social settings.
	 * Term social settings are mapped to SureRank social meta.
	 */
	public const TERM_SOCIAL_MAPPING = [
		'wpseo_opengraph-title'       => [ 'social-title-tax', 'facebook_title' ],
		'wpseo_opengraph-description' => [ 'social-description-tax', 'facebook_description' ],
		'wpseo_opengraph-image'       => [ 'social-image-url-tax', 'facebook_image_url' ],
		'wpseo_opengraph-image-id'    => [ 'social-image-id-tax', 'facebook_image_id' ],
		'wpseo_twitter-title'         => [ '', 'twitter_title' ],
		'wpseo_twitter-description'   => [ '', 'twitter_description' ],
		'wpseo_twitter-image'         => [ '', 'twitter_image_url' ],
		'wpseo_twitter-image-id'      => [ '', 'twitter_image_id' ],
	];

	public const SEPARATOR_MAPPING = [
		'sc-dash'   => '-',
		'sc-ndash'  => '–',
		'sc-mdash'  => '—',
		'sc-middot' => '·',
		'sc-bull'   => '•',
		'sc-star'   => '*',
		'sc-smstar' => '⋆',
		'sc-pipe'   => '|',
		'sc-tilde'  => '~',
		'sc-laquo'  => '«',
		'sc-raquo'  => '»',
		'sc-lt'     => '>',
		'sc-gt'     => '<',
	];

	/**
	 * Get Yoast meta data for a specific post or term.
	 *
	 * @param int    $id         Post or Term ID.
	 * @param bool   $is_taxonomy Whether the ID is for a taxonomy term.
	 * @param string $type     Type of taxonomy if applicable (default: 'category').
	 * @return array<string, mixed> Yoast meta data.
	 */
	public static function yoast_meta_data( $id, $is_taxonomy = false, $type = 'category' ) {
		if ( $is_taxonomy ) {
			$term_meta = get_option( 'wpseo_taxonomy_meta', [] );
			$term_meta = $term_meta[ $type ][ $id ] ?? [];
			$data      = $term_meta;
		} else {
			$data = get_post_meta( $id );
		}
		$yoast_global_settings = get_option( 'wpseo_titles', [] );

		if ( ! is_wp_error( $data ) ) {
			foreach ( $yoast_global_settings as $key => $value ) {
				// If the key is not already present in $data, add it.
				if ( ! array_key_exists( $key, $data ) ) {
					$data[ $key ] = $value;
				}
			}
		}
		return $data;
	}

	/**
	 * Replace Yoast placeholders with SureRank placeholders in a given value.
	 *
	 * @param string|array<string> $value The value containing placeholders to replace.
	 * @param string|null          $separator Optional separator to replace the %%sep%% placeholder.
	 * @return string The value with placeholders replaced.
	 */
	public static function replace_placeholders( $value, ?string $separator = null ) {
		if ( is_array( $value ) ) {
			$replaced = array_map( static fn( $item) => self::replace_placeholders( $item, $separator ), $value );
			return implode( ', ', $replaced );
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		$placeholders = self::PLACEHOLDERS_MAPPING;

		// If the value contains %%sep%% and a separator is provided, override the default %%sep%% mapping.
		if ( $separator !== null && strpos( $value, '%%sep%%' ) !== false ) {
			$placeholders['%%sep%%'] = self::SEPARATOR_MAPPING[ $separator ] ?? '-';
		}

		// Split the string into parts based on %%...%% patterns.
		preg_match_all( '/%%[^%]+%%|[^%]+/', $value, $matches );
		$result = '';

		foreach ( $matches[0] as $part ) {
			// Check if the part is a placeholder (starts and ends with %%).
			if ( preg_match( '/^%%[^%]+%%$/', $part ) ) {
				// Replace placeholder if it exists in PLACEHOLDERS_MAPPING else SKIP.
				if ( isset( $placeholders[ $part ] ) ) {
					$result .= $placeholders[ $part ];
				}
			} else {
				// Keep the part as is (either non-placeholder text or unmatched placeholder).
				$result .= $part;
			}
		}

		return $result;
	}

	/**
	 * Get term IDs from Yoast taxonomy meta.
	 *
	 * @param array<string, object> $taxonomies_objects Array of taxonomy objects.
	 * @return array{term_ids: array<int>, total_items: int} Array containing term IDs and total count.
	 */
	public static function get_term_ids( $taxonomies_objects ) {
		$taxonomy_meta = get_option( 'wpseo_taxonomy_meta', [] );
		$term_ids      = [];
		$total_terms   = 0;

		if ( empty( $taxonomy_meta ) || ! is_array( $taxonomy_meta ) ) {
			return [
				'term_ids'    => [],
				'total_items' => 0,
			];
		}

		// Extract all term IDs from relevant taxonomies.
		$all_term_ids = [];
		$taxonomies   = [];

		foreach ( $taxonomy_meta as $taxonomy => $terms ) {
			// Ensure the taxonomy is public and in $taxonomies_objects.
			if (
				! isset( $taxonomies_objects[ $taxonomy ] ) ||
				! ( property_exists( $taxonomies_objects[ $taxonomy ], 'public' ) && $taxonomies_objects[ $taxonomy ]->public )
			) {
				continue;
			}

			if ( ! is_array( $terms ) ) {
				continue;
			}

			$taxonomies[] = $taxonomy;
			foreach ( $terms as $term_id => $meta ) {
				$all_term_ids[] = (int) $term_id;
			}
		}

		if ( empty( $all_term_ids ) || empty( $taxonomies ) ) {
			return [
				'term_ids'    => [],
				'total_items' => 0,
			];
		}

		// Use WP_Term_Query to fetch valid terms and filter out migrated ones.
		$term_query = new \WP_Term_Query(
			[
				'taxonomy'   => $taxonomies,
				'include'    => array_unique( $all_term_ids ),
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => 'surerank_migration',
						'compare' => 'NOT EXISTS',
					],
				],
				'fields'     => 'ids',
				'hide_empty' => false,
			]
		);

		if ( ! empty( $term_query->terms ) ) {
			$term_ids    = array_map( 'intval', $term_query->terms );
			$total_terms = count( $term_ids );
		}

		return [
			'term_ids'    => $term_ids,
			'total_items' => $total_terms,
		];
	}
}
