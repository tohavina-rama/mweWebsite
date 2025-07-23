<?php
/**
 * Utils
 *
 * This file handles functionality for all Utils.
 *
 * @package SureRank
 * @since 1.0.0
 */

namespace SureRank\Inc\Schema;

use SureRank\Inc\Schema\Types\Article;
use SureRank\Inc\Schema\Types\BreadcrumbList;
use SureRank\Inc\Schema\Types\Organization;
use SureRank\Inc\Schema\Types\Person;
use SureRank\Inc\Schema\Types\Search_Action;
use SureRank\Inc\Schema\Types\WebPage;
use SureRank\Inc\Schema\Types\WebSite;
use SureRank\Inc\Traits\Get_Instance;

/**
 * Class Utils
 *
 * Provides utility functions for handling schema data.
 *
 * @package SureRank\Inc\Schema
 * @since 1.0.0
 */
class Utils {
	use Get_Instance;

	/**
	 * Get Default Schemas
	 *
	 * Retrieves default schema data with unique IDs as keys.
	 *
	 * @return array<string, mixed> Default schema data with unique IDs as keys.
	 * @since 1.0.0
	 */
	public static function get_default_schemas() {
		$schemas = [];
		foreach ( self::get_schema_types() as $key => $class ) {
			$instance  = $class::get_instance();
			$schemas[] = $instance->schema_data();
		}

		$schemas = apply_filters( 'surerank_default_schemas', $schemas );

		$result = [];
		foreach ( $schemas as $key => $schema ) {
			$unique_id            = self::generate_unique_id( $key );
			$result[ $unique_id ] = $schema;
		}

		return $result;
	}

	/**
	 * Get Schema Type Data
	 *
	 * Retrieves schema type data.
	 *
	 * @return array<string, mixed> Schema type data.
	 */
	public static function get_schema_type_data() {
		$schema_data = [];
		foreach ( self::get_schema_types() as $key => $class ) {
			$instance            = $class::get_instance();
			$schema_data[ $key ] = $instance->get();
		}

		return apply_filters( 'surerank_schema_type_data', $schema_data );
	}

	/**
	 * Parse Schema Fields
	 *
	 * Parses schema fields recursively to include only required data.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $fields Schema fields.
	 * @return array<string, mixed> Parsed fields.
	 */
	public static function parse_fields( array $fields ) {
		$parsed = [];
		foreach ( $fields as $field ) {
			if ( empty( $field['id'] ) || ( empty( $field['required'] ) && empty( $field['show'] ) ) ) {
				continue;
			}

			$type = $field['type'] ?? 'Text';
			if ( $type === 'Group' && isset( $field['fields'] ) ) {
				$default_type = null;
				foreach ( $field['fields'] as $subfield ) {
					if ( isset( $subfield['id'] ) && $subfield['id'] === '@type' && isset( $subfield['std'] ) ) {
						$default_type = $subfield['std'];
						break;
					}
				}

				if ( $default_type ) {
					$filtered_subfields = [];
					foreach ( $field['fields'] as $subfield ) {
						/**
						 * If the field is not a main field, or the main field is the default type, add it to the filtered subfields
						 */
						if ( ! isset( $subfield['main'] ) || $subfield['main'] === $default_type ) {
							$filtered_subfields[] = $subfield;
						}
					}
					$field['fields'] = $filtered_subfields;
				}

				$value = self::parse_group( $field );
			} else {
				$value = $field['std'] ?? '';
			}

			if ( ! empty( $value ) ) {
				$parsed[ $field['id'] ] = $value;
			}
		}
		return $parsed;
	}

	/**
	 * Get Schema Type Options
	 *
	 * @return array<string, mixed> Schema type options.
	 */
	public static function get_schema_type_options() {
		return [
			'WebPage'      => WebPage::get_instance()->get_schema_type_options(),
			'Article'      => Article::get_instance()->get_schema_type_options(),
			'Organization' => Organization::get_instance()->get_schema_type_options(),
		];
	}

	/**
	 * Schema Types
	 *
	 * List of schema type classes.
	 *
	 * @return array<string, mixed> Schema type class mappings.
	 */
	private static function get_schema_types() {
		return [
			'WebSite'        => WebSite::class,
			'WebPage'        => WebPage::class,
			'Organization'   => Organization::class,
			'BreadcrumbList' => BreadcrumbList::class,
			'Article'        => Article::class,
			'SearchAction'   => Search_Action::class,
			'Person'         => Person::class,
		];
	}

	/**
	 * Generate Unique ID
	 *
	 * Generates a unique ID for a given schema type.
	 *
	 * @param string $type The schema type.
	 * @return string The unique ID.
	 */
	private static function generate_unique_id( string $type ): string {
		return sprintf( '%s', wp_generate_uuid4() );
	}

	/**
	 * Parse Group Fields
	 *
	 * Parses group fields recursively.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $field Group field data.
	 * @return array<string, mixed>|array<int, array<string, mixed>> Parsed group fields.
	 */
	private static function parse_group( array $field ) {
		$group_fields = self::parse_fields( $field['fields'] );
		if ( 1 === count( $group_fields ) && isset( $group_fields['@type'] ) ) { // Yoda condition.
			return [];
		}

		if ( ! empty( $field['cloneable'] ) ) {
			return [ $group_fields ];
		}

		return $group_fields;
	}
}
