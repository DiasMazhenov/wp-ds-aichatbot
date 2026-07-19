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
delete_option( 'wpdsac_deepseek_api_key' );
delete_option( 'wpdsac_provider_credentials' );
delete_option( 'wpdsac_version' );
delete_option( 'wpdsac_db_version' );
delete_option( 'wpdsac_pdf_attachment_ids' );
wp_clear_scheduled_hook( 'wpdsac_cleanup_logs' );
wp_clear_scheduled_hook( 'wpdsac_cleanup_rate_limits' );
wp_clear_scheduled_hook( 'wpdsac_cleanup_conversations' );
wp_clear_scheduled_hook( 'wpdsac_cleanup_leads' );

$rate_limit_table    = $wpdb->prefix . 'wpdsac_rate_limits';
$request_lock_table  = $wpdb->prefix . 'wpdsac_request_locks';
$knowledge_table     = $wpdb->prefix . 'wpdsac_knowledge_chunks';
$conversations_table = $wpdb->prefix . 'wpdsac_conversations';
$messages_table      = $wpdb->prefix . 'wpdsac_messages';
$leads_table         = $wpdb->prefix . 'wpdsac_leads';
$wpdb->query( "DROP TABLE IF EXISTS {$rate_limit_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$request_lock_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$knowledge_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$messages_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$conversations_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$leads_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
