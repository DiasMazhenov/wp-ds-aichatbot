<?php
/**
 * OpenRouter embeddings provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Generate embeddings through OpenRouter's OpenAI-compatible endpoint.
 */
final class OpenRouterEmbeddingsProvider implements EmbeddingsProviderInterface {

	/**
	 * Credential resolver.
	 *
	 * @var CredentialResolver
	 */
	private $credentials;

	/**
	 * Configured model identifier.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Store dependencies.
	 *
	 * @param CredentialResolver $credentials Credential resolver.
	 * @param string             $model       Optional OpenRouter embedding model.
	 */
	public function __construct( CredentialResolver $credentials, string $model = '' ) {
		$this->credentials = $credentials;
		$this->model       = '' !== trim( $model ) ? trim( $model ) : 'openai/text-embedding-3-small';
	}

	/**
	 * Return whether an OpenRouter key is available.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->credentials->get_api_key( 'openrouter' );
	}

	/**
	 * Generate a normalized embedding vector.
	 *
	 * @param string $text Text to embed.
	 * @return array<int, float>|null
	 */
	public function embed( string $text ): ?array {
		$api_key = $this->credentials->get_api_key( 'openrouter' );

		if ( '' === $api_key ) {
			return null;
		}

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/embeddings',
			array(
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url( '/' ),
					'X-Title'       => 'WP DS AI Chatbot',
				),
				'body'        => wp_json_encode(
					array(
						'model' => $this->model,
						'input' => $text,
					)
				),
				'timeout'     => 15,
				'redirection' => 0,
			)
		);

		return $this->vector_from_response( $response );
	}

	/**
	 * Extract and normalize a vector from an OpenAI-compatible response.
	 *
	 * @param array<string, mixed>|\WP_Error $response HTTP response.
	 * @return array<int, float>|null
	 */
	private function vector_from_response( $response ): ?array {
		if ( is_wp_error( $response ) || 200 !== absint( wp_remote_retrieve_response_code( $response ) ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['data'][0]['embedding'] ) || ! is_array( $data['data'][0]['embedding'] ) ) {
			return null;
		}

		return EmbeddingVector::normalize( array_map( 'floatval', $data['data'][0]['embedding'] ) );
	}
}
