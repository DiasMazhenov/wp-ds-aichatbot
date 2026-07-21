<?php
/**
 * Shared embedding vector operations.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Normalize provider vectors before persistence and comparison.
 */
final class EmbeddingVector {

	/**
	 * L2-normalize a vector to unit length.
	 *
	 * @param array<int, float> $vector Raw vector.
	 * @return array<int, float>
	 */
	public static function normalize( array $vector ): array {
		$sum = 0.0;

		foreach ( $vector as $value ) {
			$sum += $value * $value;
		}

		$magnitude = sqrt( $sum );

		if ( 0.0 === $magnitude ) {
			return $vector;
		}

		return array_map(
			static function ( float $value ) use ( $magnitude ): float {
				return $value / $magnitude;
			},
			$vector
		);
	}
}
