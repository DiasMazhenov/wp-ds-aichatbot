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

	public const DB_VERSION = '2';

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

		$rate_limit_table   = self::rate_limit_table();
		$request_lock_table = self::request_lock_table();
		$charset_collate    = $wpdb->get_charset_collate();
		$rate_limit_sql     = "CREATE TABLE {$rate_limit_table} (
			bucket_hash char(64) NOT NULL,
			request_count int(10) unsigned NOT NULL DEFAULT 0,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (bucket_hash),
			KEY expires_at (expires_at)
		) {$charset_collate};";
		$request_lock_sql   = "CREATE TABLE {$request_lock_table} (
			lock_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			lock_token char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (lock_hash),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $rate_limit_sql );
		dbDelta( $request_lock_sql );

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
}
