<?php
/**
 * Dashboard class
 *
 * Handles dashboard related REST API endpoints for the SureRank plugin.
 *
 * @package SureRank\Inc\GoogleSearchConsole
 */

namespace SureRank\Inc\GoogleSearchConsole;

use SureRank\Inc\API\Api_Base;
use SureRank\Inc\Functions\Helper;
use SureRank\Inc\Functions\Send_Json;
use SureRank\Inc\Traits\Get_Instance;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Dashboard
 *
 * Handles dashboard related REST API endpoints with improved error handling and structure.
 */
class Dashboard extends Api_Base {

	use Get_Instance;

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Register Routes
	 *
	 * @return void
	 */
	public function register_routes() {
		$namespace = $this->get_api_namespace();

		$routes = [
			'revoke-auth'            => [
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => [ $this, 'revoke' ],
			],
			'auth-url'               => [
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_auth_url' ],
			],
			'matched-site'           => [
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_matched_site' ],
			],
			'sites'                  => [
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_sites' ],
			],
			'site'                   => [
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_site' ],
			],
			'clicks-and-impressions' => [
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'get_clicks_and_impressions' ],
				'args'     => [
					'startDate' => [
						'type'     => 'string',
						'required' => false,
					],
					'endDate'   => [
						'type'     => 'string',
						'required' => false,
					],
				],
			],
			'site-traffic'           => [
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_site_traffic' ],
				'args'     => [
					'startDate' => [
						'type'     => 'string',
						'required' => false,
					],
					'endDate'   => [
						'type'     => 'string',
						'required' => false,
					],
				],
			],
			'content-performance'    => [
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_content_performance' ],
				'args'     => [
					'startDate' => [
						'type'     => 'string',
						'required' => false,
					],
					'endDate'   => [
						'type'     => 'string',
						'required' => false,
					],
				],
			],
		];

		foreach ( $routes as $endpoint => $args ) {
			register_rest_route(
				$namespace,
				$endpoint,
				[
					'methods'             => $args['methods'],
					'callback'            => $args['callback'],
					'permission_callback' => [ $this, 'validate_permission' ],
					'args'                => $args['args'] ?? [],
				]
			);
		}

		register_rest_route(
			$namespace,
			'site',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_site' ],
				'permission_callback' => [ $this, 'validate_permission' ],
				'args'                => [
					'url' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Get Site saved in credentials
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function get_site() {
		Send_Json::success( [ 'site' => Auth::get_instance()->get_credentials( 'site_url' ) ] );
	}

	/**
	 * Update Site saved in credentials
	 *
	 * @return void
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 */
	public function update_site( $request ) {
		$url                         = $request->get_param( 'url' );
		$all_credentials             = Auth::get_instance()->get_credentials();
		$all_credentials['site_url'] = $url;
		Auth::get_instance()->save_credentials( $all_credentials );
		Send_Json::success();
	}

	/**
	 * Revoke
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function revoke() {
		Auth::get_instance()->delete_credentials();
		Send_Json::success();
	}

	/**
	 * Get authentication URL
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function get_auth_url() {
		$query_args = apply_filters(
			'surerank_auth_api_url_query_args',
			[
				'nonce'  => wp_create_nonce( 'surerank_auth_nonce' ),
				'action' => 'surerank_auth',
			]
		);

		$redirect_uri = add_query_arg( $query_args, admin_url( 'admin.php?page=surerank' ) );
		$auth_url     = Helper::get_auth_api_url() . 'search-console/connect/?redirect_uri=' . urlencode( $redirect_uri );
		Send_Json::success( [ 'url' => $auth_url ] );
	}

	/**
	 * Get Sites
	 *
	 * @return void
	 */
	public function get_sites() {
		// We are checking is_wp_error() in call_api() method.
		Send_Json::success( Controller::get_instance()->get_sites() );
	}

	/**
	 * Get Matched
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function get_matched_site() {
		Send_Json::success( [ 'matched' => Controller::get_instance()->get_matched_site() ] );
	}

	/**
	 * Get Site Traffic
	 *
	 * @return void
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 */
	public function get_site_traffic( $request ) {
		Send_Json::success( Controller::get_instance()->get_site_traffic( $request ) );
	}

	/**
	 * Get Clicks and Impressions
	 *
	 * @return void
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 */
	public function get_clicks_and_impressions( $request ) {
		Send_Json::success( Controller::get_instance()->get_clicks_and_impressions( $request ) );
	}

	/**
	 * Get Content Performance
	 *
	 * @return void
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @since 1.0.0
	 */
	public function get_content_performance( $request ) {
		Send_Json::success( Controller::get_instance()->get_content_performance( $request ) );
	}
}
