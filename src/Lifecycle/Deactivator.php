<?php
/**
 * Plugin deactivation routine.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Lifecycle;

defined( 'ABSPATH' ) || exit;

/**
 * Remove temporary scheduled state during deactivation.
 */
final class Deactivator {

	/**
	 * Clear temporary plugin state while preserving user data.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'wpdsac_cleanup_logs' );
		wp_clear_scheduled_hook( 'wpdsac_cleanup_rate_limits' );
		wp_clear_scheduled_hook( 'wpdsac_cleanup_conversations' );
	}
}
