<?php
/**
 * Shared OpenResponses-compatible provider implementation.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Implement the common OpenResponses request and response format.
 */
class ResponsesApiProvider extends AbstractHttpProvider {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Provider API endpoint.
	 *
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * Settings key containing the selected model.
	 *
	 * @var string
	 */
	private $model_option;

	/**
	 * Configure an OpenResponses-compatible provider.
	 *
	 * @param CredentialResolver $credentials  Credential resolver.
	 * @param string             $id           Provider ID.
	 * @param string             $api_endpoint Fixed HTTPS endpoint.
	 * @param string             $model_option Settings key for the model ID.
	 */
	public function __construct( CredentialResolver $credentials, string $id, string $api_endpoint, string $model_option ) {
		parent::__construct( $credentials );
		$this->id           = $id;
		$this->api_endpoint = $api_endpoint;
		$this->model_option = $model_option;
	}

	/**
	 * Return the provider identifier.
	 *
	 * @return string
	 */
	protected function provider_id(): string {
		return $this->id;
	}

	/**
	 * Return the configured API endpoint.
	 *
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	protected function endpoint( array $options ): string {
		return $this->api_endpoint;
	}

	/**
	 * Build an OpenResponses-compatible request body.
	 *
	 * @param string               $message    Visitor message.
	 * @param string               $session_id Session UUID.
	 * @param array<string, mixed> $options    Plugin settings.
	 * @return array<string, mixed>
	 */
	protected function request_body( string $message, string $session_id, array $options ): array {
		return array(
			'model'             => (string) $options[ $this->model_option ],
			'instructions'      => (string) $options['ai_instructions'],
			'input'             => $message,
			'max_output_tokens' => (int) $options['ai_max_output_tokens'],
			'store'             => false,
		);
	}

	/**
	 * Extract text from an OpenResponses-compatible response.
	 *
	 * @param array<string, mixed> $response Decoded response.
	 * @return string
	 */
	protected function extract_output_text( array $response ): string {
		if ( isset( $response['output_text'] ) && is_string( $response['output_text'] ) ) {
			return $response['output_text'];
		}

		$parts = array();

		foreach ( is_array( $response['output'] ?? null ) ? $response['output'] : array() as $item ) {
			foreach ( is_array( $item['content'] ?? null ) ? $item['content'] : array() as $content ) {
				if ( is_array( $content ) && 'output_text' === ( $content['type'] ?? '' ) && is_string( $content['text'] ?? null ) ) {
					$parts[] = $content['text'];
				}
			}
		}

		return implode( "\n", $parts );
	}
}
