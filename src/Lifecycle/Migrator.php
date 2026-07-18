<?php
/**
 * Versioned database migrations.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Lifecycle;

defined( 'ABSPATH' ) || exit;

final class Migrator {

	public const DB_VERSION = '1';

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

		$table_name      = self::rate_limit_table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			bucket_hash char(64) NOT NULL,
			request_count int(10) unsigned NOT NULL DEFAULT 0,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (bucket_hash),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

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
}

