<?php
/**
 * Minimal PSR-4-compatible autoloader.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Load plugin classes from the PSR-4 namespace tree.
 */
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
	 * @param string $class_name Fully-qualified class name.
	 * @return void
	 */
	private static function load( string $class_name ): void {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( self::PREFIX ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
		$file           = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . $relative_path;

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
