<?php
/**
 * Frontend asset registration.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Register and conditionally enqueue the frontend bundle.
 */
final class Assets {

	/**
	 * Whether frontend configuration has been localized.
	 *
	 * @var bool
	 */
	private $localized = false;

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

		if ( $this->localized ) {
			return;
		}

		wp_localize_script(
			'wpdsac-chat',
			'wpdsacChatConfig',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'wp-ds-aichatbot/v1' ) ),
				'restNonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'strings'   => array(
					'connecting'       => __( 'Connecting…', 'wp-ds-aichatbot' ),
					'sending'          => __( 'Sending…', 'wp-ds-aichatbot' ),
					'error'            => __( 'The chat request failed. Please try again.', 'wp-ds-aichatbot' ),
					'leadSaving'       => __( 'Saving contact details…', 'wp-ds-aichatbot' ),
					'leadError'        => __( 'Contact details could not be saved. Please try again.', 'wp-ds-aichatbot' ),
					'leadNameInvalid'  => __( 'Please enter your name.', 'wp-ds-aichatbot' ),
					'leadAskPhone'     => __( 'Great! Now please enter your phone number.', 'wp-ds-aichatbot' ),
					'leadPhoneInvalid' => __( 'Invalid number. Enter your phone, e.g.: 8 900 123-45-67', 'wp-ds-aichatbot' ),
					'leadSaved'        => __( 'Thank you! We will contact you shortly.', 'wp-ds-aichatbot' ),
					'navigate'         => __( 'Go to', 'wp-ds-aichatbot' ),
				),
			)
		);

		$this->localized = true;
	}
}
