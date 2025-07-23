<?php
/**
 * Abstract Analyzer class.
 *
 * Base class for performing SEO checks for WordPress entities with consistent output for UI.
 *
 * @package SureRank\Inc\Analyzer
 */

namespace SureRank\Inc\Analyzer;

use DOMDocument;
use DOMXPath;
use SureRank\Inc\Functions\Get;

/**
 * Abstract Analyzer class.
 */
class Utils {

	/**
	 * Get rendered XPath.
	 *
	 * @param string $rendered_content Rendered content.
	 * @return DOMXPath|null
	 */
	public static function get_rendered_xpath( $rendered_content ) {
		if ( empty( $rendered_content ) ) {
			return null;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );

		$encoded_content = mb_encode_numericentity(
			htmlspecialchars_decode(
				htmlentities( $rendered_content, ENT_NOQUOTES, 'UTF-8', false ),
				ENT_NOQUOTES
			),
			[ 0x80, 0x10FFFF, 0, ~0 ],
			/**
			 * Conversion map for mb_encode_numericentity:
			 * 0x80 (128) is the first non-ASCII Unicode code point.
			 * 0x10FFFF (1,114,111) is the highest valid Unicode code point.
			 * 0 is the bitmask for the first byte (no filtering).
			 * ~0 is the bitmask to include all characters in the range.
			 */
			'UTF-8'
		);

		if ( empty( $encoded_content ) ) {
			return null;
		}

		$dom->loadHTML( $encoded_content );
		libxml_clear_errors();
		return new DOMXPath( $dom );
	}

	/**
	 * Check for search engine title.
	 *
	 * @param string|null $title Title.
	 * @return array<string, mixed>
	 */
	public static function analyze_title( $title ) {
		if ( $title === null ) {
			return [
				'status'  => 'error',
				'message' => __( 'Search engine title is missing on the page.', 'surerank' ),
			];
		}

		$length       = mb_strlen( $title );
		$exists       = ! empty( $title );
		$is_optimized = $exists && $length <= Get::TITLE_LENGTH;
		// translators: %s is the search engine title length.
		$working_message = sprintf( __( 'Search engine title is present and under %s characters.', 'surerank' ), Get::TITLE_LENGTH );
		// translators: %s is the search engine title length.
		$exceeding_message = sprintf( __( 'Search engine title exceeds %s characters.', 'surerank' ), Get::TITLE_LENGTH );
		// translators: %s is the search engine title length.
		$missing_message = __( 'Search engine title is missing on the page.', 'surerank' );

		$message = $exists
			? ( $length <= Get::TITLE_LENGTH
				? $working_message
				: $exceeding_message )
			: $missing_message;

		$description = $exists && ! $is_optimized ? [
			// translators: %s is the search engine title.
			sprintf( __( 'The search engine title for the page is: "%s"', 'surerank' ), $title ),
		] : [];

		return [
			'status'  => $exists ? ( $is_optimized ? 'success' : 'warning' ) : 'error',
			'message' => $message,
		];
	}

	/**
	 * Check for search engine description.
	 *
	 * @param string|null $description Description.
	 * @return array<string, mixed>
	 */
	public static function analyze_description( $description ) {

		if ( $description === null ) {
			return [
				'status'  => 'warning',
				'message' => __( 'Search engine description is missing on the page.', 'surerank' ),
			];
		}

		$length       = mb_strlen( $description );
		$exists       = ! empty( $description );
		$is_optimized = $exists && $length >= Get::DESCRIPTION_MIN_LENGTH && $length <= Get::DESCRIPTION_LENGTH;
		// translators: %s is the search engine description length.
		$working_message = sprintf( __( 'Search engine description is present and under %s characters.', 'surerank' ), Get::DESCRIPTION_LENGTH );
		// translators: %s is the search engine description length.
		$exceeding_message = sprintf( __( 'Search engine description exceeds %s characters.', 'surerank' ), Get::DESCRIPTION_LENGTH );
		// translators: %s is the search engine description length.
		$missing_message = __( 'Search engine description is missing on the page.', 'surerank' );

		$message = $exists
			? ( $length <= Get::DESCRIPTION_LENGTH
				? $working_message
				: $exceeding_message )
			: $missing_message;

		/* translators: %s is the search engine description */
		$description = $exists && ! $is_optimized ? [ sprintf( __( 'The search engine description for the page is: "%s"', 'surerank' ), $description ) ] : [];

		return [
			'status'  => $exists && $length <= Get::DESCRIPTION_LENGTH ? 'success' : 'warning',
			'message' => $message,
		];
	}

	/**
	 * Check for canonical URL.
	 *
	 * @param string|null $canonical Canonical URL.
	 * @param string|null $permalink Permalink URL.
	 * @return array<string, mixed>
	 */
	public static function analyze_canonical_url( $canonical, $permalink ) {
		if ( $canonical === null && $permalink === null ) {
			return [
				'status'  => 'warning',
				'message' => __( 'Canonical tag is not present on the page.', 'surerank' ),
			];
		}

		return [
			'status'  => 'success',
			'message' => __( 'Canonical tag is present on the page.', 'surerank' ),
		];
	}

	/**
	 * Check for URL length.
	 *
	 * @param string|null $url URL.
	 * @return array<string, mixed>
	 */
	public static function check_url_length( $url ) {
		if ( $url === null ) {
			return [
				'status'  => 'warning',
				'message' => __( 'No URL provided.', 'surerank' ),
			];
		}

		$length          = mb_strlen( $url );
		$exists          = ! empty( $url );
		$is_optimized    = $exists && $length <= Get::URL_LENGTH;
		$working_message = __( 'Page URL is short and SEO-friendly.', 'surerank' );

		/* translators: %s is the URL length. */
		$exceeding_message = sprintf( __( 'Page URL is longer than %s characters and may affect SEO and readability.', 'surerank' ), Get::URL_LENGTH );
		$missing_message   = __( 'No URL provided.', 'surerank' );

		$message = $exists
			? ( $is_optimized ? $working_message : $exceeding_message )
			: $missing_message;

		return [
			'status'  => $exists ? ( $is_optimized ? 'success' : 'warning' ) : 'warning',
			'message' => $message,
		];
	}
	/**
	 * Get meta data.
	 *
	 * @param array<string, mixed> $meta Meta data.
	 * @return array<string, mixed>
	 */
	public static function get_meta_data( array $meta ) {
		$meta_data   = $meta['data'] ?? [];
		$global_data = $meta['global_default'] ?? [];

		if ( empty( $meta_data['page_title'] ) ) {
			$meta_data['page_title'] = $global_data['page_title'] ?? '';
			$meta_data['page_title'] = str_replace( '%title%', '%term_title%', $meta_data['page_title'] );
		}

		if ( empty( $meta_data['page_description'] ) ) {
			$meta_data['page_description'] = $global_data['page_description'] ?? '';
			$meta_data['page_description'] = str_replace( '%excerpt%', '%term_description%', $meta_data['page_description'] );
		}

		return $meta_data;
	}

	/**
	 * Get existing broken links.
	 *
	 * @param array<string, mixed> $broken_links Broken links.
	 * @param array<string>        $urls URLs.
	 * @return array<string>
	 */
	public static function existing_broken_links( $broken_links, $urls ) {
		$description           = $broken_links['description'] ?? [];
		$existing_broken_links = [];
		foreach ( $description as $item ) {
			if ( is_array( $item ) && isset( $item['list'] ) ) {
				$existing_broken_links = $item['list'];
				break;
			}
		}

		return array_intersect( $existing_broken_links, $urls );
	}

	/**
	 * Check for open graph tags.
	 *
	 * @return array<string, mixed>
	 */
	public static function open_graph_tags() {

		if ( apply_filters( 'surerank_disable_open_graph_tags', false ) ) {
			return [
				'status'  => 'suggestion',
				'message' => __( 'Open Graph tags are not present on the page.', 'surerank' ),
			];
		}

		return [
			'status'  => 'success',
			'message' => __( 'Open Graph tags are present on the page.', 'surerank' ),
		];
	}
}
