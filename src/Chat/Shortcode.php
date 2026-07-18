<?php
/**
 * Chatbot shortcode integration.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Chat;

defined( 'ABSPATH' ) || exit;

final class Shortcode {

	private $renderer;

	/**
	 * @param Renderer $renderer Shared chatbot renderer.
	 */
	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'ds_ai_chatbot', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string, mixed>|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function render( $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'title'           => '',
				'welcome_message' => '',
			),
			is_array( $attributes ) ? $attributes : array(),
			'ds_ai_chatbot'
		);

		$attributes = array_filter(
			$attributes,
			static function ( $value ): bool {
				return '' !== $value;
			}
		);
		$attributes['expanded'] = true;

		return $this->renderer->render( $attributes );
	}
}

