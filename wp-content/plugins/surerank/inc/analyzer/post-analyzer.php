<?php
/**
 * Post Analyzer class.
 *
 * Performs SEO checks for WordPress posts like page, post, cpts with consistent output for UI.
 *
 * @package SureRank\Inc\Analyzer
 */

namespace SureRank\Inc\Analyzer;

use DOMElement;
use DOMNodeList;
use DOMXPath;
use SureRank\Inc\API\Admin;
use SureRank\Inc\API\Post;
use SureRank\Inc\Functions\Get;
use SureRank\Inc\Functions\Helper;
use SureRank\Inc\Functions\Settings;
use SureRank\Inc\Functions\Update;
use SureRank\Inc\Traits\Get_Instance;
use SureRank\Inc\Traits\Logger;
use WP_Post;

/**
 * Post Analyzer
 */
class PostAnalyzer {
	use Get_Instance;
	use Logger;

	/**
	 * XPath instance.
	 *
	 * @var DOMXPath|null
	 */
	private $xpath;

	/**
	 * Page title.
	 *
	 * @var string|null
	 */
	private $page_title;

	/**
	 * Page description.
	 *
	 * @var string|null
	 */
	private $page_description = '';

	/**
	 * Canonical URL.
	 *
	 * @var string|null
	 */
	private $canonical_url = '';

	/**
	 * Post ID.
	 *
	 * @var int|null
	 */
	private $post_id;

	/**
	 * Post permalink.
	 *
	 * @var string
	 */
	private $post_permalink = '';

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! Settings::get( 'enable_page_level_seo' ) ) {
			return;
		}
		add_action( 'wp_after_insert_post', [ $this, 'save_post' ], 10, 2 );
		add_filter( 'surerank_run_post_seo_checks', [ $this, 'run_checks' ], 10, 2 );
	}

	/**
	 * Handle post save to run SEO checks.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return;
		}

		$post_type_object = get_post_type_object( $post_type );

		if ( ! $post_type_object || ! $post_type_object->public ||
			in_array( $post_type, apply_filters( 'surerank_excluded_post_types_from_seo_checks', [] ), true ) ) {
			return;
		}
		$response = $this->run_checks( $post_id, $post );

		if ( isset( $response['status'] ) && 'error' === $response['status'] ) {
			$this->log_error( $response['message'] );
		}
	}

	/**
	 * Run SEO checks for the post.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return array<string, mixed>
	 */
	public function run_checks( $post_id, $post ) {
		$this->post_id = $post_id;

		if ( ! $this->post_id || ! $post instanceof WP_Post ) {
			return [
				'status' => 'error',
			];
		}

		$meta_data = Post::get_post_data_by_id( $post_id, $post->post_type, false );
		$variables = Admin::get_instance()->get_variables( $post_id, null );
		$meta_data = Utils::get_meta_data( $meta_data );

		foreach ( $meta_data as $key => $value ) {
			$meta_data[ $key ] = Helper::replacement( $key, $value, $variables );
		}

		$this->page_title       = $meta_data['page_title'] ?? '';
		$this->page_description = $meta_data['page_description'] ?? '';
		$this->canonical_url    = $meta_data['canonical_url'] ?? '';
		$this->post_permalink   = get_permalink( (int) $post_id ) !== false ? get_permalink( (int) $post_id ) : '';

		$rendered_content = '';
		$post_content     = $post->post_content;
		$blocks           = parse_blocks( $post_content );
		foreach ( $blocks as $block ) {
			$rendered_content .= render_block( $block );
		}

		$this->xpath = Utils::get_rendered_xpath( $rendered_content );
		$result      = $this->analyze();

		if ( $this->update_broken_links_status( $result ) && is_array( $result ) ) {
			$result['broken_links'] = $this->update_broken_links_status( $result );
		}

		$success = Update::post_seo_checks( $post_id, $result );

		if ( ! $success ) {
			return [
				'status'  => 'error',
				'message' => __( 'Failed to update SEO checks', 'surerank' ),
			];
		}

		return $result;
	}

	/**
	 * Analyze the post.
	 *
	 * @return array<string, mixed>
	 */
	private function analyze() {
		return [
			'h2_subheadings'            => $this->check_subheadings(),
			'image_alt_text'            => $this->check_image_alt_text(),
			'media_present'             => $this->check_media_present(),
			'links_present'             => $this->check_links_present(),
			'url_length'                => Utils::check_url_length( $this->post_permalink ),
			'search_engine_title'       => Utils::analyze_title( $this->page_title ),
			'search_engine_description' => Utils::analyze_description( $this->page_description ),
			'canonical_url'             => $this->canonical_url(),
			'all_links'                 => $this->get_all_links(),
			'open_graph_tags'           => Utils::open_graph_tags(),
		];
	}

	/**
	 * Check for H2 subheadings.
	 *
	 * @return array<string, mixed>
	 */
	private function check_subheadings() {
		$headings = [ 'h2', 'h3', 'h4', 'h5', 'h6' ];
		$count    = 0;

		foreach ( $headings as $tag ) {
			$elements = $this->xpath ? $this->xpath->query( "//{$tag}" ) : null;
			$count   += $elements instanceof DOMNodeList ? $elements->length : 0;
		}

		if ( $count === 0 ) {
			return [
				'status'  => 'warning',
				'message' => __( 'The page does not contain any subheadings.', 'surerank' ),
			];
		}

		return [
			'status'  => 'success',
			'message' => __( 'Page contains at least one subheading.', 'surerank' ),
		];
	}

	/**
	 * Check for image alt text.
	 *
	 * @return array<string, mixed>
	 */
	private function check_image_alt_text() {
		$images = $this->xpath ? $this->xpath->query( '//img' ) : new DOMNodeList();

		if ( ! $images || $images->length === 0 ) {
			return [];
		}

		$total              = $images->length;
		$missing_alt        = 0;
		$missing_alt_images = [];

		foreach ( $images as $img ) {
			if ( $img instanceof DOMElement ) {
				$src = $img->hasAttribute( 'src' ) ? $img->getAttribute( 'src' ) : '';
				if ( ! $img->hasAttribute( 'alt' ) || empty( trim( $img->getAttribute( 'alt' ) ) ) ) {
					$missing_alt++;
					if ( $src ) {
						$missing_alt_images[] = $src;
					}
				}
			}
		}

		$exists       = $total > 0;
		$is_optimized = $exists && $missing_alt === 0;

		$message = $exists && $is_optimized ? __( 'All images on this page have alt text attributes.', 'surerank' ) : __( 'One or more images on this page are missing alt text attributes.', 'surerank' );

		return [
			'status'      => $exists ? ( $is_optimized ? 'success' : 'warning' ) : 'warning',
			'description' => $this->build_image_description( $exists, $total, $missing_alt, $missing_alt_images ),
			'message'     => $message,
			'show_images' => $exists && $missing_alt > 0,
		];
	}

	/**
	 * Build image description.
	 *
	 * @param bool          $exists Whether images exist.
	 * @param int           $total Total number of images.
	 * @param int           $missing_alt Number of images missing alt text.
	 * @param array<string> $missing_alt_images Images missing alt text.
	 * @return array<int, array<string, array<int, string>>|string>
	 */
	private function build_image_description( bool $exists, int $total, int $missing_alt, array $missing_alt_images ) {
		$descriptions = [];

		if ( ! $exists ) {
			$descriptions[] = __( 'The page does not contain any images.', 'surerank' );
			$descriptions[] = __( 'Add images to improve the post/page\'s visual appeal and SEO.', 'surerank' );
		} else {
			if ( $missing_alt === 0 ) {
				$descriptions[] = __( 'Images on the post/page have alt text attributes', 'surerank' );
			} else {
				if ( ! empty( $missing_alt_images ) ) {
					$list = [];
					foreach ( array_unique( $missing_alt_images ) as $image ) {
						$list[] = esc_html( $image );
					}
					$descriptions[]['list'] = $list;
				}
			}
		}

		return $descriptions;
	}

	/**
	 * Check for media present.
	 *
	 * @return array<string, mixed>
	 */
	private function check_media_present() {
		$images         = $this->xpath ? $this->xpath->query( '//img' ) : new DOMNodeList();
		$videos         = $this->xpath ? $this->xpath->query( '//video' ) : new DOMNodeList();
		$featured_image = get_post_thumbnail_id( $this->post_id );

		$image_length = $images->length ?? 0;
		$video_length = $videos->length ?? 0;
		$exists       = $image_length > 0 || $video_length > 0 || $featured_image;
		$message      = $exists ? __( 'This page includes images or videos to enhance content.', 'surerank' ) : __( 'No images or videos found on this page.', 'surerank' );

		return [
			'status'  => $exists ? 'success' : 'warning',
			'message' => $message,
		];
	}

	/**
	 * Check for links present.
	 *
	 * @return array<string, mixed>
	 */
	private function check_links_present() {
		$links = $this->xpath ? $this->xpath->query( '//a[@href]' ) : new DOMNodeList();

		if ( ! $links || $links->length === 0 ) {
			return [
				'status'  => 'warning',
				'message' => __( 'No links found on the page.', 'surerank' ),
			];
		}

		return [
			'status'  => 'success',
			'message' => __( 'Links are present on the page.', 'surerank' ),
		];
	}

	/**
	 * Get canonical URL.
	 *
	 * @return array<string, mixed>
	 */
	private function canonical_url() {
		if ( $this->canonical_url === null ) {
			return [
				'status'  => 'error',
				'message' => __( 'No canonical URL provided.', 'surerank' ),
			];
		}

		$permalink = get_permalink( (int) $this->post_id );
		if ( ! $permalink ) {
			return [
				'status'  => 'error',
				'message' => __( 'No permalink provided.', 'surerank' ),
			];
		}

		return Utils::analyze_canonical_url( $this->canonical_url, $permalink );
	}

	/**
	 * Update broken links status.
	 *
	 * @param array<string, mixed> $result Result.
	 * @return array<string, mixed>|false
	 */
	private function update_broken_links_status( $result ) {
		$links = $this->xpath ? $this->xpath->query( '//a[@href]' ) : new DOMNodeList();

		$empty_message = [
			'status'  => 'success',
			'message' => __( 'No broken links found on the page.', 'surerank' ),
		];

		if ( ! $links || $links->length === 0 ) {
			return $empty_message;
		}

		$urls = [];
		foreach ( $links as $link ) {
			if ( $link instanceof DOMElement ) {
				if ( ! in_array( $link->getAttribute( 'href' ), $urls ) ) {
					$urls[] = $link->getAttribute( 'href' );
				}
			}
		}

		$broken_links = Get::post_meta( (int) $this->post_id, SURERANK_SEO_CHECKS, true );
		$broken_links = $broken_links['broken_links'] ?? [];

		$existing_broken_links = Utils::existing_broken_links( $broken_links, $urls );

		if ( empty( $existing_broken_links ) ) {
			return $empty_message;
		}

		return false;
	}

	/**
	 * Get all links from the rendered post content.
	 *
	 * @return array<string>
	 */
	private function get_all_links() {
		if ( ! $this->xpath ) {
			return [];
		}

		$links        = [];
		$anchor_nodes = $this->xpath->query( '//a[@href]' );

		if ( ! $anchor_nodes instanceof DOMNodeList ) {
			return [];
		}

		foreach ( $anchor_nodes as $anchor ) {
			if ( $anchor instanceof DOMElement ) {
				$href = trim( $anchor->getAttribute( 'href' ) );
				if ( $href !== '' && ! in_array( $href, $links, true ) ) {
					$links[] = $href;
				}
			}
		}

		return $links;
	}

}
