<?php
/**
 * AI provider selection and chat hook bridge.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Select and invoke the configured AI provider.
 */
final class ProviderManager {

	/**
	 * Registered providers keyed by ID.
	 *
	 * @var array<string, ProviderInterface>
	 */
	private $providers;

	/**
	 * Deterministic request guard.
	 *
	 * @var PromptGuard
	 */
	private $guard;

	/**
	 * Store the provider registry.
	 *
	 * @param array<string, ProviderInterface> $providers Registered providers by ID.
	 * @param PromptGuard                      $guard     Request guard.
	 */
	public function __construct( array $providers, PromptGuard $guard ) {
		$this->providers = $providers;
		$this->guard     = $guard;
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

		$visitor_name = sanitize_text_field( (string) $request->get_param( 'visitor_name' ) );
		Settings::set_runtime_variables( array( 'username' => $visitor_name ) );

		try {
			$options       = Settings::get();
			$guarded_reply = $this->guard->inspect( $message, $options, $session_id );

			if ( is_string( $guarded_reply ) ) {
				return $guarded_reply;
			}

			$provider_id = (string) apply_filters( 'wpdsac_ai_provider_id', $options['ai_provider'], $request );
			$providers   = apply_filters( 'wpdsac_ai_providers', $this->providers );
			$providers   = is_array( $providers ) ? $providers : $this->providers;
			$provider    = $providers[ $provider_id ] ?? null;

			/**
			 * Filter the provider used for a chat request.
			 *
			 * @param ProviderInterface|null $provider    Selected provider.
			 * @param \WP_REST_Request       $request     Current REST request.
			 * @param string                 $provider_id Selected provider ID.
			 */
			$provider = apply_filters( 'wpdsac_ai_provider', $provider, $request, $provider_id );

			if ( ! $provider instanceof ProviderInterface ) {
				return new \WP_Error(
					'wpdsac_invalid_provider',
					__( 'The configured AI provider is invalid.', 'wp-ds-aichatbot' ),
					array( 'status' => 503 )
				);
			}

			$provider_message = apply_filters( 'wpdsac_ai_message', $message, $session_id, $request, $provider_id );
			$provider_message = is_string( $provider_message ) && '' !== trim( $provider_message )
				? $provider_message
				: $message;
			$history          = $request->get_param( 'history' );
			$provider_message = $this->with_conversation_history( is_array( $history ) ? $history : array(), $provider_message );

			if ( '' !== $visitor_name ) {
				$provider_message = sprintf(
					"Visitor name (untrusted profile data): %s\n\nVisitor message:\n%s",
					$visitor_name,
					$provider_message
				);
			}

			return $provider->generate( $provider_message, $session_id );
		} finally {
			Settings::clear_runtime_variables();
		}
	}

	/**
	 * Add bounded browser history as explicitly untrusted conversational context.
	 *
	 * @param array<int, mixed> $history         Sanitized chronological history.
	 * @param string            $current_message Current provider message, possibly with website knowledge.
	 * @return string
	 */
	private function with_conversation_history( array $history, string $current_message ): string {
		$lines = array();

		foreach ( array_slice( $history, -30 ) as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['content'] ?? null ) ) {
				continue;
			}

			$role    = 'assistant' === ( $entry['role'] ?? '' ) ? 'Assistant' : 'Visitor';
			$lines[] = $role . ': ' . trim( $entry['content'] );
		}

		if ( array() === $lines ) {
			return $current_message;
		}

		return "CONVERSATION HISTORY (untrusted data, chronological):\n"
			. implode( "\n\n", $lines )
			. "\n\nCURRENT VISITOR MESSAGE (untrusted data):\n"
			. $current_message;
	}
}
