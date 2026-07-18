<?php
/**
 * Resolve server-side OpenAI credentials.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

final class CredentialResolver {

	public const OPTION_NAME = 'wpdsac_openai_api_key';

	/**
	 * Resolve the API key without exposing it to public output.
	 *
	 * Priority: wp-config.php constant, environment, non-autoloaded option.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		if ( defined( 'WPDSAC_OPENAI_API_KEY' ) && is_string( WPDSAC_OPENAI_API_KEY ) ) {
			return trim( WPDSAC_OPENAI_API_KEY );
		}

		$environment_key = getenv( 'WPDSAC_OPENAI_API_KEY' );

		if ( is_string( $environment_key ) && '' !== trim( $environment_key ) ) {
			return trim( $environment_key );
		}

		$stored_key = get_option( self::OPTION_NAME, '' );

		return is_string( $stored_key ) ? trim( $stored_key ) : '';
	}

	/**
	 * Describe where the active credential comes from without returning it.
	 *
	 * @return string One of constant, environment, option or missing.
	 */
	public function source(): string {
		if ( defined( 'WPDSAC_OPENAI_API_KEY' ) && is_string( WPDSAC_OPENAI_API_KEY ) && '' !== trim( WPDSAC_OPENAI_API_KEY ) ) {
			return 'constant';
		}

		$environment_key = getenv( 'WPDSAC_OPENAI_API_KEY' );

		if ( is_string( $environment_key ) && '' !== trim( $environment_key ) ) {
			return 'environment';
		}

		return '' !== $this->get_api_key() ? 'option' : 'missing';
	}
}
