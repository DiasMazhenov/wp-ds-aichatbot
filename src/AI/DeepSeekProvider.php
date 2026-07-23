<?php
/**
 * DeepSeek Chat Completions provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Generate replies through the native DeepSeek API.
 */
final class DeepSeekProvider extends AbstractHttpProvider {

	/**
	 * Return the provider identifier.
	 *
	 * @return string
	 */
	protected function provider_id(): string {
		return 'deepseek';
	}

	/**
	 * Return the DeepSeek Chat Completions endpoint.
	 *
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	protected function endpoint( array $options ): string {
		return 'https://api.deepseek.com/chat/completions';
	}

	/**
	 * Build a DeepSeek Chat Completions request body.
	 *
	 * @param string               $message    Visitor message.
	 * @param string               $session_id Session UUID.
	 * @param array<string, mixed> $options    Plugin settings.
	 * @return array<string, mixed>
	 */
	protected function request_body( string $message, string $session_id, array $options ): array {
		return array(
			'model'      => (string) $options['deepseek_model'],
			'messages'   => array(
				array(
					'role'    => 'system',
					'content' => (string) $options['ai_instructions'],
				),
				array(
					'role'    => 'user',
					'content' => $message,
				),
			),
			'thinking'   => array(
				'type' => ! empty( $options['deepseek_thinking'] ) ? 'enabled' : 'disabled',
			),
			'max_tokens' => (int) $options['ai_max_output_tokens'],
			'stream'     => false,
		);
	}

	/**
	 * Whether this provider implements real SSE streaming.
	 *
	 * @return bool
	 */
	protected function supports_streaming(): bool {
		return true;
	}

	/**
	 * Extract text from a DeepSeek Chat Completions SSE chunk.
	 *
	 * Format: choices[0].delta.content
	 * Also extracts reasoning_content from thinking blocks.
	 *
	 * @param string $raw Raw curl chunk.
	 * @return string
	 */
	protected static function extract_stream_delta( string $raw ): string {
		$delta = '';
		$lines = explode( "\n", $raw );

		foreach ( $lines as $line ) {
			if ( 0 !== strpos( $line, 'data: ' ) ) {
				continue;
			}

			$json_str = substr( $line, 6 );

			if ( '' === $json_str || 'null' === $json_str || '[DONE]' === $json_str ) {
				continue;
			}

			$json = json_decode( $json_str, true );

			if ( ! is_array( $json ) ) {
				continue;
			}

			$choice = $json['choices'][0] ?? null;

			if ( ! is_array( $choice ) ) {
				continue;
			}

			$choice_delta = $choice['delta'] ?? null;

			if ( ! is_array( $choice_delta ) ) {
				continue;
			}

			// Content chunk.
			if ( isset( $choice_delta['content'] ) && is_string( $choice_delta['content'] ) ) {
				$delta .= $choice_delta['content'];
				continue;
			}

			// Reasoning content (thinking) — emit as inline text.
			if ( isset( $choice_delta['reasoning_content'] ) && is_string( $choice_delta['reasoning_content'] ) ) {
				$delta .= $choice_delta['reasoning_content'];
			}
		}

		return $delta;
	}

	/**
	 * Extract the final answer without exposing reasoning content.
	 *
	 * @param array<string, mixed> $response Decoded response.
	 * @return string
	 */
	protected function extract_output_text( array $response ): string {
		$content = $response['choices'][0]['message']['content'] ?? '';

		return is_string( $content ) ? $content : '';
	}
}
