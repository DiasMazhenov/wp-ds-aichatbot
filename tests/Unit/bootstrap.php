<?php
/**
 * Minimal WordPress function surface for pure unit tests.
 *
 * @package WPDsAiChatbotTests
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WPDSAC_PATH', dirname( __DIR__, 2 ) . '/' );
define( 'WPDSAC_VERSION', '0.5.69' );
define( 'WPDSAC_FILE', WPDSAC_PATH . 'wp-ds-aichatbot.php' );

$GLOBALS['wpdsac_test_options'] = array();
$GLOBALS['wpdsac_test_settings_errors'] = array();

final class WP_Error {

	private $code;

	public function __construct( string $code ) {
		$this->code = $code;
	}

	public function get_error_code(): string {
		return $this->code;
	}
}

function __( string $text ): string {
	return $text;
}

function wp_generate_uuid4(): string {
	return '123e4567-e89b-42d3-a456-426614174000';
}

function wp_json_encode( $value ): string {
	return (string) json_encode( $value );
}

function wp_salt( string $scheme = 'auth' ): string {
	return 'unit-test-' . $scheme . '-salt';
}

function wp_is_uuid( $uuid, $version = null ): bool {
	unset( $version );

	return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $uuid );
}

function strip_shortcodes( string $value ): string {
	return (string) preg_replace( '/\[[^\]]+\]/', '', $value );
}

function wp_strip_all_tags( string $value ): string {
	return strip_tags( $value );
}

function get_bloginfo( string $key ): string {
	unset( $key );

	return 'UTF-8';
}

function plugin_basename( string $file ): string {
	return 'wp-ds-aichatbot/' . basename( $file );
}

function sanitize_hex_color( $value ) {
	return is_string( $value ) && 1 === preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? strtolower( $value ) : null;
}

function absint( $value ): int {
	return abs( (int) $value );
}

function get_option( string $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['wpdsac_test_options'] )
		? $GLOBALS['wpdsac_test_options'][ $name ]
		: $default;
}

function wp_parse_args( $args, $defaults = array() ): array {
	$args     = is_array( $args ) ? $args : array();
	$defaults = is_array( $defaults ) ? $defaults : array();

	return array_merge( $defaults, $args );
}

function add_settings_error( string $setting, string $code, string $message ): void {
	$GLOBALS['wpdsac_test_settings_errors'][] = compact( 'setting', 'code', 'message' );
}

function sanitize_key( $value ): string {
	return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
}

function sanitize_text_field( $value ): string {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_textarea_field( $value ): string {
	return trim( strip_tags( (string) $value ) );
}

function esc_url_raw( $value, $protocols = null ): string {
	$url       = filter_var( (string) $value, FILTER_SANITIZE_URL );
	$scheme    = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
	$protocols = is_array( $protocols ) ? $protocols : array( 'http', 'https' );

	return in_array( $scheme, $protocols, true ) ? $url : '';
}

function home_url( string $path = '' ): string {
	return 'https://example.test' . $path;
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function do_action( string $hook, ...$args ): void {
	unset( $hook, $args );
}

require_once WPDSAC_PATH . 'src/Support/Autoloader.php';

\DiasMazhenov\WPDsAiChatbot\Support\Autoloader::register();
