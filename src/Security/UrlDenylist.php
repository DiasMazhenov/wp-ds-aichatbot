<?php
/**
 * Shared URL denylist for administrative and service endpoints.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for blocking admin/service URLs.
 */
final class UrlDenylist {

	/**
	 * Administrative and service path prefixes that must be blocked.
	 *
	 * @var array<int, string>
	 */
	private const BLOCKED_PREFIXES = array(
		'/wp-admin',
		'/wp-login.php',
		'/wp-cron.php',
		'/xmlrpc.php',
		'/wp-json',
		'admin-ajax.php',
		'admin-post.php',
	);

	/**
	 * Check whether a normalized URL path is an administrative or service endpoint.
	 *
	 * @param string $url Full URL.
	 * @return bool
	 */
	public static function is_blocked( string $url ): bool {
		$path = self::normalized_path( $url );

		if ( '' === $path ) {
			return false;
		}

		$decoded = self::decode_safely( $path );

		if ( '' === $decoded ) {
			return true; // Unparseable = reject.
		}

		foreach ( self::BLOCKED_PREFIXES as $prefix ) {
			if ( self::path_matches( $decoded, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Safely extract and normalize the path from a URL.
	 *
	 * @param string $url Full URL.
	 * @return string
	 */
	private static function normalized_path( string $url ): string {
		$parsed = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $parsed ) || '' === $parsed ) {
			return '';
		}

		// Collapse repeated slashes.
		$parsed = preg_replace( '#/{2,}#', '/', $parsed );

		if ( ! is_string( $parsed ) ) {
			return '';
		}

		// Replace backslashes.
		$parsed = str_replace( '\\', '/', $parsed );

		return strtolower( trim( $parsed ) );
	}

	/**
	 * Decode percent-encoded characters up to a bounded number of iterations.
	 *
	 * @param string $path Normalized path.
	 * @return string
	 */
	private static function decode_safely( string $path ): string {
		$decoded = $path;

		for ( $i = 0; $i < 3; $i++ ) {
			$previous = $decoded;
			$decoded  = rawurldecode( $decoded );

			// Re-normalize after decode (handle %2f → /).
			$decoded = preg_replace( '#/{2,}#', '/', $decoded );

			if ( ! is_string( $decoded ) || $decoded === $previous ) {
				break;
			}
		}

		return is_string( $decoded ) ? $decoded : '';
	}

	/**
	 * Check if a decoded path starts with a blocked prefix, handling dot segments.
	 *
	 * @param string $path   Decoded and normalized path.
	 * @param string $prefix Blocked prefix.
	 * @return bool
	 */
	private static function path_matches( string $path, string $prefix ): bool {
		// Exact match (e.g., /wp-login.php).
		if ( $path === $prefix ) {
			return true;
		}

		// Path starts with prefix followed by / (e.g., /wp-admin/...).
		$path_with_slash = $path . '/';
		$prefix_slash    = $prefix . '/';

		if ( 0 === strpos( $path_with_slash, $prefix_slash ) ) {
			return true;
		}

		// Check for dot segments that resolve into blocked paths.
		$resolved = self::resolve_dot_segments( $path );

		if ( $resolved === $prefix ) {
			return true;
		}

		$resolved_slash = $resolved . '/';

		return 0 === strpos( $resolved_slash, $prefix_slash );
	}

	/**
	 * Resolve . and .. dot segments in a URL path.
	 *
	 * @param string $path Path to resolve.
	 * @return string
	 */
	private static function resolve_dot_segments( string $path ): string {
		$parts = explode( '/', $path );
		$stack = array();

		foreach ( $parts as $part ) {
			if ( '..' === $part ) {
				array_pop( $stack );
			} elseif ( '.' !== $part && '' !== $part ) {
				$stack[] = $part;
			}
		}

		return '/' . implode( '/', $stack );
	}
}
