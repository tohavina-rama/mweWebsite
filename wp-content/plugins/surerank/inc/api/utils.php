<?php
/**
 * Utils class
 * Handles utility functions for the SureRank plugin API's
 *
 * @package SureRank\Inc\API
 */

namespace SureRank\Inc\API;

/**
 * Class Utils
 *
 * Provides utility functions for the SureRank plugin API's.
 */
class Utils {

	/**
	 * Recursively decode HTML entities in arrays, objects or strings.
	 *
	 * @param mixed $value Array, object, string or other.
	 * @return mixed Decoded value of the same type.
	 */
	public static function decode_html_entities_recursive( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::decode_html_entities_recursive( $item );
			}
			return $value;
		}

		if ( is_object( $value ) ) {
			foreach ( get_object_vars( $value ) as $prop => $item ) {
				$value->{$prop} = self::decode_html_entities_recursive( $item );
			}
			return $value;
		}

		if ( is_string( $value ) ) {
			return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		// leave ints, bools, null, etc. untouched.
		return $value;
	}

	/**
	 * Process options data and return new option values
	 *
	 * @param array<string, mixed> $all_options All available options.
	 * @param array<string, mixed> $data Data to process.
	 * @return array<string, mixed> Processed option values.
	 */
	public static function process_option_values( array $all_options, array $data ): array {
		$processed_options = [];

		foreach ( $all_options as $option_name => $option_value ) {
			$new_option_value = [];
			if ( is_array( $option_value ) ) {
				if ( empty( $option_value ) ) {
					$new_option_value[ $option_name ] = $data[ $option_name ] ?? $option_value;
				} else {
					foreach ( $option_value as $key => $value ) {
						if ( isset( $data[ $key ] ) ) {
							$new_option_value[ $key ] = $data[ $key ] !== '' ? $data[ $key ] : $value;
						}
					}
				}
			} else {
				if ( isset( $data[ $option_name ] ) ) {
					$new_option_value = $data[ $option_name ] === '' ? false : $data[ $option_name ];
				}
			}

			if ( ! empty( $new_option_value ) ) {
				$processed_options[ $option_name ] = $new_option_value;
			}
		}

		return $processed_options;
	}
}
