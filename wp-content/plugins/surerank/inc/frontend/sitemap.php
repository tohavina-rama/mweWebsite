<?php
/**
 * Common Meta Data
 *
 * This file handles functionality to generate sitemap in frontend.
 *
 * @package surerank
 * @since 0.0.1
 */

namespace SureRank\Inc\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureRank\Inc\Functions\Helper;
use SureRank\Inc\Functions\Settings;
use SureRank\Inc\Schema\Helper as Schema_Helper;
use SureRank\Inc\Sitemap\Utils;
use SureRank\Inc\Traits\Get_Instance;
use WP_Query;

/**
 * Sitemap
 * Handles functionality to generate various types of sitemaps.
 *
 * @since 1.0.0
 */
class Sitemap {

	use Get_Instance;
	/**
	 * Sitemap slug to be used across the class.
	 *
	 * @var string
	 */
	private static $sitemap_slug = 'sitemap_index';

	/**
	 * Constructor
	 *
	 * Sets up the sitemap functionality if XML sitemaps are enabled in settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {

		add_filter(
			'surerank_flush_rewrite_settings',
			[ $this, 'flush_settings' ],
			10,
			1
		);

		if ( ! Settings::get( 'enable_xml_sitemap' ) ) {
			return;
		}

		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_action( 'template_redirect', [ $this, 'template_redirect' ] );
		add_action( 'parse_query', [ $this, 'parse_query' ] );
	}

	/**
	 * Array of settings to flush rewrite rules on update settings
	 *
	 * @param array<string, mixed> $settings Existing settings to flush.
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function flush_settings( $settings ) {
		$settings[] = 'enable_xml_sitemap';
		$settings[] = 'enable_xml_image_sitemap';
		return $settings;
	}

	/**
	 * Returns the sitemap slug.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_slug(): string {
		return self::$sitemap_slug . '.xml';
	}

	/**
	 * Redirects default WordPress sitemap requests to custom sitemap URLs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function template_redirect() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$current_url = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$current_url = explode( '/', $current_url );
		$last_url    = end( $current_url );

		$sitemap = [
			'sitemap.xml',
			'wp-sitemap.xml',
			'index.xml',
		];

		if ( in_array( $last_url, $sitemap, true ) ) {
			wp_safe_redirect( '/' . self::get_slug(), 301 );
			exit;
		}
	}

	/**
	 * Parses custom query variables and triggers sitemap generation.
	 *
	 * @param \WP_Query $query Current query object.
	 * @since 1.0.0
	 * @return void
	 */
	public function parse_query( \WP_Query $query ) {
		if ( ! $query->is_main_query() && ! is_admin() ) {
			return;
		}

		$type  = sanitize_text_field( get_query_var( 'surerank_sitemap' ) );
		$style = sanitize_text_field( get_query_var( 'type' ) );

		if ( ! $type && ! $style ) {
			return;
		}

		if ( $style ) {
			Utils::output_stylesheet( $style );
		}

		$page      = absint( get_query_var( 'page' ) ) ? absint( get_query_var( 'page' ) ) : 1;
		$threshold = apply_filters( 'surerank_sitemap_threshold', 100 );
		// Dynamically handle CPTs.
		if ( post_type_exists( $type ) ) {
			$this->generate_main_sitemap( $type, $page, $threshold );
			return;
		}

		$this->generate_sitemap( $type, $page, $threshold );
	}

	/**
	 * Generates the appropriate sitemap based on the requested type.
	 *
	 * @param string $type Sitemap type requested.
	 * @param int    $page Current page number for paginated sitemaps.
	 * @param int    $threshold Threshold for splitting sitemaps.
	 * @since 1.0.0
	 * @return void
	 */
	public function generate_sitemap( string $type, int $page, $threshold ) {

		$sitemap = [];

		if ( '1' === $type ) {
			$sitemap = $this->generate_index_sitemap( $threshold );
			$this->sitemapindex( $sitemap );
		}

		$this->generate_main_sitemap( $type, $page, $threshold );
	}

	/**
	 * Generates a sitemap for WooCommerce product categories.
	 *
	 * @return array<string, mixed>|array<int, string> List of product category URLs for the sitemap.
	 */
	public function generate_product_cat_sitemap() {
		remove_all_actions( 'parse_query' );
		$args         = [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => 0,
		];
		$product_cats = get_terms( $args );

		$sitemap = [];
		if ( is_array( $product_cats ) ) {
			foreach ( $product_cats as $product_cat ) {
				$sitemap[] = get_term_link( $product_cat->term_id );
			}
		}
		return $sitemap;
	}

	/**
	 * Generates the index sitemap based on content thresholds.
	 *
	 * @param int $threshold Threshold for splitting sitemaps.
	 * @return array<string, mixed>|array<int, string> List of URLs for the index sitemap.
	 */
	public function generate_index_sitemap( int $threshold ) {
		// Fetch default post type counts.
		$total_posts      = $this->get_total_count( 'post' );
		$total_pages      = $this->get_total_count( 'page' );
		$total_categories = $this->get_total_count( 'category' );
		$total_tags       = $this->get_total_count( 'post_tag' );

		// Add default types to the sitemap.
		$sitemap_types = [
			'post'     => $total_posts,
			'page'     => $total_pages,
			'category' => $total_categories,
			'post-tag' => $total_tags,
		];

		// Include WooCommerce product categories if WooCommerce is active.
		if ( Helper::wc_status() ) {
			$total_product_cats                = $this->get_total_count( 'product_cat' );
			$sitemap_types['product-category'] = $total_product_cats;
		}

		// Include custom post types (CPTs).
		$cpts = Helper::get_public_cpts();
		foreach ( $cpts as $cpt ) {
			// Exclude default post types to avoid duplication.
			if ( in_array( $cpt->name, [ 'post', 'page', 'attachment' ], true ) ) {
				continue;
			}

			if ( ! apply_filters( "surerank_sitemap_include_{$cpt->name}", true ) ) {
				continue;
			}

			$total_count                 = $this->get_total_count( $cpt->name );
			$sitemap_types[ $cpt->name ] = $total_count;
		}

		// Get all custom taxonomies.
		$custom_taxonomies = Schema_Helper::get_instance()->get_taxonomies(
			[
				'public'   => true,
				'_builtin' => false,
			]
		);
		foreach ( $custom_taxonomies as $custom_taxonomy ) {
			$total_count                               = $this->get_total_count( $custom_taxonomy['slug'] );
			$sitemap_types[ $custom_taxonomy['slug'] ] = $total_count;
		}

		// Generate the sitemap URLs with last modified date.
		$sitemap = [];
		foreach ( $sitemap_types as $type => $total ) {
			if ( $this->check_noindex( $type, 'check' ) ) {
				continue;
			}

			if ( $total <= 0 ) {
				continue;
			}

			$last_modified = null;
			if ( post_type_exists( $type ) ) {
				$args  = [
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				];
				$query = new WP_Query( $args );
				if ( $query->have_posts() ) {
					if ( $query->posts[0] instanceof \WP_Post ) {
						$last_modified = get_post_modified_time( 'c', false, $query->posts[0]->ID );
					}
				}
				wp_reset_postdata();
			} elseif ( taxonomy_exists( $type ) ) {
				$args = [
					'post_type'      => 'any',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'tax_query'      => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						[
							'taxonomy' => $type,
							'operator' => 'EXISTS',
						],
					],
				];

				$query = new WP_Query( $args );
				if ( $query->have_posts() ) {
					if ( $query->posts[0] instanceof \WP_Post ) {
						$last_modified = get_post_modified_time( 'c', false, $query->posts[0]->ID );
					}
				}
				wp_reset_postdata();
			}

			if ( $total >= $threshold ) {
				$total_sitemaps = ceil( $total / $threshold );
				for ( $i = 1; $i <= $total_sitemaps; $i++ ) {
					$sitemap[] = [
						'link'    => home_url( "{$type}-sitemap{$i}.xml" ),
						'updated' => $last_modified !== null ? esc_html( (string) $last_modified ) : current_time( 'c' ),
					];
				}
			} else {
				$sitemap[] = [
					'link'    => home_url( "{$type}-sitemap.xml" ),
					'updated' => $last_modified ? esc_html( (string) $last_modified ) : current_time( 'c' ),
				];
			}
		}

		return $sitemap;
	}

	/**
	 * Generates the main sitemap for a specific type, page, and offset.
	 *
	 * @param string $type Post type or taxonomy.
	 * @param int    $page Current page number.
	 * @param int    $offset Number of posts to retrieve.
	 * @since 1.0.0
	 * @return void
	 */
	public function generate_main_sitemap( string $type, int $page, int $offset = 1000 ) {
		remove_all_actions( 'parse_query' );
		$sitemap = [];

		// Handle CPTs dynamically.
		if ( post_type_exists( $type ) ) {
			$sitemap = $this->generate_post_sitemap( $type, $page, $offset );
		} elseif ( 'author' === $type ) {
			$sitemap = $this->generate_author_sitemap( $page, $offset );
		} elseif ( 'category' === $type ) {
			$sitemap = $this->generate_category_sitemap( $page, $offset );
		} elseif ( 'post-tag' === $type ) {
			$sitemap = $this->generate_post_tag_sitemap( $page, $offset );
		} elseif ( 'product-category' === $type ) {
			$sitemap = $this->generate_product_category_sitemap( $page, $offset );
		} elseif ( taxonomy_exists( $type ) ) {
			$sitemap = $this->generate_taxonomy_sitemap( $type, $page, $offset );
		}

		do_action( 'surerank_sitemap_generated', $sitemap, $type, $page ); // this action can be used to modify the sitemap data.

		$this->generate_main_sitemap_xml( $sitemap );
	}

	/**
	 * Outputs the sitemap index as XML.
	 *
	 * @param array<string, mixed>|array<int, string> $sitemap Sitemap index data.
	 * @since 1.0.0
	 * @return void
	 */
	public function sitemapindex( array $sitemap ) {
		echo Utils::sitemap_index( $sitemap ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is safely generated in sitemap_index
		exit;
	}

	/**
	 * Outputs the main sitemap as XML.
	 *
	 * @param array<string, mixed>|array<int, string> $sitemap Sitemap data for main sitemap.
	 * @since 1.0.0
	 * @return void
	 */
	public function generate_main_sitemap_xml( array $sitemap ) {
		echo Utils::sitemap_main( $sitemap ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is safely generated in sitemap_main
		exit;
	}

	/**
	 * Get sitemap url
	 *
	 * @return string
	 */
	public function get_sitemap_url() {
		return home_url( self::get_slug() );
	}

	/**
	 * Get total count.
	 *
	 * @param string $type The type to get the total count for.
	 * @return int
	 */
	public function get_total_count( $type ) {

		// check if indexable.
		$no_index = $this->get_noindex_settings();

		if ( in_array( $type, $no_index, true ) ) {
			$total_count = $this->check_noindex( $type, 'count' );
		} else {
			if ( post_type_exists( $type ) ) {
				$total_count = wp_count_posts( $type )->publish ?? 0;
			} else {
				$total_count = wp_count_terms( [ 'taxonomy' => $type ] );
			}
		}

		return $total_count;
	}

	/**
	 * Check if the post is noindex.
	 *
	 * @param int|null    $post_id The post ID.
	 * @param string|null $post_type The post type.
	 * @return bool
	 */
	public function is_noindex( $post_id = null, $post_type = null ) {
		if ( ! $post_id || ! $post_type ) {
			return true;
		}
		return $this->indexable( $post_id, $post_type, 'get_post_meta', 'noindex' );
	}

	/**
	 * Check if the term is noindex.
	 *
	 * @param int|null    $term_id The term ID.
	 * @param string|null $taxonomy The taxonomy.
	 * @return bool
	 */
	public function is_noindex_term( $term_id = null, $taxonomy = null ) {
		if ( ! $term_id || ! $taxonomy ) {
			return true;
		}
		return $this->indexable( $term_id, $taxonomy, 'get_term_meta', 'noindex' );
	}

	/**
	 * Get noindex settings.
	 *
	 * @return array<string, mixed>|array<int, string>
	 */
	public function get_noindex_settings() {
		$settings = Settings::get();
		return $settings['no_index'] ?? [];
	}

	/**
	 * Meta Query Args.
	 *
	 * @param array<string, mixed> $args The arguments to modify.
	 * @return array<string, mixed>|array<int, string>
	 */
	public function meta_query_args( $args ) {
		$args['meta_query'] = [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			[
				'key'     => 'surerank_settings_post_no_index',
				'value'   => 'no',
				'compare' => '=',
			],
		];
		return $args;
	}

	/**
	 * Generates the author sitemap.
	 *
	 * @param int $page Page number.
	 * @param int $offset Number of authors to retrieve.
	 * @return array<string, mixed>|array<int, string>
	 */
	private function generate_author_sitemap( int $page, int $offset ) {
		// Author-based sitemap logic.
		$args = [
			'role__in' => [ 'Administrator', 'Editor', 'Author' ],
			'number'   => $offset,
			'paged'    => $page,
		];

		$authors = get_users( $args );

		$sitemap = [];
		if ( is_array( $authors ) ) {
			foreach ( $authors as $author ) {
				$sitemap[] = [
					'link'    => get_author_posts_url( $author->ID ),
					'updated' => gmdate( 'Y-m-d\TH:i:sP', strtotime( $author->user_registered ) ),
				];
			}
		}

		return $sitemap;
	}

	/**
	 * Generates the category sitemap.
	 *
	 * @param int $page Page number.
	 * @param int $offset Number of categories to retrieve.
	 * @return array<string, mixed>|array<int, string>
	 */
	private function generate_category_sitemap( int $page, int $offset ) {
		return $this->generate_taxonomy_sitemap( 'category', $page, $offset );
	}

	/**
	 * Generates the post-tag sitemap.
	 *
	 * @param int $page Page number.
	 * @param int $offset Number of tags to retrieve.
	 * @return array<string, mixed>|array<int, string>
	 */
	private function generate_post_tag_sitemap( int $page, int $offset ) {
		return $this->generate_taxonomy_sitemap( 'post_tag', $page, $offset );
	}

	/**
	 * Generates the product-category sitemap.
	 *
	 * @param int $page Page number.
	 * @param int $offset Number of product categories to retrieve.
	 * @return array<string, mixed>|array<int, string>
	 */
	private function generate_product_category_sitemap( int $page, int $offset ) {
		return $this->generate_taxonomy_sitemap( 'product_cat', $page, $offset );
	}

	/**
	 * Generates the sitemap for a specific taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $page Page number.
	 * @param int    $offset Number of terms to retrieve.
	 * @return array<string, mixed>|array<int, string>
	 */
	private function generate_taxonomy_sitemap( string $taxonomy, int $page, int $offset ) {
		// Calculate the offset based on the page number.
		$calculated_offset = ( $page - 1 ) * $offset;

		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $offset, // Number of terms per page.
			'offset'     => $calculated_offset, // Skip terms based on page.
		];

		if ( $this->check_noindex( $taxonomy, 'check' ) ) {
			return [];
		}

		$no_index = $this->get_noindex_settings();
		if ( in_array( $taxonomy, $no_index, true ) ) {
			$args = $this->meta_query_args( $args );
		}

		$modif = new WP_Query(
			[
				'taxonomy'  => $taxonomy,
				'showposts' => 1,
			]
		);

		$last_modified = isset( $modif->posts[0] ) && $modif->posts[0] instanceof \WP_Post
			? $modif->posts[0]->post_modified
			: null;

		$last_modified_timestamp = is_string( $last_modified ) ? strtotime( $last_modified ) : null;

		// Filter to modify the arguments for the taxonomy sitemap.
		$args = apply_filters( 'surerank_taxonomy_sitemap_args', $args, $taxonomy );

		$terms = get_terms( $args );

		$sitemap = [];
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_id = $term->term_id ?? null;
				if ( ! $term_id ) {
					continue;
				}

				if ( $this->is_noindex_term( $term_id, $taxonomy ) ) {
					continue;
				}
				$sitemap[] = [
					'link'    => get_term_link( $term_id ),
					'updated' => $last_modified_timestamp ? gmdate( 'Y-m-d\TH:i:sP', $last_modified_timestamp ) : null,
				];
			}
		}

		return $sitemap;
	}

	/**
	 * Generates the post sitemap, including images if enabled.
	 *
	 * @param string $type Post type.
	 * @param int    $page Page number.
	 * @param int    $offset Number of posts to retrieve.
	 * @return array<string, mixed>|array<int, string>
	 */
	private function generate_post_sitemap( string $type, int $page, int $offset ) {

		if ( $this->check_noindex( $type, 'check' ) ) {
			return [];
		}

		$no_index = $this->get_noindex_settings();

		// Post-based sitemap logic.
		$args = [
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => $offset,
			'paged'          => $page,
			'cache_results'  => true,
			'post__not_in'   => apply_filters( 'surerank_exclude_posts_from_sitemap', [] ),
		];

		if ( in_array( $type, $no_index, true ) ) {
			$args = $this->meta_query_args( $args );
		}

		$query = new WP_Query( $args );

		$sitemap = [];
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				if ( $this->is_noindex( (int) get_the_ID(), $type ) ) {
					continue;
				}
				$url_data = [
					'link'        => esc_url( (string) get_permalink() ),
					'updated'     => esc_html( (string) get_the_modified_date( 'c' ) ),
					'images'      => 0,
					'images_data' => [],
				];

				if ( empty( Settings::get( 'enable_xml_image_sitemap' ) ) ) {
					$sitemap[] = $url_data;
					continue;
				}

				$images = Utils::get_images_from_post( (int) get_the_ID() );

				if ( is_array( $images ) && ! empty( $images ) ) {
					$url_data['images']      = count( $images );
					$url_data['images_data'] = array_map(
						static function ( $image_url ) {
							return [
								'link'    => esc_url( $image_url ),
								'updated' => esc_html( (string) get_the_modified_date( 'c' ) ),
							];
						},
						$images
					);
				}

				$sitemap[] = $url_data;
			}
			wp_reset_postdata();
		}
		return $sitemap;
	}

	/**
	 * Helper function to check noindex status.
	 *
	 * @param string $type The type to check against (post type or taxonomy).
	 * @param string $action The action to perform (check, count, or get).
	 * @return bool|int
	 */
	private function check_noindex( $type, $action = 'check' ) {
		$count         = 0;
		$taxonomy_type = str_replace( '-', '_', $type );
		if ( taxonomy_exists( $taxonomy_type ) ) {
			$args = [
				'taxonomy'   => $taxonomy_type,
				'hide_empty' => false,
			];

			$args  = $this->meta_query_args( $args );
			$terms = get_terms( $args );
			$count = is_array( $terms ) ? count( $terms ) : 0;

			if ( $count === 0 ) {

				if ( $action === 'count' ) {
					return 0;
				}

				$no_index = $this->get_noindex_settings();
				if ( in_array( $taxonomy_type, $no_index, true ) ) {
					return true;
				}
			}
		} elseif ( post_type_exists( $type ) ) {
			$args = [
				'post_type'      => $type,
				'posts_per_page' => -1,
			];

			$args  = $this->meta_query_args( $args );
			$query = new WP_Query( $args );
			$count = $query->found_posts;
			if ( $count === 0 ) {

				if ( $action === 'count' ) {
					return 0;
				}

				$no_index = $this->get_noindex_settings();
				if ( in_array( $type, $no_index, true ) ) {
					return true;
				}
			}
		}
		if ( $count > 0 ) {
			if ( $action === 'count' ) {
				return $count;
			}
			return false;
		}
		return false;
	}

	/**
	 * Helper function to check noindex status.
	 *
	 * @param mixed  $id The ID to check (post ID or term ID).
	 * @param string $type The type to check against (post type or taxonomy).
	 * @param string $meta_key The meta key to look up.
	 * @param string $settings_key The settings key to fall back to.
	 * @return bool
	 */
	private function indexable( $id, $type, $meta_key, $settings_key ) {
		if ( ! $id ) {
			return true;
		}

		$meta = $meta_key === 'get_post_meta'
			? get_post_meta( $id, 'surerank_settings_post_no_index', true )
			: get_term_meta( $id, 'surerank_settings_post_no_index', true );

		if ( $meta === 'yes' ) {
			return true;
		}

		if ( $meta === 'no' ) {
			return false;
		}

		$no_index = $this->get_noindex_settings();

		if ( in_array( $type, $no_index, true ) ) {
			return true;
		}

		return false;
	}
}
