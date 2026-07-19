<?php
/**
 * Elementor page-content knowledge adapter.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Add rendered Elementor widgets to the searchable page source.
 */
final class ElementorSource {

	/**
	 * Register the source filter.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wpdsac_knowledge_post_content', array( $this, 'content' ), 20, 2 );
	}

	/**
	 * Append rendered or extracted Elementor widget text.
	 *
	 * @param string   $content Existing source content.
	 * @param \WP_Post $post    Source post.
	 * @return string
	 */
	public function content( string $content, \WP_Post $post ): string {
		$raw_data = get_post_meta( $post->ID, '_elementor_data', true );

		if ( ! is_string( $raw_data ) || '' === trim( $raw_data ) ) {
			return $content;
		}

		$rendered = $this->rendered_content( $post->ID );

		if ( '' !== $rendered ) {
			return $content . "\n\n" . $rendered;
		}

		$data = json_decode( $raw_data, true );

		if ( ! is_array( $data ) ) {
			return $content;
		}

		$parts = array();
		$this->extract_text( $data, '', $parts );

		return array() === $parts ? $content : $content . "\n\n" . implode( "\n", array_unique( $parts ) );
	}

	/**
	 * Ask Elementor for frontend HTML when its runtime is available.
	 *
	 * @param int $post_id Source post ID.
	 * @return string
	 */
	private function rendered_content( int $post_id ): string {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance->frontend ) ) {
			return '';
		}

		try {
			$rendered = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id, true );
		} catch ( \Throwable $error ) {
			unset( $error );
			return '';
		}

		return is_string( $rendered ) ? $rendered : '';
	}

	/**
	 * Extract user-facing fallback strings from Elementor JSON settings.
	 *
	 * @param mixed              $value Extracted JSON value.
	 * @param string             $key   Current setting key.
	 * @param array<int, string> $parts Collected strings.
	 * @return void
	 */
	private function extract_text( $value, string $key, array &$parts ): void {
		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child ) {
				$this->extract_text( $child, is_string( $child_key ) ? $child_key : $key, $parts );
			}
			return;
		}

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return;
		}

		if ( 1 === preg_match( '/(title|heading|text|editor|content|description|caption|label|button|question|answer|html|price|feature|item|name)/i', $key ) ) {
			$parts[] = $value;
		}
	}
}
