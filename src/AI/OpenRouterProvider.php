<?php
/**
 * OpenRouter OpenResponses provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Generate replies through the OpenRouter OpenResponses API.
 */
final class OpenRouterProvider extends ResponsesApiProvider {

	/**
	 * Configure the OpenRouter endpoint and model setting.
	 *
	 * @param CredentialResolver $credentials Credential resolver.
	 */
	public function __construct( CredentialResolver $credentials ) {
		parent::__construct( $credentials, 'openrouter', 'https://openrouter.ai/api/v1/responses', 'openrouter_model' );
	}

	/**
	 * Add OpenRouter attribution headers.
	 *
	 * @param string $api_key Provider API key.
	 * @return array<string, string>
	 */
	protected function request_headers( string $api_key ): array {
		$headers = parent::request_headers( $api_key );

		$headers['HTTP-Referer']       = home_url( '/' );
		$headers['X-OpenRouter-Title'] = get_bloginfo( 'name' );

		return $headers;
	}
}
