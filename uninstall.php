<?php
/**
 * Plugin uninstall routine.
 *
 * @package WPDsAiChatbot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'wpdsac_settings' );
delete_option( 'wpdsac_openai_api_key' );
delete_option( 'wpdsac_anthropic_api_key' );
delete_option( 'wpdsac_gemini_api_key' );
delete_option( 'wpdsac_openrouter_api_key' );
delete_option( 'wpdsac_version' );
delete_option( 'wpdsac_db_version' );
wp_clear_scheduled_hook( 'wpdsac_cleanup_logs' );
wp_clear_scheduled_hook( 'wpdsac_cleanup_rate_limits' );

$rate_limit_table = $wpdb->prefix . 'wpdsac_rate_limits';
$wpdb->query( "DROP TABLE IF EXISTS {$rate_limit_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
