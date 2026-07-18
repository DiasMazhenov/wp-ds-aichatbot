<?php
/**
 * OpenAI Responses API provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

final class OpenAIProvider extends ResponsesApiProvider {

	/**
	 * @param CredentialResolver $credentials Credential resolver.
	 */
	public function __construct( CredentialResolver $credentials ) {
		parent::__construct( $credentials, 'openai', 'https://api.openai.com/v1/responses', 'openai_model' );
	}
}
