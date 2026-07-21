<?php
/**
 * Resolve the configured embeddings provider independently from chat.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Build a supported embeddings adapter without coupling it to the chat provider.
 */
final class EmbeddingsProviderFactory {

	private const SUPPORTED = array( 'openai', 'gemini', 'openrouter' );

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
	 * Create the selected configured provider, or null for keyword-only retrieval.
	 *
	 * @return EmbeddingsProviderInterface|null
	 */
	public function create(): ?EmbeddingsProviderInterface {
		$options  = Settings::get();
		$provider = $this->resolve_provider_id( (string) ( $options['embeddings_provider'] ?? 'auto' ), (string) ( $options['ai_provider'] ?? '' ) );
		$model    = sanitize_text_field( (string) ( $options['embeddings_model'] ?? '' ) );

		if ( '' === $provider ) {
			return null;
		}

		if ( 'gemini' === $provider ) {
			return new GeminiEmbeddingsProvider( $this->credentials, $this->model_for_provider( $provider, $model ) );
		}

		if ( 'openrouter' === $provider ) {
			return new OpenRouterEmbeddingsProvider( $this->credentials, $this->model_for_provider( $provider, $model ) );
		}

		return new OpenAIEmbeddingsProvider( $this->credentials, $this->model_for_provider( $provider, $model ) );
	}

	/**
	 * Resolve an explicit or automatic configured provider ID.
	 *
	 * @param string $selected      Embeddings selection.
	 * @param string $chat_provider Active chat provider.
	 * @return string
	 */
	private function resolve_provider_id( string $selected, string $chat_provider ): string {
		$selected = sanitize_key( $selected );

		if ( in_array( $selected, self::SUPPORTED, true ) ) {
			return '' !== $this->credentials->get_api_key( $selected ) ? $selected : '';
		}

		$ordered = in_array( $chat_provider, self::SUPPORTED, true )
			? array_merge( array( $chat_provider ), self::SUPPORTED )
			: self::SUPPORTED;

		foreach ( array_unique( $ordered ) as $provider ) {
			if ( '' !== $this->credentials->get_api_key( $provider ) ) {
				return $provider;
			}
		}

		return '';
	}

	/**
	 * Return a safe provider-specific default when the shared field is empty or stale.
	 *
	 * @param string $provider Provider ID.
	 * @param string $model    Saved model.
	 * @return string
	 */
	private function model_for_provider( string $provider, string $model ): string {
		$defaults = array(
			'openai'     => 'text-embedding-3-small',
			'gemini'     => 'gemini-embedding-001',
			'openrouter' => 'openai/text-embedding-3-small',
		);

		if ( '' === $model || ( 'openai' !== $provider && 'text-embedding-3-small' === $model ) ) {
			return $defaults[ $provider ];
		}

		return $model;
	}
}
