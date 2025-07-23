<?php
/**
 * Enqueue
 *
 * @package surerank
 * @since 0.0.1
 */

namespace SureRank\Inc\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Trait Enqueue.
 *
 * @since 1.0.0`
 */
trait Logger {
	use Get_Instance;

	/**
	 * Logger
	 *
	 * @since 0.0.1
	 * @param string $message The message to log.
	 * @return void
	 */
	public static function log_error( $message ) {
		if ( defined( 'SURERANK_DEBUG' ) && SURERANK_DEBUG ) {
			error_log( '[SureRank Error] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
