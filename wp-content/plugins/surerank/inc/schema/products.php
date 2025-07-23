<?php
/**
 * Products
 *
 * This file handles functionality for all Products.
 *
 * @package SureRank
 * @since 1.0.0
 */

namespace SureRank\Inc\Schema;

use SureRank\Inc\Functions\Helper;
use SureRank\Inc\Schema\Types\Product;
use SureRank\Inc\Traits\Get_Instance;
use WC_Product_Variable;
use WC_Product_Variation;

/**
 * Products
 * This class handles functionality for all Products.
 *
 * @since 1.0.0
 */
class Products {

	use Get_Instance;

	/**
	 * Constructor.
	 */
	public function __construct() {

		if ( ! Helper::wc_status() && ! Helper::sc_status() ) {
			return;
		}

		$this->init();
	}

	/**
	 * Initialize the class functionality.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'surerank_default_schemas', [ $this, 'add_product_schema' ] );
		add_filter( 'surerank_schema_data', [ $this, 'add_schema_data' ] );
		add_action( 'wp_footer', [ $this, 'remove_wc_schema' ], 0 );
		add_filter( 'surerank_default_schema_variables', [ $this, 'add_variables' ] );
		add_filter( 'surerank_schema_type_data', [ $this, 'schema_type_data' ] );
	}

	/**
	 * Add schema data for products.
	 *
	 * @param array<string, mixed> $data Existing schema data.
	 * @return array<string, mixed> Modified schema data.
	 */
	public function add_schema_data( $data ) {

		$post = is_singular() ? get_post( get_queried_object_id() ) : '';

		if ( empty( $post ) ) {
			return $data;
		}

		$post_type = get_post_type( $post );

		switch ( $post_type ) {
			case 'product':
				/**
				 * Get product data from WooCommerce
				 */
				$product = wc_get_product( $post->ID ) ?? '';

				if ( empty( $product ) ) {
					return $data;
				}

				$product_data = $this->get_product_data( $product );
				break;

			case 'sc_product':
				/**
				 * Get product data from SureCart
				 */
				$product = get_post_meta( $post->ID, 'product', true ) ?? '';

				if ( empty( $product ) ) {
					return $data;
				}

				$product_data = $this->get_surecart_product_data( $product );

				/**
				 * Remove WooCommerce schema from SureCart product
				 */
				add_filter( 'sc_display_product_json_ld_schema', '__return_false', 10 );
				break;

			default:
				return $data;
		}

		// Merge product data into schema.
		$data['product'] = $product_data;

		return $data;
	}

	/**
	 * Remove WooCommerce schema from footer.
	 *
	 * @return void
	 */
	public function remove_wc_schema() {
		if ( ! Helper::wc_status() ) {
			return;
		}

		remove_action( 'wp_footer', [ WC()->structured_data, 'output_structured_data' ], 10 );
	}

	/**
	 * Add variables for product schema.
	 *
	 * @param array<string, mixed> $variables Existing variables.
	 * @return array<string, mixed> Modified variables.
	 */
	public function add_variables( $variables ) {
		$wc_variables = [
			'%product.price%'                 => __( 'Product Price', 'surerank' ),
			'%product.price_with_tax%'        => __( 'Product Price Including Tax', 'surerank' ),
			'%product.low_price%'             => __( 'Product Low Price (variable product)', 'surerank' ),
			'%product.high_price%'            => __( 'Product High Price (variable product)', 'surerank' ),
			'%product.offer_count%'           => __( 'Product Offer Count (variable product)', 'surerank' ),
			'%product.sale_from%'             => __( 'Product Sale Price Date "From"', 'surerank' ),
			'%product.sale_to%'               => __( 'Product Sale Price Date "To"', 'surerank' ),
			'%product.sku%'                   => __( 'Product SKU', 'surerank' ),
			'%product.stock%'                 => __( 'Product Stock Status', 'surerank' ),
			'%product.currency%'              => __( 'Product Currency', 'surerank' ),
			'%product.rating%'                => __( 'Product Rating Value', 'surerank' ),
			'%product.review_count%'          => __( 'Product Review Count', 'surerank' ),
			'%product.image%'                 => __( 'Product Image', 'surerank' ),
			'%product.image_width%'           => __( 'Product Image Width', 'surerank' ),
			'%product.image_height%'          => __( 'Product Image Height', 'surerank' ),
			'%product.description%'           => __( 'Product Description', 'surerank' ),
			'%product.variant_sku%'           => __( 'Product Variant SKU', 'surerank' ),
			'%product.variant_name%'          => __( 'Product Variant Name', 'surerank' ),
			'%product.variant_url%'           => __( 'Product Variant URL', 'surerank' ),
			'%product.variant_image%'         => __( 'Product Variant Image', 'surerank' ),
			'%product.variant_size%'          => __( 'Product Variant Size', 'surerank' ),
			'%product.variant_color%'         => __( 'Product Variant Color', 'surerank' ),
			'%product.variant_description%'   => __( 'Product Variant Description', 'surerank' ),
			'%product.variant_sale_price%'    => __( 'Product Variant Sale Price', 'surerank' ),
			'%product.variant_regular_price%' => __( 'Product Variant Regular Price', 'surerank' ),
			'%product.variant_stock%'         => __( 'Product Variant Stock Status', 'surerank' ),
		];

		return array_merge( $variables, $wc_variables );
	}

	/**
	 * Add product schema.
	 *
	 * @param array<string, mixed> $schemas Existing schemas.
	 * @return array<string, mixed> Modified schemas.
	 */
	public function add_product_schema( $schemas ) {

		if ( Helper::wc_status() ) {
			$rule = 'product|all';
		} elseif ( Helper::sc_status() ) {
			$rule = 'sc_product|all';
		} else {
			return $schemas;
		}

		$schemas['Product'] = [
			'title'   => 'Product',
			'type'    => 'Product',
			'show_on' => [
				'rules'        => [
					$rule,
				],
				'specific'     => [],
				'specificText' => [],
			],
			'fields'  => Utils::parse_fields( Product::get_instance()->get() ),
		];

		return $schemas;
	}

	/**
	 * Get schema type data.
	 *
	 * @param array<string, mixed> $data Existing schema type data.
	 * @return array<string, mixed> Modified schema type data.
	 */
	public function schema_type_data( $data ) {
		$data['Product'] = Product::get_instance()->get();
		return $data;
	}

	/**
	 * Get detailed product data for schema.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @return array<string, mixed> Product data for schema.
	 */
	private function get_product_data( $product ) {
		$price          = $product->get_price();
		$price_with_tax = wc_get_price_including_tax( $product, [ 'price' => $price ] );

		$sale_from = $product->get_date_on_sale_from()
			? gmdate( 'Y-m-d', $product->get_date_on_sale_from()->getTimestamp() )
			: '';

		$today   = gmdate( 'Y-m-d' );
		$sale_to = gmdate( 'Y-m-d', wc_string_to_timestamp( '+1 month' ) );

		if ( $product->is_on_sale() && $product->get_date_on_sale_to() ) {
			$sale_to = gmdate( 'Y-m-d', $product->get_date_on_sale_to()->getTimestamp() );
		} elseif ( $sale_from > $today ) {
			$sale_to = gmdate( 'Y-m-d', wc_string_to_timestamp( $sale_from ) - DAY_IN_SECONDS );
		} elseif ( $sale_from === $today ) {
			$sale_to = $today;
		}

		$low_price    = '';
		$high_price   = '';
		$offer_count  = 0;
		$variant_data = [];

		// Here we are checking if the product is variable product and if it is then we are getting the variation data.
		if ( $product->is_type( 'variable' ) && $product instanceof WC_Product_Variable ) {
			$low_price              = wc_get_price_including_tax( $product, [ 'price' => $product->get_variation_price( 'min', false ) ] );
			$high_price             = wc_get_price_including_tax( $product, [ 'price' => $product->get_variation_price( 'max', false ) ] );
			$offer_count            = count( $product->get_children() );
			$variations             = $product->get_available_variations();
			$variation_descriptions = [];

			if ( ! empty( $variations ) && is_array( $variations ) ) {
				$default_variation = $variations[0] ?? [];

				$variation_id           = null;
				$variation_descriptions = '';

				if ( is_array( $default_variation ) ) {
					if ( isset( $default_variation['variation_id'] ) ) {
						$variation_id = $default_variation['variation_id'];
					}

					if ( isset( $default_variation['variation_description'] ) ) {
						$variation_descriptions = $default_variation['variation_description'];
					}
				}

				if ( $variation_id ) {
					$variation_obj = wc_get_product( $variation_id );

					if ( $variation_obj && $variation_obj instanceof WC_Product_Variation ) {
						$attributes = $variation_obj->get_attributes();

						$size  = '';
						$color = '';

						foreach ( $attributes as $attribute_name => $attribute_value ) {
							$taxonomy = str_replace( 'attribute_', '', $attribute_name );

							if ( strpos( $taxonomy, 'size' ) !== false ) {
								$size = $attribute_value;
							} elseif ( strpos( $taxonomy, 'color' ) !== false || strpos( $taxonomy, 'colour' ) !== false ) {
								$color = $attribute_value;
							}
						}

						// get variation image and fallback to product image.
						$variation_image_id  = $variation_obj->get_image_id() ? $variation_obj->get_image_id() : $product->get_image_id();
						$variation_image     = wp_get_attachment_image_src( (int) $variation_image_id, 'single-post-thumbnail' );
						$variation_image_url = $variation_image ? $variation_image[0] : '';

						// check stock status.
						$statuses   = $this->get_stock_status_mapping();
						$var_status = strtolower( $variation_obj->get_stock_status() );
						$var_stock  = 'https://schema.org/' . ( $statuses[ $var_status ] ?? 'InStock' );

						// populate variant data.
						$variant_data = [
							'sku'           => $variation_obj->get_sku(),
							'name'          => $variation_obj->get_name(),
							'url'           => $variation_obj->get_permalink(),
							'image'         => $variation_image_url,
							'size'          => $size,
							'color'         => $color,
							'description'   => $variation_descriptions,
							'sale_price'    => $variation_obj->get_sale_price() ? wc_get_price_including_tax( $variation_obj, [ 'price' => $variation_obj->get_sale_price() ] ) : '',
							'regular_price' => wc_get_price_including_tax( $variation_obj, [ 'price' => $variation_obj->get_regular_price() ] ),
							'stock'         => $var_stock,
						];
					}
				}
			}
		}

		$statuses = $this->get_stock_status_mapping();
		$status   = strtolower( $product->get_stock_status() );
		$stock    = 'https://schema.org/' . ( $statuses[ $status ] ?? 'InStock' );

		$image_data = $this->get_product_image_dimensions( $product );

		$product_data = [
			'price'             => $price,
			'price_with_tax'    => $price_with_tax,
			'low_price'         => $low_price,
			'high_price'        => $high_price,
			'offer_count'       => $offer_count,
			'sale_from'         => $sale_from,
			'sale_to'           => $sale_to,
			'sku'               => $product->get_sku(),
			'stock'             => $stock,
			'currency'          => get_woocommerce_currency(),
			'rating'            => $product->get_average_rating(),
			'review_count'      => $product->get_review_count(),
			'image'             => $image_data['image'],
			'image_width'       => $image_data['image_width'],
			'image_height'      => $image_data['image_height'],
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
		];

		if ( ! empty( $variant_data ) ) {
			foreach ( $variant_data as $key => $value ) {
				$product_data[ 'variant_' . $key ] = $value ?? '';
			}
		}

		return $product_data;
	}

	/**
	 * Get product image dimensions.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @return array<string, mixed> Product image dimensions.
	 */
	private function get_product_image_dimensions( $product ) {

		$image_data        = wp_get_attachment_image_src( (int) $product->get_image_id(), 'single-post-thumbnail' );
		$product_image_url = $image_data[0] ?? '';

		if ( empty( $product_image_url ) ) {
			$surerank_settings = get_post_meta( $product->get_id(), 'surerank_settings', true );
			$product_image_url = $surerank_settings['facebook_image_url'] ?? '';
		}

		return [
			'image'        => $product_image_url ?? '',
			'image_width'  => $image_data[1] ?? '',
			'image_height' => $image_data[2] ?? '',
		];
	}

	/**
	 * Get stock status mapping for schema.
	 *
	 * @return array<string, mixed> Stock status mapping.
	 */
	private function get_stock_status_mapping() {
		return [
			'instock'     => 'InStock',
			'outofstock'  => 'OutOfStock',
			'onbackorder' => 'BackOrder',
			'soldout'     => 'SoldOut',
		];
	}

	/**
	 * Get detailed product data for schema from SureCart data.
	 *
	 * @param array<string, mixed> $post_data SureCart product data array.
	 * @return array<string, mixed> Product data for schema.
	 */
	private function get_surecart_product_data( $post_data ) {
		$price          = isset( $post_data['initial_amount'] ) ? $post_data['initial_amount'] / 100 : '';
		$price_with_tax = $price;

		// Sale dates (SureCart doesn't provide sale dates directly in this data, so we'll leave them empty unless you have a custom field).
		$sale_from = '';
		$sale_to   = '';

		$low_price   = isset( $post_data['metrics']['min_price_amount'] ) ? $post_data['metrics']['min_price_amount'] / 100 : $price;
		$high_price  = isset( $post_data['metrics']['max_price_amount'] ) ? $post_data['metrics']['max_price_amount'] / 100 : $price;
		$offer_count = $post_data['variants']['pagination']['count'] ?? 0;

		$variant_data = [];
		if ( $offer_count > 0 && ! empty( $post_data['variants']['data'] ) ) {
			// Get the first variant as the default.
			$default_variant = $post_data['variants']['data'][0];

			$size  = $default_variant['option_1'] ?? '';
			$color = $default_variant['option_2'] ?? '';

			// Variant image (use product image if variant-specific image is not set).
			$variant_image_url = $default_variant['image_url'] ?? $post_data['image_url'];

			// Stock status.
			$stock_enabled = $post_data['stock_enabled'] ?? false;
			$stock_status  = $stock_enabled && $default_variant['available_stock'] <= 0 ? 'OutOfStock' : 'InStock';
			$var_stock     = 'https://schema.org/' . $stock_status;

			// Variant data (SureCart doesn't seem to have variant-specific prices here; use initial price).
			$variant_data = [
				'sku'           => $default_variant['sku'] ?? '',
				'name'          => $post_data['name'] . ' - ' . $size . ' ' . $color,
				'url'           => $post_data['checkout_permalink'] ?? '',
				'image'         => $variant_image_url,
				'size'          => $size,
				'color'         => $color,
				'description'   => $post_data['description'] ?? '',
				'sale_price'    => '',
				'regular_price' => $price,
				'stock'         => $var_stock,
			];
		}

		// Stock status for the main product.
		$stock_enabled = $post_data['stock_enabled'] ?? false;
		$stock_status  = $stock_enabled && $post_data['available_stock'] <= 0 ? 'OutOfStock' : 'InStock';
		$stock         = 'https://schema.org/' . $stock_status;

		// Get product image dimensions from preview image.
		$preview_image = $post_data['preview_image'] ?? '';
		$image_url     = $preview_image->src ?? '';
		$image_width   = $preview_image->width ?? '';
		$image_height  = $preview_image->height ?? '';

		// Currency (from SureCart's initial price or metrics).
		$currency = $post_data['initial_price']['currency'] ?? 'usd';

		// Rating and reviews (not available in SureCart data, so default to empty).
		$rating       = '';
		$review_count = '';

		// Assemble product data.
		$product_data = [
			'price'             => $price,
			'price_with_tax'    => $price_with_tax,
			'low_price'         => $low_price,
			'high_price'        => $high_price,
			'offer_count'       => $offer_count,
			'sale_from'         => $sale_from,
			'sale_to'           => $sale_to,
			'sku'               => $post_data['sku'] ?? '',
			'stock'             => $stock,
			'currency'          => strtoupper( $currency ),
			'rating'            => $rating,
			'review_count'      => $review_count,
			'image'             => $image_url,
			'image_width'       => $image_width,
			'image_height'      => $image_height,
			'description'       => $post_data['description'] ?? '',
			'short_description' => $post_data['meta_description'] ?? '',
		];

		// Add variant data if available.
		if ( ! empty( $variant_data ) ) {
			foreach ( $variant_data as $key => $value ) {
				$product_data[ 'variant_' . $key ] = $value ?? '';
			}
		}

		return $product_data;
	}
}
