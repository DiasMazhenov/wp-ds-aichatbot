<?php
/**
 * Embeddings provider contract.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Generate a vector embedding for a text input.
 */
interface EmbeddingsProviderInterface {

	/**
	 * Return whether the provider has usable credentials.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Convert text to a fixed-length float vector.
	 *
	 * @param string $text Input text to embed.
	 * @return array<int, float>|null Normalized vector, or null on failure.
	 */
	public function embed( string $text ): ?array;
}
