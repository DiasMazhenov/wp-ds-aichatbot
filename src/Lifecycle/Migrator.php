<?php
/**
 * Versioned database migrations.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Lifecycle;

defined( 'ABSPATH' ) || exit;

/**
 * Maintain versioned plugin database tables.
 */
final class Migrator {

	public const DB_VERSION = '6';

	private const VERSION_OPTION = 'wpdsac_db_version';

	/**
	 * Run migrations when the stored schema version is outdated.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( self::DB_VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		self::migrate();
	}

	/**
	 * Create or update plugin tables.
	 *
	 * @return void
	 */
	public static function migrate(): void {
		global $wpdb;

		$rate_limit_table    = self::rate_limit_table();
		$request_lock_table  = self::request_lock_table();
		$knowledge_table     = self::knowledge_table();
		$conversations_table = self::conversations_table();
		$messages_table      = self::messages_table();
		$leads_table         = self::leads_table();
		$charset_collate     = $wpdb->get_charset_collate();
		$rate_limit_sql      = "CREATE TABLE {$rate_limit_table} (
			bucket_hash char(64) NOT NULL,
			request_count int(10) unsigned NOT NULL DEFAULT 0,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (bucket_hash),
			KEY expires_at (expires_at)
		) {$charset_collate};";
		$request_lock_sql    = "CREATE TABLE {$request_lock_table} (
			lock_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			lock_token char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (lock_hash),
			KEY expires_at (expires_at)
		) {$charset_collate};";
		$knowledge_sql       = "CREATE TABLE {$knowledge_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_type varchar(32) NOT NULL,
			source_id bigint(20) unsigned NOT NULL,
			chunk_index smallint(5) unsigned NOT NULL,
			title text NOT NULL,
			source_url text NOT NULL,
			content longtext NOT NULL,
			content_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_chunk (source_type, source_id, chunk_index),
			KEY source_lookup (source_type, source_id),
			KEY content_hash (content_hash)
		) {$charset_collate};";
		$conversations_sql   = "CREATE TABLE {$conversations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at bigint(20) unsigned NOT NULL,
			updated_at bigint(20) unsigned NOT NULL,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_hash (session_hash),
			KEY user_id (user_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";
		$messages_sql        = "CREATE TABLE {$messages_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(16) NOT NULL,
			content longtext NOT NULL,
			created_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		$leads_sql           = "CREATE TABLE {$leads_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			request_text text NOT NULL,
			consent_text text NOT NULL,
			created_at bigint(20) unsigned NOT NULL,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_hash (session_hash),
			KEY email (email),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $rate_limit_sql );
		dbDelta( $request_lock_sql );
		dbDelta( $knowledge_sql );
		dbDelta( $conversations_sql );
		dbDelta( $messages_sql );
		dbDelta( $leads_sql );

		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Return the prefixed rate-limit table name.
	 *
	 * @return string
	 */
	public static function rate_limit_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpdsac_rate_limits';
	}

	/**
	 * Return the prefixed in-flight request lock table name.
	 *
	 * @return string
	 */
	public static function request_lock_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpdsac_request_locks';
	}

	/**
	 * Return the prefixed knowledge chunks table name.
	 *
	 * @return string
	 */
	public static function knowledge_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpdsac_knowledge_chunks';
	}

	/**
	 * Return the prefixed conversations table name.
	 *
	 * @return string
	 */
	public static function conversations_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpdsac_conversations';
	}

	/**
	 * Return the prefixed conversation messages table name.
	 *
	 * @return string
	 */
	public static function messages_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpdsac_messages';
	}

	/**
	 * Return the prefixed leads table name.
	 *
	 * @return string
	 */
	public static function leads_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpdsac_leads';
	}
}
