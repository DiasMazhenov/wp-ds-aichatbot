<?php
/**
 * WordPress 7.0 provider-agnostic AI Client adapter.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Generate replies through the WordPress provider-agnostic AI Client.
 */
final class WordPressAiClientProvider implements ProviderInterface {

	/**
	 * Generate through the provider configured in WordPress AI Connectors.
	 *
	 * @param string $message    Sanitized visitor message.
	 * @param string $session_id Verified internal session UUID.
	 * @return string|\WP_Error
	 */
	public function generate( string $message, string $session_id ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new \WP_Error(
				'wpdsac_wordpress_ai_unavailable',
				__( 'WordPress AI Client requires WordPress 7.0 or newer and a configured connector.', 'wp-ds-aichatbot' ),
				array( 'status' => 503 )
			);
		}

		$options                    = Settings::get();
		$options['ai_instructions'] = PromptGuard::protected_instructions(
			(string) $options['ai_instructions'],
			(string) $options['topic_scope'],
			(string) $options['guard_refusal_message']
		);
		$result                     = wp_ai_client_prompt( $message )
			->using_system_instruction( (string) $options['ai_instructions'] )
			->using_max_tokens( (int) $options['ai_max_output_tokens'] )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 0 ) : 0;

			do_action(
				'wpdsac_ai_provider_error',
				array(
					'provider'   => 'wordpress_ai',
					'status'     => $status,
					'request_id' => '',
					'error_code' => sanitize_key( $result->get_error_code() ),
				)
			);

			return new \WP_Error(
				'wpdsac_wordpress_ai_error',
				__( 'The AI service is temporarily unavailable.', 'wp-ds-aichatbot' ),
				array( 'status' => 502 )
			);
		}

		return is_string( $result ) ? $result : '';
	}
}
