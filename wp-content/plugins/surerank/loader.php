<?php
/**
 * Loader.
 *
 * @package surerank
 * @since 0.0.1
 */

namespace SureRank;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureRank\Inc\Admin\Attachment;
use SureRank\Inc\Admin\Dashboard;
use SureRank\Inc\Admin\Onboarding;
use SureRank\Inc\Admin\Seo_Bar;
use SureRank\Inc\Admin\Seo_Popup;
use SureRank\Inc\Admin\Update_Timestamp;
use SureRank\Inc\Ajax\Ajax;
use SureRank\Inc\Analytics\Analytics;
use SureRank\Inc\Analyzer\PostAnalyzer;
use SureRank\Inc\Analyzer\TermAnalyzer;
use SureRank\Inc\API\Analyzer;
use SureRank\Inc\API\Api_Init;
use SureRank\Inc\Frontend\Archives;
use SureRank\Inc\Frontend\Canonical;
use SureRank\Inc\Frontend\Common;
use SureRank\Inc\Frontend\Crawl_Optimization;
use SureRank\Inc\Frontend\Facebook;
use SureRank\Inc\Frontend\Feed;
use SureRank\Inc\Frontend\Meta_Data;
use SureRank\Inc\Frontend\Product;
use SureRank\Inc\Frontend\Robots;
use SureRank\Inc\Frontend\Seo_Popup as Seo_Popup_Frontend;
use SureRank\Inc\Frontend\Single;
use SureRank\Inc\Frontend\Sitemap;
use SureRank\Inc\Frontend\Special_Page;
use SureRank\Inc\Frontend\Taxonomy;
use SureRank\Inc\Frontend\Title;
use SureRank\Inc\Frontend\Twitter;
use SureRank\Inc\Functions\Defaults;
use SureRank\Inc\Functions\Get;
use SureRank\Inc\Functions\Helper;
use SureRank\Inc\Functions\Update;
use SureRank\Inc\GoogleSearchConsole\Auth;
use SureRank\Inc\Lib\Surerank_Nps_Survey;
use SureRank\Inc\Nps_Notice;
use SureRank\Inc\Routes;
use SureRank\Inc\Schema\Schemas;
use SureRank\Inc\ThirdPartyPlugins\Bricks;
use SureRank\Inc\ThirdPartyPlugins\CartFlows;
use SureRank\Inc\ThirdPartyPlugins\Elementor;

/**
 * Plugin_Loader
 *
 * @since 1.0.0
 */
class Loader {

	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class Instance.
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		spl_autoload_register( [ $this, 'autoload' ] );
		add_action( 'shutdown', [ $this, 'shutdown' ] );

		add_action( 'init', [ $this, 'setup' ], 999 );
		add_action( 'init', [ $this, 'flush_rules' ], 999 );
		add_action( 'init', [ $this, 'load_textdomain' ], 10 );
		add_action( 'plugins_loaded', [ $this, 'load_routes' ], 10 );

		register_activation_hook( SURERANK_FILE, [ $this, 'activation' ] );
		register_deactivation_hook( SURERANK_FILE, [ $this, 'deactivation' ] );
		add_filter( 'plugin_row_meta', [ $this, 'add_meta_links' ], 10, 2 );
	}

	/**
	 * Enqueue required classes after plugins loaded.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function setup() {
		Defaults::get_instance();
		Schemas::get_instance();
		Seo_Bar::get_instance();
		Attachment::get_instance();
		Crawl_Optimization::get_instance();
		Analyzer::get_instance();
		CartFlows::get_instance();
		PostAnalyzer::get_instance();
		TermAnalyzer::get_instance();
		Api_Init::get_instance();
		Auth::get_instance();

		if ( is_admin() ) {
			Seo_Popup::get_instance();
			Update_Timestamp::get_instance();
			Dashboard::get_instance();
			Onboarding::get_instance();
			if ( class_exists( 'SureRank\Inc\Lib\Surerank_Nps_Survey' ) && ! apply_filters( 'surerank_disable_nps_survey', false ) ) {
				Surerank_Nps_Survey::get_instance();
				Nps_Notice::get_instance();
			}
			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				Elementor::get_instance();
			}
			Ajax::get_instance();
		} else {
			Single::get_instance();
			Product::get_instance();
			Taxonomy::get_instance();
			Title::get_instance();
			Canonical::get_instance();
			Common::get_instance();
			Robots::get_instance();
			Facebook::get_instance();
			Twitter::get_instance();
			Special_Page::get_instance();
			Feed::get_instance();
			Seo_Popup_Frontend::get_instance();
			Meta_Data::get_instance();
			Sitemap::get_instance();
			Archives::get_instance();
			if ( defined( 'BRICKS_VERSION' ) ) {
				Bricks::get_instance();
			}

			/**
			 * Commenting this since we will deal with bricks in the next release.
			 * if ( defined( 'BRICKS_VERSION' ) ) {
			 * Seo_Popup::get_instance();
			 * Bricks::get_instance();
			 * }
			 */

		}

		if ( class_exists( 'SureRank\Inc\Lib\Surerank_Nps_Survey' ) && ! apply_filters( 'surerank_disable_nps_survey', false ) ) {
			Surerank_Nps_Survey::get_instance();
			Nps_Notice::get_instance();
		}
	}

	/**
	 * Load routes.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function load_routes() {
		Routes::get_instance();
		Analytics::get_instance();
	}

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class class name.
	 * @since 1.0.0
	 * @return void
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
			return;
		}

		$class_to_load = $class;

		$filename = preg_replace(
			[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
			[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
			$class_to_load
		);

		if ( is_string( $filename ) ) {
			$filename = strtolower( $filename );

			$file = SURERANK_DIR . $filename . '.php';

			// if the file readable, include it.
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Load Plugin Text Domain.
	 * This will load the translation textdomain depending on the file priorities.
	 *      1. Global Languages /wp-content/languages/surerank/ folder
	 *      2. Local directory /wp-content/plugins/surerank/languages/ folder
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		// Default languages directory.
		$lang_dir = SURERANK_DIR . 'languages/';

		/**
		 * Filters the languages directory path to use for plugin.
		 *
		 * @param string $lang_dir The languages directory path.
		 */
		$lang_dir = apply_filters( 'surerank_languages_directory', $lang_dir );

		$get_locale = get_user_locale();

		$locale = apply_filters( 'plugin_locale', $get_locale, 'surerank' ); //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- wordpress hook
		$mofile = sprintf( '%1$s-%2$s.mo', 'surerank', $locale );

		// Setup paths to current locale file.
		$mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;
		$mofile_local  = $lang_dir . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/surerank/ folder.
			load_textdomain( 'surerank', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/surerank/languages/ folder.
			load_textdomain( 'surerank', $mofile_local );
		}
	}

	/**
	 * Activation Hook
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activation() {
		Update::option( 'surerank_flush_required', 1 );
		Update::option( 'surerank_redirect_on_activation', 'yes' );
	}

	/**
	 * Deactivation Hook
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivation() {
		Update::option( 'surerank_flush_required', 1 );
	}

	/**
	 * Flush if settings is updated
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function flush_rules() {

		$flush = Get::option( 'surerank_flush_required' );
		if ( $flush ) {
			Helper::flush();
		}

		delete_option( 'surerank_flush_required' );
	}

	/**
	 * Flush the setting on the shubdown
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function shutdown() {
		Update::option( 'rewrite_rules', '' );
	}

	/**
	 * Add meta links to the plugin row (under description).
	 *
	 * @param array<int,string> $links Array of plugin meta links.
	 * @param string            $file Plugin file path.
	 * @return array<int,string> Modified plugin meta links.
	 */
	public function add_meta_links( array $links, string $file ): array {
		if ( SURERANK_BASE === $file ) {
			$stars = '';
			for ( $indx = 0; $indx < 5; $indx++ ) {
				$stars .= '<span class="dashicons dashicons-star-filled" style="color: #ffb900; font-size: 16px; width: 16px; height: 16px; line-height: 1.2;" aria-hidden="true"></span>';
			}
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s" role="button">%s</a>',
				esc_url( 'https://wordpress.org/support/plugin/surerank/reviews/#new-post' ),
				esc_attr__( 'Rate our plugin', 'surerank' ),
				$stars
			);
		}
		return $links;
	}
}

/**
 * Kicking this off by calling 'get_instance()' method
 */
Loader::get_instance();
