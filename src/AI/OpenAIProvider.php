<?php
/**
 * OpenAI Responses API provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Generate replies through the OpenAI Responses API.
 */
final class OpenAIProvider extends ResponsesApiProvider {

	/**
	 * Configure the OpenAI endpoint and model setting.
	 *
	 * @param CredentialResolver $credentials Credential resolver.
	 */
	public function __construct( CredentialResolver $credentials ) {
		parent::__construct( $credentials, 'openai', 'https://api.openai.com/v1/responses', 'openai_model' );
	}
}
