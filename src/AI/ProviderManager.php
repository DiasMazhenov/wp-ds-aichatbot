<?php
/**
 * AI provider selection and chat hook bridge.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

final class ProviderManager {

	private $default_provider;

	/**
	 * @param ProviderInterface $default_provider Default AI provider.
	 */
	public function __construct( ProviderInterface $default_provider ) {
		$this->default_provider = $default_provider;
	}

	/**
	 * Register the provider bridge.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wpdsac_chat_reply', array( $this, 'generate' ), 10, 4 );
	}

	/**
	 * Generate a reply unless an earlier integration already supplied one.
	 *
	 * @param mixed            $reply      Existing reply.
	 * @param string           $message    Visitor message.
	 * @param string           $session_id Verified session UUID.
	 * @param \WP_REST_Request $request    REST request.
	 * @return string|\WP_Error
	 */
	public function generate( $reply, string $message, string $session_id, \WP_REST_Request $request ) {
		if ( is_string( $reply ) || is_wp_error( $reply ) ) {
			return $reply;
		}

		/**
		 * Filter the provider used for a chat request.
		 *
		 * @param ProviderInterface $provider Default provider.
		 * @param \WP_REST_Request  $request  Current REST request.
		 */
		$provider = apply_filters( 'wpdsac_ai_provider', $this->default_provider, $request );

		if ( ! $provider instanceof ProviderInterface ) {
			return new \WP_Error(
				'wpdsac_invalid_provider',
				__( 'The configured AI provider is invalid.', 'wp-ds-aichatbot' ),
				array( 'status' => 503 )
			);
		}

		return $provider->generate( $message, $session_id );
	}
}
