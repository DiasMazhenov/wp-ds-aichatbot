<?php
/**
 * Google Gemini Interactions API provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

final class GeminiProvider extends AbstractHttpProvider {

	/** @return string */
	protected function provider_id(): string {
		return 'gemini';
	}

	/**
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	protected function endpoint( array $options ): string {
		return 'https://generativelanguage.googleapis.com/v1/interactions';
	}

	/**
	 * @param string               $message    Visitor message.
	 * @param string               $session_id Session UUID.
	 * @param array<string, mixed> $options    Plugin settings.
	 * @return array<string, mixed>
	 */
	protected function request_body( string $message, string $session_id, array $options ): array {
		return array(
			'model'              => (string) $options['gemini_model'],
			'input'              => $message,
			'system_instruction' => (string) $options['ai_instructions'],
			'store'              => false,
			'generation_config'  => array(
				'max_output_tokens' => (int) $options['ai_max_output_tokens'],
			),
		);
	}

	/**
	 * @param string $api_key Provider API key.
	 * @return array<string, string>
	 */
	protected function request_headers( string $api_key ): array {
		return array(
			'x-goog-api-key' => $api_key,
			'Content-Type'   => 'application/json',
		);
	}

	/**
	 * @param array<string, mixed> $response Decoded response.
	 * @return string
	 */
	protected function extract_output_text( array $response ): string {
		$parts = array();

		foreach ( is_array( $response['steps'] ?? null ) ? $response['steps'] : array() as $step ) {
			if ( ! is_array( $step ) || 'model_output' !== ( $step['type'] ?? '' ) ) {
				continue;
			}

			foreach ( is_array( $step['content'] ?? null ) ? $step['content'] : array() as $content ) {
				if ( is_array( $content ) && 'text' === ( $content['type'] ?? '' ) && is_string( $content['text'] ?? null ) ) {
					$parts[] = $content['text'];
				}
			}
		}

		return implode( "\n", $parts );
	}
}
