<?php
/**
 * Minimal PSR-4-compatible autoloader.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Support;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	private const PREFIX = 'DiasMazhenov\\WPDsAiChatbot\\';

	/**
	 * Register the project autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load one plugin class.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	private static function load( string $class ): void {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::PREFIX ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
		$file           = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . $relative_path;

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}

