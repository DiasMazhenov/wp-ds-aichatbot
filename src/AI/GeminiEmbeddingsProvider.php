<?php
/**
 * Google Gemini embeddings provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Generate embeddings through Gemini embedContent.
 */
final class GeminiEmbeddingsProvider implements EmbeddingsProviderInterface {

	/**
	 * Credential resolver.
	 *
	 * @var CredentialResolver
	 */
	private $credentials;

	/**
	 * Gemini embedding model without the models/ prefix.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Store dependencies.
	 *
	 * @param CredentialResolver $credentials Credential resolver.
	 * @param string             $model       Optional Gemini embedding model.
	 */
	public function __construct( CredentialResolver $credentials, string $model = '' ) {
		$this->credentials = $credentials;
		$model             = preg_replace( '#^models/#', '', trim( $model ) );
		$this->model       = is_string( $model ) && '' !== $model ? $model : 'gemini-embedding-001';
	}

	/**
	 * Return whether a Gemini key is available.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->credentials->get_api_key( 'gemini' );
	}

	/**
	 * Generate a normalized embedding vector.
	 *
	 * @param string $text Text to embed.
	 * @return array<int, float>|null
	 */
	public function embed( string $text ): ?array {
		$api_key = $this->credentials->get_api_key( 'gemini' );

		if ( '' === $api_key ) {
			return null;
		}

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $this->model ) . ':embedContent',
			array(
				'headers'     => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $api_key,
				),
				'body'        => wp_json_encode(
					array(
						'model'   => 'models/' . $this->model,
						'content' => array(
							'parts' => array( array( 'text' => $text ) ),
						),
					)
				),
				'timeout'     => 15,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) || 200 !== absint( wp_remote_retrieve_response_code( $response ) ) ) {
			return null;
		}

		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$vector = $data['embedding']['values'] ?? $data['embeddings'][0]['values'] ?? null;

		if ( ! is_array( $vector ) ) {
			return null;
		}

		return EmbeddingVector::normalize( array_map( 'floatval', $vector ) );
	}
}
