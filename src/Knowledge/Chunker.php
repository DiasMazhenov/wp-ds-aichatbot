<?php
/**
 * Convert WordPress content into bounded knowledge fragments.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Produce compact text chunks without external dependencies.
 */
final class Chunker {

	private const MAX_CHARS = 1200;

	/**
	 * Normalize and split source content on word boundaries.
	 *
	 * @param string $content Raw WordPress content.
	 * @return array<int, string>
	 */
	public function split( string $content ): array {
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content, true );
		$charset = get_bloginfo( 'charset' );
		$charset = is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, $charset );
		$content = preg_replace( '/\s+/u', ' ', $content );
		$content = is_string( $content ) ? trim( $content ) : '';

		if ( '' === $content ) {
			return array();
		}

		$words = preg_split( '/\s+/u', $content );

		if ( ! is_array( $words ) ) {
			return array();
		}

		$chunks  = array();
		$current = '';

		foreach ( $words as $word ) {
			$candidate = '' === $current ? $word : $current . ' ' . $word;

			if ( self::length( $candidate ) <= self::MAX_CHARS ) {
				$current = $candidate;
				continue;
			}

			if ( '' !== $current ) {
				$chunks[] = $current;
			}

			$current = self::length( $word ) > self::MAX_CHARS
				? self::slice( $word, 0, self::MAX_CHARS )
				: $word;
		}

		if ( '' !== $current ) {
			$chunks[] = $current;
		}

		return array_slice( $chunks, 0, 200 );
	}

	/**
	 * Count Unicode characters when mbstring is available.
	 *
	 * @param string $value Text value.
	 * @return int
	 */
	private static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * Slice Unicode text when mbstring is available.
	 *
	 * @param string $value  Text value.
	 * @param int    $start  Start offset.
	 * @param int    $length Maximum length.
	 * @return string
	 */
	private static function slice( string $value, int $start, int $length ): string {
		return function_exists( 'mb_substr' )
			? mb_substr( $value, $start, $length )
			: substr( $value, $start, $length );
	}
}
