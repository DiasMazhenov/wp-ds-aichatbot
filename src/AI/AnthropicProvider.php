<?php
/**
 * Anthropic Messages API provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

final class AnthropicProvider extends AbstractHttpProvider {

	/** @return string */
	protected function provider_id(): string {
		return 'anthropic';
	}

	/**
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	protected function endpoint( array $options ): string {
		return 'https://api.anthropic.com/v1/messages';
	}

	/**
	 * @param string               $message    Visitor message.
	 * @param string               $session_id Session UUID.
	 * @param array<string, mixed> $options    Plugin settings.
	 * @return array<string, mixed>
	 */
	protected function request_body( string $message, string $session_id, array $options ): array {
		return array(
			'model'      => (string) $options['anthropic_model'],
			'max_tokens' => (int) $options['ai_max_output_tokens'],
			'system'     => (string) $options['ai_instructions'],
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $message,
				),
			),
		);
	}

	/**
	 * @param string $api_key Provider API key.
	 * @return array<string, string>
	 */
	protected function request_headers( string $api_key ): array {
		return array(
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
			'Content-Type'      => 'application/json',
		);
	}

	/**
	 * @param array<string, mixed> $response Decoded response.
	 * @return string
	 */
	protected function extract_output_text( array $response ): string {
		$parts = array();

		foreach ( is_array( $response['content'] ?? null ) ? $response['content'] : array() as $content ) {
			if ( is_array( $content ) && 'text' === ( $content['type'] ?? '' ) && is_string( $content['text'] ?? null ) ) {
				$parts[] = $content['text'];
			}
		}

		return implode( "\n", $parts );
	}
}
