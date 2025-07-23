<?php
/**
 * Helper
 *
 * @package surerank
 * @since 0.0.1
 */

namespace SureRank\Inc\Functions;

use SureRank\Inc\Meta_Variables\Post;
use SureRank\Inc\Meta_Variables\Site;
use SureRank\Inc\Meta_Variables\Term;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper
 * This class will handle all helper functions.
 *
 * @since 1.0.0
 */
class Helper {

	/**
	 * Check if classic editor is enabled.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_classic_editor_active() {
		// Check if classic editor is active.
		if ( ! is_plugin_active( 'classic-editor/classic-editor.php' ) ) {
			return false;
		}

		return 'classic' === Get::option( 'classic-editor-replace' );
	}

	/**
	 * Get role names.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_role_names() {

		global $wp_roles;

		if ( empty( $wp_roles ) ) {
			$wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $wp_roles->get_names();
	}

	/**
	 * Update flush rules.
	 *
	 * @since 0.0.1
	 * @param array<string, mixed>|array<int, string> $updated_options Array of updated option keys and values.
	 * @return void
	 */
	public static function update_flush_rules( $updated_options ) {
		$flush_settings = apply_filters( 'surerank_flush_rewrite_settings', [] );
		$updated_keys   = array_values( $updated_options ); // Get keys from $updated_options.
		$flush_required = false;

		foreach ( $flush_settings as $setting ) {
			if ( in_array( $setting, $updated_keys, true ) ) {
				$flush_required = true;
				break; // Exit the loop as soon as we find a match.
			}
		}
		if ( $flush_required ) {
			Update::option( 'surerank_flush_required', 1 );
		}
	}

	/**
	 * Flush rewrite rules
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public static function flush() {
		flush_rewrite_rules(); //phpcs:ignore
	}

	/**
	 * Check if woocommerce is active
	 *
	 * @since 0.0.1
	 * @return bool
	 */
	public static function wc_status() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check if surecart is active
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function sc_status() {
		return class_exists( 'SureCart' );
	}

	/**
	 * Get public custom post types.
	 *
	 * @since 0.0.1
	 * @return array<string, mixed>
	 */
	public static function get_public_cpts() {
		$args = [
			'public'  => true,
			'show_ui' => true,
		];

		$output   = 'objects';
		$operator = 'and';

		return get_post_types( $args, $output, $operator );
	}

	/**
	 * Check if the current page is a WooCommerce product.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_product() {
		return function_exists( 'is_product' ) && is_product();
	}

	/**
	 * Get website details
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function website_details() {
		return [
			'website_name'         => self::get_sitename(),
			'website_owner_name'   => self::get_admin_name(),
			'website_owner_email'  => self::get_admin_email(),
			'website_represents'   => Helper::website_represents(),
			'website_logo'         => self::get_logo(),
			'website_lead_details' => self::get_website_lead_details(),
			'website_about_us'     => self::get_website_about_us(),
			'website_contact_us'   => self::get_website_contact_us(),
		];
	}

	/**
	 * Check if the current page is a WooCommerce shop page.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function website_represents() {
		if ( class_exists( 'WooCommerce' ) || class_exists( 'Easy_Digital_Downloads' ) || class_exists( 'SureCart' ) ) {
			return 'ecommerce';
		}
		return '';
	}

	/**
	 * Get sitename
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_sitename() {
		// getting title and if it is not set then we can get URL.
		$title = get_bloginfo( 'title' );
		$url   = get_bloginfo( 'url' );
		return $title ? $title : $url;
	}

	/**
	 * Get sitename
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_admin_name() {
		return get_bloginfo( 'name' );
	}

	/**
	 * Get admin email
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_admin_email() {
		return get_bloginfo( 'admin_email' );
	}

	/**
	 * Get current page number
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_paged_info() {

		global $wp_query;
		if ( ! is_paged() ) {
			return '';
		}

		$page_num  = $wp_query->get( 'paged' );
		$max_pages = $wp_query->max_num_pages;

		if ( empty( $page_num ) || empty( $max_pages ) ) {
			return '';
		}

		return self::format_paged_info( $page_num, $max_pages );
	}

	/**
	 * Format paged info
	 *
	 * @param int $page_num The current page number.
	 * @param int $max_pages The total number of pages.
	 * @since 1.0.0
	 * @return string
	 */
	public static function format_paged_info( $page_num, $max_pages ) {
		if ( empty( $page_num ) || empty( $max_pages ) ) {
			return '';
		}

		/* translators: %s: page number, %s: total pages */
		$formatted_string = sprintf( __( ' - Page %1$s of %2$s', 'surerank' ), $page_num, $max_pages );

		/**
		 * Filter the page number format.
		 *
		 * @since 1.0.0
		 * @param string $formatted_string The formatted string.
		 * @param int $page_num The current page number.
		 * @param int $max_pages The total number of pages.
		 */
		return apply_filters( 'surerank_homepage_pagination_format', $formatted_string, $page_num, $max_pages );
	}

	/**
	 * Get logo
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_logo() {
		$logo     = get_theme_mod( 'custom_logo' );
		$logo_url = '';

		if ( $logo ) {
			$logo_url = wp_get_attachment_url( $logo );
		}

		$attachment = get_post( $logo );
		$metadata   = wp_get_attachment_metadata( $logo );
		$filesize   = $metadata['filesize'] ?? 0;

		if ( ! $attachment ) {
			return [
				'attachment_id' => ! empty( $logo ) ? $logo : 0,
				'name'          => '',
				'size'          => '',
				'type'          => '',
				'url'           => '',
			];
		}

		$name = $attachment->post_title;
		$type = $attachment->post_mime_type;
		$url  = $logo_url;

		return [
			'attachment_id' => $attachment->ID,
			'name'          => $name,
			'size'          => $filesize,
			'type'          => $type,
			'url'           => $url,
		];
	}

	/**
	 * Get logo
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function logo_uri() {

		return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEzLjU1MzcgMS41QzE3Ljg0NTMgMS41IDIxLjMyNTEgNC45Nzg5NSAyMS4zMjUyIDkuMjcwNTFDMjEuMzI1MiAxMi4zNDcgMTkuNTM2OCAxNS4wMDU2IDE2Ljk0MzQgMTYuMjY0NkgyMS4zMjUyVjIyLjVIMTguMDg4OUMxNC45MDg2IDIyLjUgMTIuMjg2MSAyMC4xMTg2IDExLjkwMzMgMTcuMDQySDExLjkwMTRMMTEuOTAzMyAxMy43ODUyQzE0LjgyODMgMTMuNzY2MSAxNy4wMzQyIDExLjM4OTQgMTcuMDM0MiA4LjQ1OTk2VjYuMDI5M0MxNC4xMzcgNi4wMjk0NyAxMS42OTQ4IDcuOTc2ODIgMTAuOTQ0MyAxMC42MzM4QzEwLjE2MDUgOS41MzM0NSA4Ljg3MzgzIDguODE2NSA3LjQxOTkyIDguODE2NDFINi4zODA4NlY5Ljg1MzUySDYuMzgzNzlDNi40NDUxNSAxMi4wMzU2IDguMjMzNzUgMTMuNzg2IDEwLjQzMDcgMTMuNzg2MUgxMC43MDYxTDEwLjY5MzQgMTcuMDQySDEwLjY4NjVDMTAuMjk0MyAyMC4xMDgyIDcuNjc2NzggMjIuNDc4NSA0LjUwMzkxIDIyLjQ3ODVIMi42NzQ4VjEuNUgxMy41NTM3WiIgZmlsbD0iIzQzMzhDQSIvPgo8L3N2Zz4K';
	}

	/**
	 * Get Auth API URL
	 *
	 * @return string
	 */
	public static function get_auth_api_url() {
		return defined( 'SURERANK_SAAS_AUTH_API_URL' ) ? SURERANK_SAAS_AUTH_API_URL : 'https://api.surerank.com/';
	}

	/**
	 * Get website lead details
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public static function get_website_lead_details() {
		$user = wp_get_current_user();
		return [
			'first_name' => $user->first_name ?? '',
			'last_name'  => $user->last_name ?? '',
			'email'      => $user->user_email ?? '',
		];
	}

	/**
	 * Replace variables in a string.
	 *
	 * @param string               $key       The key for the value.
	 * @param mixed                $value     The value to process.
	 * @param array<string, mixed> $variables The variables for replacement.
	 * @return mixed
	 */
	public static function replacement( $key, $value, $variables ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}
		$post_keys = Post::get_instance()->variables;
		$site_keys = Site::get_instance()->variables;
		$term_keys = Term::get_instance()->variables;

		$dynamic_keys = array_keys( array_merge( $post_keys, $site_keys, $term_keys ) );

		$chunks = self::extract_variables( $value );

		$replacement_array = [];
		foreach ( $chunks as $chunk ) {
			if ( ! isset( $replacement_array[ $chunk ] ) ) {
				if ( in_array( $chunk, $dynamic_keys ) ) {
					if ( isset( $variables['post'][ $chunk ]['value'] ) ) {
						$replacement_array[ $chunk ] = $variables['post'][ $chunk ]['value'];
					} elseif ( isset( $variables['term'][ $chunk ]['value'] ) ) {
						$replacement_array[ $chunk ] = $variables['term'][ $chunk ]['value'];
					} elseif ( isset( $variables['site'][ $chunk ]['value'] ) ) {
						$replacement_array[ $chunk ] = $variables['site'][ $chunk ]['value'];
					} else {
						$replacement_array[ $chunk ] = '';
					}
				} else {
					$replacement_array[ $chunk ] = '';
				}
			}
		}

		return preg_replace_callback(
			'/%([^%\s]+)%/',
			static function ( $matches ) use ( $replacement_array ) {
				$value = $matches[1];
				return $replacement_array[ $value ] ?? '';
			},
			$value
		);
	}

	/**
	 * Extract variables from a string.
	 *
	 * @param string $str The input string.
	 * @return array<int, string>
	 */
	public static function extract_variables( $str ) {
		preg_match_all( '/%([^%\s]+)%/', $str, $matches );
		return ! empty( $matches[1] ) ? $matches[1] : [];
	}

	/**
	 * Retrieves a WordPress page's ID and title based on a partial title search.
	 *
	 * @param string $search_term The term to search for in page titles.
	 * @return array<string, mixed>|false Array with 'value' (page ID) and 'label' (page title) if found, false otherwise.
	 */
	public static function get_page_id_by_title( $search_term ) {
		if ( empty( $search_term ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( $screen && $screen->base !== 'surerank_page_surerank_onboarding' ) {
			return false;
		}

		$search_term = sanitize_text_field( $search_term );
		$args        = [
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'sort_order'     => 'ASC',
			'sort_column'    => 'post_title',
			's'              => $search_term,
		];

		$pages = new WP_Query( $args );

		if ( ! empty( $pages->posts[0] ) && $pages->posts[0] instanceof WP_Post ) {
			return [
				'value' => $pages->posts[0]->ID,
				'label' => $pages->posts[0]->post_title,
			];
		}

		return false;
	}

	/**
	 * Get website About Us page ID.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>|false
	 */
	public static function get_website_about_us() {
		return self::get_page_id_by_title( 'about' );
	}

	/**
	 * Get website Contact Us page ID.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed>|false
	 */
	public static function get_website_contact_us() {
		return self::get_page_id_by_title( 'contact' );
	}
}
