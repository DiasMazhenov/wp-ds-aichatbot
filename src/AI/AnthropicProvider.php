<?php
/**
 * Anthropic Messages API provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Generate replies through the Anthropic Messages API.
 */
final class AnthropicProvider extends AbstractHttpProvider {

	/**
	 * Return the provider identifier.
	 *
	 * @return string
	 */
	protected function provider_id(): string {
		return 'anthropic';
	}

	/**
	 * Return the Anthropic Messages endpoint.
	 *
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	protected function endpoint( array $options ): string {
		return 'https://api.anthropic.com/v1/messages';
	}

	/**
	 * Build an Anthropic Messages request body.
	 *
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
	 * Build Anthropic authentication headers.
	 *
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
	 * Whether this provider implements real SSE streaming.
	 *
	 * @return bool
	 */
	protected function supports_streaming(): bool {
		return true;
	}

	/**
	 * Extract text from an Anthropic Messages SSE chunk.
	 *
	 * Event: content_block_delta
	 * data: {"type":"content_block_delta","delta":{"text":"...","type":"text_delta"}}
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

			$type = $json['type'] ?? '';

			if ( 'content_block_delta' !== $type ) {
				continue;
			}

			$event_delta = $json['delta'] ?? null;

			if ( ! is_array( $event_delta ) ) {
				continue;
			}

			// text_delta event.
			if ( 'text_delta' === ( $event_delta['type'] ?? '' ) && is_string( $event_delta['text'] ?? '' ) ) {
				$delta .= $event_delta['text'];
			}
		}

		return $delta;
	}

	/**
	 * Extract text blocks from an Anthropic response.
	 *
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
