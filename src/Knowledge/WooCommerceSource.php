<?php
/**
 * WooCommerce product knowledge adapter.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Add public WooCommerce product data without requiring WooCommerce.
 */
final class WooCommerceSource {

	/**
	 * Register source filters.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wpdsac_knowledge_post_types', array( $this, 'add_product_type' ) );
		add_filter( 'wpdsac_knowledge_post_content', array( $this, 'product_content' ), 10, 2 );
		add_filter( 'wpdsac_knowledge_post_source_type', array( $this, 'source_type' ), 10, 2 );
	}

	/**
	 * Include products only when WooCommerce APIs are available.
	 *
	 * @param array<int, string> $types Source post types.
	 * @return array<int, string>
	 */
	public function add_product_type( array $types ): array {
		if ( post_type_exists( 'product' ) && function_exists( 'wc_get_product' ) ) {
			$types[] = 'product';
		}

		return $types;
	}

	/**
	 * Build bounded public catalog text from a WooCommerce product.
	 *
	 * @param string   $content Default post content.
	 * @param \WP_Post $post    Source post.
	 * @return string
	 */
	public function product_content( string $content, \WP_Post $post ): string {
		if ( 'product' !== $post->post_type || ! function_exists( 'wc_get_product' ) ) {
			return $content;
		}

		$product = wc_get_product( $post->ID );

		if ( ! is_object( $product ) || ! is_callable( array( $product, 'is_visible' ) ) || ! $product->is_visible() ) {
			return '';
		}

		$parts = array(
			$post->post_title,
			$post->post_excerpt,
			$post->post_content,
		);

		if ( is_callable( array( $product, 'get_sku' ) ) && '' !== $product->get_sku() ) {
			/* translators: %s: public product SKU. */
			$parts[] = sprintf( __( 'SKU: %s', 'wp-ds-aichatbot' ), $product->get_sku() );
		}

		if ( is_callable( array( $product, 'get_price_html' ) ) && '' !== $product->get_price_html() ) {
			/* translators: %s: public formatted product price. */
			$parts[] = sprintf( __( 'Price: %s', 'wp-ds-aichatbot' ), wp_strip_all_tags( $product->get_price_html() ) );
		}

		if ( is_callable( array( $product, 'get_stock_status' ) ) ) {
			/* translators: %s: public product stock status. */
			$parts[] = sprintf( __( 'Stock status: %s', 'wp-ds-aichatbot' ), sanitize_key( $product->get_stock_status() ) );
		}

		$terms = get_the_terms( $post->ID, 'product_cat' );

		if ( is_array( $terms ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated public product category names. */
				__( 'Categories: %s', 'wp-ds-aichatbot' ),
				implode( ', ', wp_list_pluck( $terms, 'name' ) )
			);
		}

		return implode( "\n\n", array_filter( $parts ) );
	}

	/**
	 * Store products under their own source type.
	 *
	 * @param string   $source_type Existing source type.
	 * @param \WP_Post $post        Source post.
	 * @return string
	 */
	public function source_type( string $source_type, \WP_Post $post ): string {
		return 'product' === $post->post_type ? 'product' : $source_type;
	}
}
