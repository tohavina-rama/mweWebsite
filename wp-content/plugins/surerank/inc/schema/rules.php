<?php
/**
 * Display Rules
 *
 * This file handles functionality for all Rules.
 *
 * @package surerank
 * @since 1.0.0
 */

namespace SureRank\Inc\Schema;

use SureRank\Inc\Traits\Get_Instance;

/**
 * Rules
 *
 * This class handles the logic for display rules and location selections.
 *
 * @since 1.0.0
 */
class Rules {
	use Get_Instance;

	/**
	 * Get Location Selections
	 *
	 * Retrieves the options for location rules based on post types and taxonomies.
	 *
	 * @param mixed $consider_type Consider type (single or archive) for specific options.
	 * @return array<string, mixed> The array of location selection options.
	 * @since 1.0.0
	 */
	public static function get_schema_rules_selections( $consider_type = false ) {
		$args = [
			'public'   => true,
			'_builtin' => true,
		];

		$post_types = get_post_types( $args, 'objects' );
		$post_types = array_filter( $post_types, static fn( $pt ) => $pt instanceof \WP_Post_Type );
		unset( $post_types['attachment'] );

		$args['_builtin'] = false;
		$custom_post_type = get_post_types( $args, 'objects' );
		$custom_post_type = array_filter( $custom_post_type, static fn( $pt ) => $pt instanceof \WP_Post_Type );

		$post_types = apply_filters( 'surerank_location_rule_post_types', array_merge( $post_types, $custom_post_type ) );

		$special_pages = [
			'special-404'    => __( '404 Page', 'surerank' ),
			'special-search' => __( 'Search Page', 'surerank' ),
			'special-blog'   => __( 'Blog / Posts Page', 'surerank' ),
			'special-front'  => __( 'Front Page', 'surerank' ),
			'special-date'   => __( 'Date Archive', 'surerank' ),
			'special-author' => __( 'Author Archive', 'surerank' ),
		];

		if ( class_exists( 'WooCommerce' ) ) {
			$special_pages['special-woo-shop'] = __( 'WooCommerce Shop Page', 'surerank' );
		}

		if ( 'single' === $consider_type ) {
			$global_val = [
				'basic-global'    => __( 'Entire Website', 'surerank' ),
				'basic-singulars' => __( 'All Singulars', 'surerank' ),
			];
		} elseif ( 'archive' === $consider_type ) {
			$global_val = [
				'basic-global'   => __( 'Entire Website', 'surerank' ),
				'basic-archives' => __( 'All Archives', 'surerank' ),
			];
		} else {
			$global_val = [
				'basic-global'    => __( 'Entire Website', 'surerank' ),
				'basic-singulars' => __( 'All Singulars', 'surerank' ),
				'basic-archives'  => __( 'All Archives', 'surerank' ),
			];
		}

		if ( 'single' === $consider_type ) {
			$selection_options = [
				'basic' => [
					'label' => __( 'Basic', 'surerank' ),
					'value' => $global_val,
				],
			];
		} else {
			$selection_options = [
				'basic'         => [
					'label' => __( 'Basic', 'surerank' ),
					'value' => $global_val,
				],
				'special-pages' => [
					'label' => __( 'Special Pages', 'surerank' ),
					'value' => $special_pages,
				],
			];
		}

		$args = [
			'public' => true,
		];

		if ( class_exists( 'WooCommerce' ) ) {
			$product_types = wc_get_product_types();

			$product_type_options = [];
			foreach ( $product_types as $type_key => $type_label ) {
				$product_type_options[ 'product-type|' . $type_key ] = $type_label;
			}

			if ( ! empty( $product_type_options ) ) {
				$selection_options['product-types'] = [
					'label' => __( 'Product Types', 'surerank' ),
					'value' => $product_type_options,
				];
			}
		}

		$taxonomies = get_taxonomies( $args, 'objects' );
		$taxonomies = array_filter( $taxonomies, static fn( $tax ) => $tax instanceof \WP_Taxonomy );

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				foreach ( $post_types as $post_type ) {
					$post_opt = self::get_post_target_rule_options( $post_type, $taxonomy, $consider_type );

					if ( isset( $selection_options[ $post_opt['post_key'] ] ) ) {
						if ( ! empty( $post_opt['value'] ) && is_array( $post_opt['value'] ) ) {
							foreach ( $post_opt['value'] as $key => $value ) {
								if ( ! in_array( $value, $selection_options[ $post_opt['post_key'] ]['value'], true ) ) {
									$selection_options[ $post_opt['post_key'] ]['value'][ $key ] = $value;
								}
							}
						}
					} else {
						$selection_options[ $post_opt['post_key'] ] = [
							'label' => $post_opt['label'],
							'value' => $post_opt['value'],
						];
					}
				}
			}
		}

		$selection_options['specific-target'] = [
			'label' => __( 'Specific Target', 'surerank' ),
			'value' => [
				'specifics' => __( 'Specific Pages / Posts / Taxonomies, etc.', 'surerank' ),
			],
		];

		return apply_filters( 'surerank_display_on_list', $selection_options );
	}

	/**
	 * Get target rules for generating the markup for rule selector.
	 *
	 * @since  1.0.0
	 *
	 * @param object $post_type Post type parameter.
	 * @param object $taxonomy Taxonomy for creating the target rule markup.
	 * @param mixed  $consider_type Consider type for dealing with rule options.
	 * @return array<string, mixed>
	 */
	public static function get_post_target_rule_options( $post_type, $taxonomy, $consider_type = false ) {
		if ( ! $post_type instanceof \WP_Post_Type || ! $taxonomy instanceof \WP_Taxonomy ) {
			return [];
		}

		$post_key    = str_replace( ' ', '-', strtolower( $post_type->label ) );
		$post_label  = ucwords( $post_type->label );
		$post_name   = $post_type->name;
		$post_option = [];

		if ( 'archive' !== $consider_type ) {
			/* translators: %s: Post type label */
			$all_posts                          = sprintf( __( 'All %s', 'surerank' ), $post_label );
			$post_option[ $post_name . '|all' ] = $all_posts;
		}

		if ( 'pages' !== $post_key && 'single' !== $consider_type ) {
			/* translators: %s: Post type label */
			$all_archive                                = sprintf( __( 'All %s Archive', 'surerank' ), $post_label );
			$post_option[ $post_name . '|all|archive' ] = $all_archive;
		}

		if ( 'single' !== $consider_type ) {
			if ( in_array( $post_type->name, $taxonomy->object_type, true ) ) {
				$tax_label = ucwords( $taxonomy->label );
				$tax_name  = $taxonomy->name;
				/* translators: %s: Taxonomy label */
				$tax_archive = sprintf( __( 'All %s Archive', 'surerank' ), $tax_label );

				$post_option[ $post_name . '|all|taxarchive|' . $tax_name ] = $tax_archive;
			}
		}

		return [
			'post_key' => $post_key,
			'label'    => $post_label,
			'value'    => $post_option,
		];
	}
}
