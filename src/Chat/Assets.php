<?php
/**
 * Frontend asset registration.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Chat;

defined( 'ABSPATH' ) || exit;

final class Assets {

	/**
	 * Register the asset hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ) );
	}

	/**
	 * Register assets without enqueueing them globally.
	 *
	 * @return void
	 */
	public function register(): void {
		wp_register_style(
			'wpdsac-chat',
			WPDSAC_URL . 'assets/build/chat.css',
			array(),
			WPDSAC_VERSION
		);

		wp_register_script(
			'wpdsac-chat',
			WPDSAC_URL . 'assets/build/chat.js',
			array(),
			WPDSAC_VERSION,
			true
		);
	}

	/**
	 * Enqueue assets when a chatbot is rendered.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! wp_style_is( 'wpdsac-chat', 'registered' ) ) {
			$this->register();
		}

		wp_enqueue_style( 'wpdsac-chat' );
		wp_enqueue_script( 'wpdsac-chat' );
	}
}

