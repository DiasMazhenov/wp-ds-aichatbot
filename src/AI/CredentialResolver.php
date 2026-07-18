<?php
/**
 * Resolve server-side OpenAI credentials.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve provider credentials from server-side sources.
 */
final class CredentialResolver {

	public const OPTION_NAME = 'wpdsac_openai_api_key';

	private const PROVIDERS = array(
		'openai'     => array(
			'constant'    => 'WPDSAC_OPENAI_API_KEY',
			'environment' => 'WPDSAC_OPENAI_API_KEY',
			'option'      => 'wpdsac_openai_api_key',
		),
		'anthropic'  => array(
			'constant'    => 'WPDSAC_ANTHROPIC_API_KEY',
			'environment' => 'WPDSAC_ANTHROPIC_API_KEY',
			'option'      => 'wpdsac_anthropic_api_key',
		),
		'gemini'     => array(
			'constant'    => 'WPDSAC_GEMINI_API_KEY',
			'environment' => 'WPDSAC_GEMINI_API_KEY',
			'option'      => 'wpdsac_gemini_api_key',
		),
		'openrouter' => array(
			'constant'    => 'WPDSAC_OPENROUTER_API_KEY',
			'environment' => 'WPDSAC_OPENROUTER_API_KEY',
			'option'      => 'wpdsac_openrouter_api_key',
		),
	);

	/**
	 * Return configuration for one supported provider.
	 *
	 * Resolve the API key without exposing it to public output.
	 *
	 * Priority: wp-config.php constant, environment, non-autoloaded option.
	 *
	 * @param string $provider Provider ID.
	 * @return string
	 */
	public function get_api_key( string $provider = 'openai' ): string {
		$config = $this->provider_config( $provider );

		if ( empty( $config ) ) {
			return '';
		}

		if ( defined( $config['constant'] ) ) {
			$constant_key = constant( $config['constant'] );

			if ( is_string( $constant_key ) && '' !== trim( $constant_key ) ) {
				return trim( $constant_key );
			}
		}

		$environment_key = getenv( $config['environment'] );

		if ( is_string( $environment_key ) && '' !== trim( $environment_key ) ) {
			return trim( $environment_key );
		}

		$stored_key = get_option( $config['option'], '' );

		return is_string( $stored_key ) ? trim( $stored_key ) : '';
	}

	/**
	 * Describe where the active credential comes from without returning it.
	 *
	 * @param string $provider Provider ID.
	 * @return string One of constant, environment, option or missing.
	 */
	public function source( string $provider = 'openai' ): string {
		$config = $this->provider_config( $provider );

		if ( empty( $config ) ) {
			return 'missing';
		}

		if ( defined( $config['constant'] ) ) {
			$constant_key = constant( $config['constant'] );

			if ( is_string( $constant_key ) && '' !== trim( $constant_key ) ) {
				return 'constant';
			}
		}

		$environment_key = getenv( $config['environment'] );

		if ( is_string( $environment_key ) && '' !== trim( $environment_key ) ) {
			return 'environment';
		}

		return '' !== $this->get_api_key( $provider ) ? 'option' : 'missing';
	}

	/**
	 * Return the option name for one direct provider.
	 *
	 * @param string $provider Provider ID.
	 * @return string
	 */
	public function option_name( string $provider ): string {
		$config = $this->provider_config( $provider );

		return $config['option'] ?? '';
	}

	/**
	 * Return supported direct provider IDs.
	 *
	 * @return array<int, string>
	 */
	public static function provider_ids(): array {
		return array_keys( self::PROVIDERS );
	}

	/**
	 * Return configuration for one supported provider.
	 *
	 * @param string $provider Provider ID.
	 * @return array<string, string>
	 */
	private function provider_config( string $provider ): array {
		return self::PROVIDERS[ $provider ] ?? array();
	}
}
