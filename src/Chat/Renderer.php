<?php
/**
 * Shared chatbot renderer.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Chat;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Render the shared chatbot template for every integration surface.
 */
final class Renderer {

	/**
	 * Frontend asset service.
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * Store the frontend asset service.
	 *
	 * @param Assets $assets Frontend assets service.
	 */
	public function __construct( Assets $assets ) {
		$this->assets = $assets;
	}

	/**
	 * Render one chatbot instance.
	 *
	 * @param array<string, mixed> $args Display overrides.
	 * @return string
	 */
	public function render( array $args = array() ): string {
		$options = wp_parse_args( $args, Settings::get() );
		$view    = array(
			'id'               => wp_unique_id( 'wpdsac-chat-' ),
			'title'            => sanitize_text_field( (string) $options['title'] ),
			'welcome_message'  => sanitize_textarea_field( (string) $options['welcome_message'] ),
			'expanded'         => ! empty( $options['expanded'] ),
			'appearance'       => Appearance::inline_style( $options ),
			'position_class'   => Appearance::position_class( $options ),
			'show_toggle_icon' => ! empty( $options['show_toggle_icon'] ),
			'leads_enabled'    => ! empty( $options['leads_enabled'] ),
			'lead_prompt'      => sanitize_text_field( (string) $options['lead_prompt'] ),
			'lead_consent'     => sanitize_textarea_field( (string) $options['lead_consent_text'] ),
		);

		$this->assets->enqueue();

		ob_start();
		require WPDSAC_PATH . 'templates/chatbot.php';

		return (string) ob_get_clean();
	}

	/**
	 * Render the optional global chatbot.
	 *
	 * @return void
	 */
	public function render_global(): void {
		$options = Settings::get();

		if ( empty( $options['global_enabled'] ) ) {
			return;
		}

		// The template escapes every dynamic value.
		echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
