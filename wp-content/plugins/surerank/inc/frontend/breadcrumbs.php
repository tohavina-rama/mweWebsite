<?php
/**
 * Breadcrumb.php
 *
 * This file handles functionality for all Breadcrumbs.
 *
 * @package surerank
 */

namespace SureRank\Inc\Frontend;

use SureRank\Inc\Traits\Get_Instance;
use WP_Post;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumbs
 *
 * This class handles functionality for all Breadcrumbs.
 */
class Breadcrumbs {

	use Get_Instance;

	/**
	 * Breadcrumb trail.
	 *
	 * @var array<array{name: string, link: string}>
	 */
	private $crumbs = [];

	/**
	 * Get the breadcrumb trail.
	 *
	 * @return array<array<string, mixed>>
	 */
	public function get_crumbs() {
		if ( empty( $this->crumbs ) ) {
			$this->generate();
		}

		return $this->crumbs;
	}

	/**
	 * Generate the breadcrumb trail.
	 *
	 * @return void
	 */
	private function generate() {
		$this->maybe_add_home_crumb();

		if ( is_category() ) {
			$this->add_category_crumbs();
		} elseif ( is_tag() ) {
			$this->add_tag_crumbs();
		} elseif ( is_tax() ) {
			$this->add_tax_crumbs();
		} elseif ( is_singular() ) {
			$this->add_singular_crumbs();
		} elseif ( is_author() ) {
			$this->add_author_crumbs();
		} elseif ( is_date() ) {
			$this->add_date_crumbs();
		} elseif ( is_search() ) {
			$this->add_crumb( 'Search results for: ' . get_search_query(), '' );
		} elseif ( is_404() ) {
			$this->add_crumb( '404 Not Found', '' );
		}
	}

	/**
	 * Add category breadcrumbs.
	 *
	 * @return void
	 */
	private function add_category_crumbs() {
		$category = get_queried_object();

		if ( $category instanceof WP_Term ) {
			$this->add_term_hierarchy_crumbs( $category, 'category' );
		}
	}

	/**
	 * Add tag breadcrumbs.
	 *
	 * @return void
	 */
	private function add_tag_crumbs() {
		$tag = get_queried_object();

		if ( $tag instanceof WP_Term ) {
			$this->add_crumb( $tag->name, get_tag_link( $tag ) );
		}
	}

	/**
	 * Add taxonomy breadcrumbs.
	 *
	 * @return void
	 */
	private function add_tax_crumbs() {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$this->add_term_hierarchy_crumbs( $term, $term->taxonomy );
		}
	}

	/**
	 * Add singular (post or page) breadcrumbs.
	 *
	 * @return void
	 */
	private function add_singular_crumbs() {
		global $post;
		if ( $post instanceof WP_Post ) {

			$post_type = $post->post_type;

			if ( ! $post_type ) {
				return;
			}

			$post_type_obj = get_post_type_object( $post->post_type );

			if ( 'product' === $post->post_type ) {
				if ( function_exists( 'wc_get_page_id' ) && get_permalink( wc_get_page_id( 'shop' ) ) ) {
					$this->add_crumb( 'Shop', get_permalink( wc_get_page_id( 'shop' ) ) );
				}

				$this->add_product_terms_crumbs( $post );
			} else {
				if ( $post_type_obj && ! in_array( $post_type, [ 'post', 'page', 'product' ], true ) ) {
					$archive_link = get_post_type_archive_link( $post_type );
					if ( ! empty( $archive_link ) ) {
						$this->add_crumb( $post_type_obj->labels->singular_name, $archive_link );
					}
				}

				if ( is_post_type_hierarchical( $post_type ) ) {
					$this->add_post_hierarchy_crumbs( $post );
				} else {
					$taxonomy = $this->get_primary_taxonomy( $post_type );

					if ( ! empty( $taxonomy ) ) {
						$terms = get_the_terms( $post->ID, $taxonomy );

						if ( is_array( $terms ) && ! empty( $terms ) ) {
							$primary_term = $terms[0];
							$this->add_term_hierarchy_crumbs( $primary_term, $taxonomy );
						}
					}
				}
			}

			// add the post/product title.
			$this->add_crumb( get_the_title( $post ), get_permalink( $post ) );
		}
	}

	/**
	 * Add breadcrumbs for a term and its ancestors.
	 *
	 * @param WP_Term $term Term object.
	 * @param string  $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	private function add_term_hierarchy_crumbs( WP_Term $term, string $taxonomy ) {
		$ancestors = $this->get_term_ancestors( $term, $taxonomy );

		foreach ( $ancestors as $ancestor ) {
			$link = $this->get_term_link( $ancestor, $taxonomy );
			if ( $link ) {
				$this->add_crumb( $ancestor->name, $link );
			}
		}

		if ( empty( $ancestors ) || end( $ancestors )->term_id !== $term->term_id ) {
			$link = $this->get_term_link( $term, $taxonomy );
			if ( $link ) {
				$this->add_crumb( $term->name, $link );
			}
		}
	}

	/**
	 * Get a term's ancestors in order from root to direct parent.
	 *
	 * @param WP_Term $term Term object.
	 * @param string  $taxonomy Taxonomy slug.
	 * @return array<int, \WP_Term> Array of WP_Term objects.
	 */
	private function get_term_ancestors( WP_Term $term, string $taxonomy ) {
		$ancestors     = [];
		$original_term = $term;

		while ( $term->parent ) {
			$term = get_term( $term->parent, $taxonomy );

			if ( ! ( $term instanceof WP_Term ) ) {
				break;
			}

			array_unshift( $ancestors, $term );
		}

		return $ancestors;
	}

	/**
	 * Get term link safely.
	 *
	 * @param WP_Term $term Term object.
	 * @param string  $taxonomy Taxonomy slug.
	 * @return string|null Term link or null if error.
	 */
	private function get_term_link( WP_Term $term, string $taxonomy ): ?string {
		$link = get_term_link( $term->term_id, $taxonomy );
		return is_wp_error( $link ) ? null : $link;
	}

	/**
	 * Get the primary taxonomy for a given post type.
	 *
	 * @param string $post_type The post type slug.
	 * @return string|null The taxonomy slug or null if not found.
	 */
	private function get_primary_taxonomy( string $post_type ): ?string {
		$taxonomies = get_object_taxonomies( $post_type, 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_obj = get_taxonomy( $taxonomy );

			if ( $taxonomy_obj && $taxonomy_obj->hierarchical ) {
				return $taxonomy;
			}
		}

		return $taxonomies[0] ?? null;
	}

	/**
	 * Add breadcrumbs for hierarchical post types like Pages.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	private function add_post_hierarchy_crumbs( WP_Post $post ) {
		$ancestors = $this->get_post_ancestors( $post );

		foreach ( $ancestors as $ancestor ) {
			if ( get_permalink( $ancestor ) ) {
				$this->add_crumb( get_the_title( $ancestor ), get_permalink( $ancestor ) );
			}
		}
	}

	/**
	 * Get a post's ancestors in order from root to direct parent.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, \WP_Post> Array of WP_Post objects.
	 */
	private function get_post_ancestors( WP_Post $post ) {
		$parents       = [];
		$original_post = $post;

		while ( $post->post_parent ) {
			$post = get_post( $post->post_parent );
			if ( $post instanceof WP_Post ) {
				array_unshift( $parents, $post );
			} else {
				break;
			}
		}

		return $parents;
	}

	/**
	 * Add product category breadcrumbs.
	 *
	 * @param WP_Post $post Product post object.
	 * @return void
	 */
	private function add_product_terms_crumbs( WP_Post $post ) {
		$term_name  = apply_filters( 'surerank_product_breadcrumbs_term_name', 'product_cat' );
		$categories = get_the_terms( $post->ID, $term_name );

		if ( is_array( $categories ) && ! empty( $categories ) ) {
			$primary_category = $categories[0];
			$this->add_term_hierarchy_crumbs( $primary_category, $term_name );
		}
	}

	/**
	 * Add author breadcrumbs.
	 *
	 * @return void
	 */
	private function add_author_crumbs() {
		$author = get_queried_object();

		if ( $author instanceof WP_User ) {
			$this->add_crumb( 'Author: ' . $author->display_name, get_author_posts_url( $author->ID ) );
		}
	}

	/**
	 * Add date breadcrumbs.
	 *
	 * @return void
	 */
	private function add_date_crumbs() {
		if ( is_year() ) {
			$year = (string) get_the_date( 'Y' );
			$this->add_crumb( $year, (string) get_year_link( intval( $year ) ) );
		}
		if ( is_month() ) {
			$year  = get_the_date( 'Y' );
			$month = get_the_date( 'm' );
			$this->add_crumb( (string) get_the_date( 'F Y' ), (string) get_month_link( intval( $year ), intval( $month ) ) );
		}
		if ( is_day() ) {
			$year  = get_the_date( 'Y' );
			$month = get_the_date( 'm' );
			$day   = get_the_date( 'd' );
			$this->add_crumb( (string) get_the_date( 'j F Y' ), (string) get_day_link( intval( $year ), intval( $month ), intval( $day ) ) );
		}
	}

	/**
	 * Add an item to the breadcrumb trail.
	 *
	 * @param string $name Name of the breadcrumb.
	 * @param string $link URL for the breadcrumb.
	 * @return void
	 */
	private function add_crumb( string $name, string $link = '' ) {

		if ( empty( $name ) ) {
			return;
		}

		$this->crumbs[] = [
			'name' => esc_html( $name ),
			'link' => esc_url( $link ),
		];
	}

	/**
	 * Add a home breadcrumb.
	 *
	 * @return void
	 */
	private function maybe_add_home_crumb() {
		$home_name = apply_filters( 'surerank_home_breadcrumb_name', 'Home' );
		$this->add_crumb( $home_name, home_url() );
	}
}
