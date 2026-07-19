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
