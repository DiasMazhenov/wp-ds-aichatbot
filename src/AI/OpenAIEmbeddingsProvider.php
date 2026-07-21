<?php
/**
 * OpenAI embeddings via the dedicated embeddings endpoint.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Call the OpenAI embeddings API and return a normalized vector.
 */
final class OpenAIEmbeddingsProvider implements EmbeddingsProviderInterface {

	/**
	 * Credential resolver.
	 *
	 * @var CredentialResolver
	 */
	private $credentials;

	/**
	 * Store dependencies.
	 *
	 * @param CredentialResolver $credentials Credential resolver.
	 */
	public function __construct( CredentialResolver $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Generate an embedding vector via OpenAI API.
	 *
	 * @param string $text Text to embed.
	 * @return array<int, float>|null
	 */
	public function embed( string $text ): ?array {
		$api_key = $this->credentials->resolve( 'openai' );

		if ( '' === $api_key ) {
			return null;
		}

		$options  = Settings::get();
		$model    = (string) ( $options['embeddings_model'] ?? 'text-embedding-3-small' );
		$url      = 'https://api.openai.com/v1/embeddings';
		$body     = wp_json_encode(
			array(
				'model' => $model,
				'input' => $text,
			)
		);
		$response = wp_remote_post(
			$url,
			array(
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'        => $body,
				'timeout'     => 15,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = absint( wp_remote_retrieve_response_code( $response ) );

		if ( 200 !== $code ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['data'][0]['embedding'] ) || ! is_array( $data['data'][0]['embedding'] ) ) {
			return null;
		}

		$vector = array_map( 'floatval', $data['data'][0]['embedding'] );

		return $this->normalize( $vector );
	}

	/**
	 * L2-normalize a float vector to unit length.
	 *
	 * @param array<int, float> $vector Raw vector.
	 * @return array<int, float>
	 */
	private function normalize( array $vector ): array {
		$sum = 0.0;

		foreach ( $vector as $v ) {
			$sum += $v * $v;
		}

		$magnitude = sqrt( $sum );

		if ( 0.0 === $magnitude ) {
			return $vector;
		}

		return array_map(
			function ( float $v ) use ( $magnitude ): float {
				return $v / $magnitude;
			},
			$vector
		);
	}
}
