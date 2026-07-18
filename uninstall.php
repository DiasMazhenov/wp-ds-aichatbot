<?php
/**
 * Plugin uninstall routine.
 *
 * @package WPDsAiChatbot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wpdsac_settings' );
delete_option( 'wpdsac_version' );
wp_clear_scheduled_hook( 'wpdsac_cleanup_logs' );

