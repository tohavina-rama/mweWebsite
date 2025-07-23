<?php
/**
 * Validator
 *
 * This file handles the validation of schema rules for determining visibility
 * based on specified conditions.
 *
 * @package surerank
 * @since 1.0.0
 */

namespace SureRank\Inc\Schema;

/**
 * Validator class
 *
 * Handles the validation of schema rules for determining visibility
 * based on specified conditions.
 */
class Validator {

	/**
	 * Validate Schema Rules
	 *
	 * Determines if the schema should be displayed based on `show_on`
	 * and `not_show_on` rules.
	 *
	 * @param array<string, mixed> $schema Schema data.
	 * @param string               $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @param int                  $post_id Post ID is from api request.
	 * @param bool                 $is_taxonomy Whether the post is a taxonomy.
	 * @return bool True if schema should be displayed, false otherwise.
	 */
	public static function validate_schema_rules( $schema, $post_type = '', $post_id = 0, $is_taxonomy = false ) {

		// if schema has parent key, and it is true, we will return true, because we are using post meta data for schema now.
		if ( isset( $schema['parent'] ) && $schema['parent'] ) {
			return true;
		}

		$show_on_rules        = $schema['show_on']['rules'] ?? [];
		$show_on_specific     = $schema['show_on']['specific'] ?? [];
		$not_show_on_rules    = $schema['not_show_on']['rules'] ?? [];
		$not_show_on_specific = $schema['not_show_on']['specific'] ?? [];

		$show_on_match     = false;
		$not_show_on_match = false;

		if ( empty( $show_on_rules ) && empty( $show_on_specific ) && empty( $not_show_on_rules ) && empty( $not_show_on_specific ) ) {
			return false;
		}

		if ( ! empty( $show_on_rules ) || ! empty( $show_on_specific ) ) {
			$show_on_match = self::evaluate_rules( $show_on_rules, $post_type, $is_taxonomy, $post_id ) ||
							self::evaluate_specifics( $show_on_specific, $post_type, $post_id );
		}

		if ( ! empty( $not_show_on_rules ) || ! empty( $not_show_on_specific ) ) {
			$not_show_on_match = self::evaluate_rules( $not_show_on_rules, $post_type, $is_taxonomy, $post_id ) ||
								self::evaluate_specifics( $not_show_on_specific, $post_type, $post_id );

		}

		// - `show_on` must match (true).
		// - `not_show_on` must NOT match (false).
		return $show_on_match && ! $not_show_on_match;
	}

	/**
	 * Evaluate Rules
	 *
	 * Evaluates an array of rules to check if any match the current context.
	 *
	 * @param array<string, mixed> $rules Rules to evaluate.
	 * @param string               $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @param bool                 $is_taxonomy Whether the post is a taxonomy.
	 * @param int                  $post_id Post ID is from api request.
	 * @return bool True if a rule matches, false otherwise.
	 */
	private static function evaluate_rules( $rules, $post_type, $is_taxonomy = false, $post_id = 0 ) {
		if ( empty( $rules ) ) {
			return false;
		}

		foreach ( $rules as $rule ) {
			if ( self::matches_current_context( $rule, $post_type, $is_taxonomy, $post_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate Specifics
	 *
	 * Evaluates specific items to determine if any match the current context.
	 *
	 * @param array<string, mixed> $specifics Specific items to evaluate.
	 * @param string               $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @param int                  $post_id Post ID is from api request.
	 * @return bool True if a specific item matches, false otherwise.
	 */
	private static function evaluate_specifics( $specifics, $post_type, $post_id ) {
		if ( empty( $specifics ) ) {
			return false;
		}

		foreach ( $specifics as $specific ) {
			if ( self::matches_specific_item( $specific, $post_type, $post_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if Rule Matches Current Context
	 *
	 * Evaluates a single rule to determine if it matches the current WordPress context.
	 *
	 * @param string $rule Rule to check.
	 * @param string $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @param bool   $is_taxonomy Whether the post is a taxonomy.
	 * @param int    $post_id Post ID is from api request.
	 * @return bool True if the rule matches, false otherwise.
	 */
	private static function matches_current_context( $rule, $post_type, $is_taxonomy = false, $post_id = 0 ) {
		$rule_parts = explode( '|', $rule );

		switch ( $rule_parts[0] ) {
			case 'basic-global':
				return true; // Always true for global rule.
			case 'basic-singulars':
				return is_singular() || ! $is_taxonomy;
			case 'basic-archives':
				return is_archive() || $is_taxonomy;
			case 'special-404':
				return is_404();
			case 'special-search':
				return is_search();
			case 'special-blog':
				return is_home();
			case 'special-front':
				return is_front_page() || $is_taxonomy;
			case 'special-date':
				return is_date() || $is_taxonomy;
			case 'special-author':
				return is_author() || $is_taxonomy;
			case 'post':
				return self::handle_post_type_rules( $rule_parts, $post_type, 'post' );
			case 'page':
				return self::handle_page_rules( $rule_parts, $post_type );
			case 'product-type':
				return self::handle_product_type_rules( $rule_parts, $post_type, $post_id );
			case 'product':
				return self::handle_product_rules( $rule_parts, $post_type );
			default:
				if ( post_type_exists( $rule_parts[0] ) ) {
					return self::handle_custom_post_type_rules( $rule_parts, $post_type );
				}
				return false;
		}
	}

	/**
	 * Handle Product Type Rules
	 *
	 * Evaluate product type-specific rules.
	 *
	 * @param array<string, mixed>|array<int, string> $rule_parts Rule parts array.
	 * @param string                                  $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @param int                                     $post_id Post ID is from api request.
	 * @return bool True if matches, false otherwise.
	 */
	private static function handle_product_type_rules( $rule_parts, $post_type, $post_id ) {

		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		if ( 'product' !== $post_type && ! is_singular( 'product' ) && ! $post_id ) {
			return false;
		}

		$product_type = $rule_parts[1] ?? '';
		if ( empty( $product_type ) ) {
			return false;
		}

		$product = null;

		if ( is_singular( 'product' ) ) {
			global $post;
			$product_id = $post->ID;
		} else {
			$product_id = get_the_ID();
			$product_id = $product_id ? $product_id : $post_id;
		}

		$product = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product ) {
			return false;
		}

		$product_type = $product->get_type();

		if ( $product_type === $rule_parts[1] ) {
			return true;
		}

		return false;
	}

	/**
	 * Handle Custom Post Type Rules
	 *
	 * Evaluate custom post type-specific rules.
	 *
	 * @param array<string, mixed>|array<int, string> $rule_parts Rule parts array.
	 * @param string                                  $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @return bool True if matches, false otherwise.
	 */
	private static function handle_custom_post_type_rules( $rule_parts, $post_type ) {
		return self::handle_post_type_rules( $rule_parts, $post_type, $rule_parts[0] );
	}

	/**
	 * Handle Page Rules
	 *
	 * Evaluate page-specific rules.
	 *
	 * @param array<string, mixed>|array<int, string> $rule_parts Rule parts array.
	 * @param string                                  $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @return bool True if matches, false otherwise.
	 */
	private static function handle_page_rules( $rule_parts, $post_type ) {
		switch ( $rule_parts[1] ?? '' ) {
			case 'all':
				return is_page() || 'page' === $post_type;
			case 'front':
				return is_front_page();
			default:
				return false;
		}
	}

	/**
	 * Handle Product Rules
	 *
	 * Evaluate product-specific rules.
	 *
	 * @param array<string, mixed>|array<int, string> $rule_parts Rule parts array.
	 * @param string                                  $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @return bool True if matches, false otherwise.
	 */
	private static function handle_product_rules( $rule_parts, $post_type ) {
		return self::handle_post_type_rules( $rule_parts, $post_type, 'product' );
	}

	/**
	 * Handle Post Type Rules
	 *
	 * Evaluate post type-specific rules.
	 *
	 * @param array<string, mixed>|array<int, string> $rule_parts Rule parts array.
	 * @param string                                  $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @param string                                  $default_type Default post type (e.g., 'product', 'post', 'custom_post').
	 * @return bool True if matches, false otherwise.
	 */
	private static function handle_post_type_rules( $rule_parts, $post_type, $default_type ) {
		switch ( $rule_parts[1] ?? '' ) {
			case 'all':
				if ( isset( $rule_parts[2] ) ) {
					switch ( $rule_parts[2] ) {
						case 'archive':
							return is_post_type_archive( $default_type );
						case 'taxarchive':
							$taxonomy = $rule_parts[3] ?? '';
							if ( 'category' === $taxonomy && 'post' !== $default_type ) {
								return is_category();
							}
							if ( 'post_tag' === $taxonomy && 'post' !== $default_type ) {
								return is_tag();
							}
							if ( is_tax( $taxonomy ) ) {
								return true;
							}
							return $post_type === $taxonomy;
					}
				}
				return is_singular( $default_type ) || $post_type === $default_type;
			case 'archive':
				return is_post_type_archive( $default_type );
			default:
				return false;
		}
	}

	/**
	 * Check if Specific Item Matches
	 *
	 * Determines if a specific item (e.g., post ID or product ID) matches the current context.
	 *
	 * @param string $specific Specific item (e.g., post ID).
	 * @param string $post_type Post type is the post_type we are getting from API request to show in settings.
	 * @param int    $post_id Post ID is from api request.
	 * @return bool True if the specific item matches, false otherwise.
	 */
	private static function matches_specific_item( $specific, $post_type, $post_id ) {
		global $post;

		$post_type_array = [ 'post', 'page', 'product' ];
		$specific_parts  = explode( '-', $specific );

		if ( count( $specific_parts ) < 2 ) {
			return false;
		}

		$type = $specific_parts[0];
		$id   = (int) $specific_parts[1];

		if ( in_array( $type, $post_type_array, true ) ) {
			if ( isset( $post ) ) {
				return (int) $id === $post->ID;
			}
			if ( ! empty( $post_id ) ) {
				return (int) $id === $post_id;
			}
		}

		if ( 'tax' === $type ) {
			// if type is tax, and if we have a second part = single, we are checking if the post has the term.
			if ( isset( $specific_parts[2] ) && 'single' === $specific_parts[2] ) {

				$term = get_term( $id );

				if ( ! $term ) {
					return false;
				}

				if ( isset( $term->taxonomy ) && isset( $term->term_id ) ) {
					$post_check_id = is_singular() ? $post->ID : $post_id;
					if ( $post_check_id && has_term( $id, $term->taxonomy, $post_check_id ) ) {
						return (int) $id === $term->term_id;
					}
				}
				return false;
			}

			// here we are checking if the post is a taxonomy with the id. if there is no queried object we will use $post_id.
			if ( is_tax() || is_category() || is_tag() ) {
				$queried_object = get_queried_object();
				if ( $queried_object && isset( $queried_object->term_id ) ) {
					return (int) $id === (int) $queried_object->term_id;
				}
			}

			// here we are returning true if the id is the same as the post id. for example tax-25, tax is already checked above, and $id we are checking below.
			if ( $id === $post_id ) {
				return true;
			}
		}

		return false;
	}

}
