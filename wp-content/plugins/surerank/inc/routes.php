<?php
/**
 * Custom Sitemap Routes
 *
 * This file manages all the rewrite rules and query variable handling
 * for custom sitemap functionality in SureRank.
 *
 * @package SureRank
 * @since 1.0.0
 */

namespace SureRank\Inc;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureRank\Inc\Frontend\Sitemap;
use SureRank\Inc\Traits\Get_Instance;

/**
 * Custom Sitemap Routes
 *
 * This class manages all the rewrite rules and query variable handling
 * for custom sitemap functionality in SureRank.
 *
 * @since 1.0.0
 */
class Routes {

	use Get_Instance;
	/**
	 * Register rewrite rules and query variables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		// Add rewrite rules.
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
	}

	/**
	 * Register custom rewrite rules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_rewrite_rules() {
		$this->sitemap_routes();
	}

	/**
	 * Register custom rewrite rules for the sitemap.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function sitemap_routes() {
		global $wp;
		global $wp_rewrite;

		// Add default rewrite rules.
		add_rewrite_rule( '^' . Sitemap::get_slug() . '$', 'index.php?surerank_sitemap=1', 'top' );
		add_rewrite_rule( '^([a-z]+)-stylesheet\.xsl$', 'index.php?type=$matches[1]', 'top' );
		add_rewrite_rule(
			'^(post|page|attachment|post-tag|author|category|product-category)-sitemap([0-9]+)?\.xml$',
			'index.php?surerank_sitemap=$matches[1]&page=$matches[2]',
			'top'
		);

		add_rewrite_rule(
			'^([a-z0-9_-]+)-sitemap([0-9]+)?\.xml$',
			'index.php?surerank_sitemap=$matches[1]&page=$matches[2]',
			'top'
		);

		$wp->add_query_var( 'surerank_sitemap' );
		$wp->add_query_var( 'type' );
		$wp->add_query_var( 'page' );
	}

}
