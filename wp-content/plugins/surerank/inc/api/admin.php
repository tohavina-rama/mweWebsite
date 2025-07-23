<?php
/**
 * Admin class
 *
 * Handles admin settings related REST API endpoints for the SureRank plugin.
 *
 * @package SureRank\Inc\API
 */

namespace SureRank\Inc\API;

use SureRank\Inc\Admin\Update_Timestamp;
use SureRank\Inc\Functions\Get;
use SureRank\Inc\Functions\Helper;
use SureRank\Inc\Functions\Send_Json;
use SureRank\Inc\Functions\Settings;
use SureRank\Inc\Functions\Update;
use SureRank\Inc\Meta_Variables\Post;
use SureRank\Inc\Meta_Variables\Site;
use SureRank\Inc\Meta_Variables\Term;
use SureRank\Inc\Traits\Get_Instance;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Admin
 *
 * Handles admin settings related REST API endpoints.
 */
class Admin extends Api_Base {
	use Get_Instance;

	/**
	 * Route Editor
	 */
	protected const EDITOR = '/editor';

	/**
	 * Route Get Admin Settings
	 */
	protected const ADMIN_SETTINGS = '/admin-settings';

	/**
	 * Route Get Site Settings
	 */
	protected const SITE_SETTINGS = '/site-settings';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Register API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->get_api_namespace(),
			self::EDITOR,
			[
				'methods'             => WP_REST_Server::READABLE, // GET Editor Data.
				'callback'            => [ $this, 'get_initials' ],
				'permission_callback' => [ $this, 'validate_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'term_id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			self::ADMIN_SETTINGS,
			[
				'methods'             => WP_REST_Server::READABLE, // GET Admin Settings.
				'callback'            => [ $this, 'get_admin_settings' ],
				'permission_callback' => [ $this, 'validate_permission' ],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			self::ADMIN_SETTINGS,
			[
				'methods'             => WP_REST_Server::CREATABLE, // Update Admin Settings.
				'callback'            => [ $this, 'update_admin_settings' ],
				'permission_callback' => [ $this, 'validate_permission' ],
				'args'                => [
					'data' => [
						'type'              => 'object',
						'required'          => true,
						'sanitize_callback' => [ $this, 'sanitize_array_data' ],
					],
				],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			self::SITE_SETTINGS,
			[
				'methods'             => WP_REST_Server::READABLE, // Get Site Settings.
				'callback'            => [ $this, 'get_site_settings' ],
				'permission_callback' => [ $this, 'validate_permission' ],
			]
		);
	}

	/**
	 * Retrieves email logs with optional filters and search.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request object.
	 * @return void
	 */
	public function get_initials( $request ) {

		$post_id = $request->get_param( 'post_id' );
		$term_id = $request->get_param( 'term_id' );

		if ( empty( $post_id ) && empty( $term_id ) ) {
			Send_Json::error( [ 'message' => __( 'Post id or term id is required', 'surerank' ) ] );
		}

		Send_Json::success(
			[
				'variables' => $this->get_variables( $request->get_param( 'post_id' ), $request->get_param( 'term_id' ) ),
				'other'     => $this->get_other_data(),
			]
		);
	}

	/**
	 * Get variables
	 *
	 * @param int|null $post_id Post ID.
	 * @param int|null $term_id Term ID.
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Array of variable groups keyed by type (e.g., term, post, site).
	 */
	public function get_variables( $post_id = null, $term_id = null ) {

		$meta_variable_instances = [];
		if ( ! empty( $term_id ) ) {
			$meta_variable_instances['term'] = Term::get_instance();
		}
		if ( ! empty( $post_id ) ) {
			$meta_variable_instances['post'] = Post::get_instance();
		}
		$meta_variable_instances['site'] = Site::get_instance();

		$variables = [];
		foreach ( $meta_variable_instances as $key => $instance ) {
			if ( ! empty( $post_id ) && method_exists( $instance, 'set_post' ) ) {
				$instance->set_post( $post_id );
			}
			if ( ! empty( $term_id ) && method_exists( $instance, 'set_term' ) ) {
				$instance->set_term( $term_id );
			}
			$variables[ $key ] = $instance->get_all_values();
		}

		return $variables;
	}

	/**
	 * Get other data.
	 *
	 * @since 1.0.0
	 * @return array<string, string>
	 */
	public function get_other_data() {
		$data = [];

		// Get site favicon icon.
		$data['favicon_url'] = $this->get_favicon();

		return $data;
	}

	/**
	 * Get admin settings
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 * @return void
	 */
	public function get_admin_settings( $request ) {
		$data                             = Settings::get();
		$data['surerank_analytics_optin'] = Get::option( 'surerank_analytics_optin' ) === 'yes' ? true : false;
		$decode_data                      = Utils::decode_html_entities_recursive( $data ) ?? $data;
		Send_Json::success( [ 'data' => $decode_data ] );
	}

	/**
	 * Update admin settings
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 * @return void
	 */
	public function update_admin_settings( $request ) {
		$data = $request->get_param( 'data' );
		if ( empty( $data ) ) {
			Send_Json::error( [ 'message' => __( 'No data found', 'surerank' ) ] );
		}

		$db_options = Settings::get();

		$updated_options = $this->get_updated_options( $data, $db_options );

		Helper::update_flush_rules( $updated_options );

		if ( is_array( $updated_options ) && array_intersect( [ 'social_profiles', 'facebook_page_url', 'twitter_profile_username' ], $updated_options ) ) {
			$data['social_profiles']['facebook'] = $data['facebook_page_url'] ?? '';

			if ( ! empty( $data['twitter_profile_username'] ) ) {
				$data['social_profiles']['twitter'] = str_replace( '@', '', $data['twitter_profile_username'] );
			}

			$this->process_onboarding_data( $data, $data );
		}

		$data = array_merge( $db_options, $data );

		$data = $this->process_surerank_analytics_optin( $data );

		if ( Update::option( SURERANK_SETTINGS, $data ) ) {
			// Update global timestamp.
			Update_Timestamp::timestamp_option();
		}

		Send_Json::success( [ 'message' => __( 'Settings updated', 'surerank' ) ] );
	}

	/**
	 * Process surerank analytics optin
	 *
	 * @param array<string, mixed> $data Data.
	 * @since 1.0.0
	 * @return array<string, mixed>
	 */
	public function process_surerank_analytics_optin( $data ) {

		if ( ! isset( $data['surerank_analytics_optin'] ) ) {
			return $data;
		}

		$surerank_analytics_optin = $data['surerank_analytics_optin'] ? 'yes' : 'no';
		Update::option( 'surerank_analytics_optin', $surerank_analytics_optin );
		unset( $data['surerank_analytics_optin'] );

		return $data;
	}

	/**
	 * Get site settings
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 * @return void
	 */
	public function get_site_settings( $request ) {
		$data = [];

		// Get site variables.
		$data['site'] = $this->get_site_variables();

		$show_option   = Get::option( 'show_on_front' );
		$page_on_front = Get::option( 'page_on_front' );
		$home_page_id  = intval( $page_on_front );

		// Check if WooCommerce is active.
		$data['is_wc_active'] = class_exists( 'WooCommerce' );

		// Get the id if the home page is set to a page. and get edit page url.
		if ( 'page' === $show_option ) {
			if ( ! empty( $page_on_front ) && ( is_string( $page_on_front ) || is_int( $page_on_front ) ) ) {
				$data['home_page_id']       = $home_page_id;
				$page_url                   = get_edit_post_link( $home_page_id );
				$data['home_page_edit_url'] = '' !== $page_url && is_string( $page_url ) ? html_entity_decode( $page_url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
			}
		}
		$data['home_page_featured_image'] = get_the_post_thumbnail_url( $home_page_id, 'full' ) ? get_the_post_thumbnail_url( $home_page_id, 'full' ) : false;
		$data['home_page_static']         = $show_option;

		Send_Json::success( [ 'data' => $data ] );
	}

	/**
	 * Get updated options
	 *
	 * @param array<string, mixed|array> $data       First array.
	 * @param array<string, mixed|array> $db_options Second array.
	 * @param string                     $parent_key  Parent key.
	 *
	 * @return array<int, string> Updated option keys.
	 */
	public function get_updated_options( $data, $db_options, $parent_key = '' ) {
		$diff_keys = [];

		foreach ( $data as $key => $value ) {
			if ( 'default_global_meta' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				if ( isset( $db_options[ $key ] ) && is_array( $db_options[ $key ] ) ) {
					$nested_diff = $this->get_updated_options( $value, $db_options[ $key ], $key );
					if ( ! empty( $nested_diff ) ) {
						$diff_keys[] = $key;
					}
				} else {
					$diff_keys[] = $key;
				}
			} else {
				if ( ! isset( $db_options[ $key ] ) || $db_options[ $key ] !== $value ) {
					$diff_keys[] = $key;
				}
			}
		}
		return array_unique( $diff_keys );
	}

	/**
	 * Process Onboarding Data
	 *
	 * @param array<string, mixed> $data Data.
	 * @param array<string, mixed> $settings Settings.
	 * @since 1.0.0
	 * @return void
	 */
	public function process_onboarding_data( $data, &$settings ) {
		Onboarding::get_instance()->set_social_schema( $data, $settings );
	}
}
