<?php
/**
 * Plugin activation routine.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Lifecycle;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\AI\CredentialResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Initialize persistent plugin state during activation.
 */
final class Activator {

	/**
	 * Add safe defaults without overwriting existing settings.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( WPDSAC_FILE ) );

			wp_die(
				esc_html__( 'WP DS AI Chatbot requires PHP 7.4 or newer.', 'wp-ds-aichatbot' ),
				esc_html__( 'Plugin activation failed', 'wp-ds-aichatbot' ),
				array( 'back_link' => true )
			);
		}

		add_option( Settings::OPTION_NAME, Settings::defaults(), '', false );

		$credentials = new CredentialResolver();

		foreach ( CredentialResolver::provider_ids() as $provider_id ) {
			add_option( $credentials->option_name( $provider_id ), '', '', false );
		}

		update_option( 'wpdsac_version', WPDSAC_VERSION, false );
		Migrator::migrate();

		if ( ! wp_next_scheduled( 'wpdsac_cleanup_rate_limits' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'wpdsac_cleanup_rate_limits' );
		}

		if ( ! wp_next_scheduled( 'wpdsac_cleanup_conversations' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'wpdsac_cleanup_conversations' );
		}

		if ( ! wp_next_scheduled( 'wpdsac_cleanup_leads' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'wpdsac_cleanup_leads' );
		}
	}
}
