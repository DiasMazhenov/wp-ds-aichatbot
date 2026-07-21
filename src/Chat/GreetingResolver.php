<?php
/**
 * Resolve administrator-friendly greeting templates.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Convert supported time-of-day templates into a natural greeting.
 */
final class GreetingResolver {

	/**
	 * Resolve supported placeholders and common Russian choice notation.
	 *
	 * @param string   $text Greeting or provider response.
	 * @param int|null $hour Optional site-local hour for deterministic tests.
	 * @return string
	 */
	public static function resolve( string $text, ?int $hour = null ): string {
		if ( '' === trim( $text ) ) {
			return $text;
		}

		$hour       = null === $hour ? (int) current_time( 'G' ) : max( 0, min( 23, $hour ) );
		$is_russian = 1 === preg_match( '/[а-яё]/iu', $text );
		$greeting   = self::for_hour( $hour, $is_russian );
		$resolved   = str_ireplace( '{time_greeting}', $greeting, $text );
		$patterns   = array(
			'/доброе\s*\/\s*ый\s*\(\s*утро\s*[,\/]\s*день\s*[,\/]\s*вечер\s*[,\/]\s*ночь\s*\)\s*!?/iu',
			'/доброе\s*\/\s*добрый\s*\(\s*утро\s*[,\/]\s*день\s*[,\/]\s*вечер\s*[,\/]\s*ночь\s*\)\s*!?/iu',
			'/доброе\s+утро\s*\/\s*добрый\s+день\s*\/\s*добрый\s+вечер\s*\/\s*доброй\s+ночи\s*!?/iu',
		);

		foreach ( $patterns as $pattern ) {
			$replacement = preg_replace( $pattern, $greeting, $resolved );
			if ( is_string( $replacement ) ) {
				$resolved = $replacement;
			}
		}

		return $resolved;
	}

	/**
	 * Return a grammatically correct greeting for the site-local hour.
	 *
	 * @param int  $hour      Hour from 0 through 23.
	 * @param bool $is_russian Whether the source template is Russian.
	 * @return string
	 */
	private static function for_hour( int $hour, bool $is_russian ): string {
		if ( $hour >= 5 && $hour < 12 ) {
			return $is_russian ? 'Доброе утро!' : __( 'Good morning!', 'wp-ds-aichatbot' );
		}

		if ( $hour >= 12 && $hour < 17 ) {
			return $is_russian ? 'Добрый день!' : __( 'Good afternoon!', 'wp-ds-aichatbot' );
		}

		if ( $hour >= 17 && $hour < 23 ) {
			return $is_russian ? 'Добрый вечер!' : __( 'Good evening!', 'wp-ds-aichatbot' );
		}

		return $is_russian ? 'Доброй ночи!' : __( 'Good night!', 'wp-ds-aichatbot' );
	}
}
