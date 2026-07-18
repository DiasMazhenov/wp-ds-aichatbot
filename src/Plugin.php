<?php
/**
 * Main plugin coordinator.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\Api\ChatController;
use DiasMazhenov\WPDsAiChatbot\Api\RateLimiter;
use DiasMazhenov\WPDsAiChatbot\Api\SessionController;
use DiasMazhenov\WPDsAiChatbot\Api\SessionToken;
use DiasMazhenov\WPDsAiChatbot\Chat\Assets;
use DiasMazhenov\WPDsAiChatbot\Chat\Renderer;
use DiasMazhenov\WPDsAiChatbot\Chat\Shortcode;
use DiasMazhenov\WPDsAiChatbot\Elementor\Integration;
use DiasMazhenov\WPDsAiChatbot\Lifecycle\Migrator;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static $instance;

	/**
	 * Return the single coordinator instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register plugin services and hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		$assets       = new Assets();
		$renderer     = new Renderer( $assets );
		$tokens       = new SessionToken();
		$rate_limiter = new RateLimiter();
		$chat_api     = new ChatController( $tokens, $rate_limiter );
		$session_api  = new SessionController( $tokens );

		Migrator::maybe_migrate();

		( new Settings() )->register_hooks();
		$assets->register_hooks();
		$rate_limiter->register_hooks();
		$chat_api->register_hooks();
		$session_api->register_hooks();
		( new Shortcode( $renderer ) )->register_hooks();
		( new Integration( $renderer ) )->register_hooks();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_footer', array( $renderer, 'render_global' ) );
	}

	/**
	 * Load translations at init.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-ds-aichatbot',
			false,
			dirname( plugin_basename( WPDSAC_FILE ) ) . '/languages'
		);
	}

	private function __construct() {}
}
