<?php
/**
 * Atomic database-backed lock for in-flight chat requests.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

use DiasMazhenov\WPDsAiChatbot\Lifecycle\Migrator;

defined( 'ABSPATH' ) || exit;

final class RequestLock {

	/**
	 * Register scheduled cleanup.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wpdsac_cleanup_rate_limits', array( $this, 'cleanup' ) );
	}

	/**
	 * Acquire a session lock, replacing only an expired lock.
	 *
	 * @param string $session_id Verified session UUID.
	 * @param int    $ttl        Lock lifetime in seconds.
	 * @return string|false Ownership token, or false when already locked.
	 */
	public function acquire( string $session_id, int $ttl ) {
		global $wpdb;

		$now        = time();
		$expires_at = $now + $ttl;
		$table      = Migrator::request_lock_table();
		$lock_hash  = hash_hmac( 'sha256', 'session:' . $session_id, wp_salt( 'nonce' ) );
		$lock_token = wp_generate_password( 64, false, false );
		$query      = $wpdb->prepare(
			"INSERT INTO {$table} (lock_hash, lock_token, expires_at)
			VALUES (%s, %s, %d)
			ON DUPLICATE KEY UPDATE
			lock_token = IF(expires_at <= %d, VALUES(lock_token), lock_token),
			expires_at = IF(expires_at <= %d, VALUES(expires_at), expires_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$lock_hash,
			$lock_token,
			$expires_at,
			$now,
			$now
		);

		if ( false === $wpdb->query( $query ) ) {
			return false;
		}

		$stored_token = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT lock_token FROM {$table} WHERE lock_hash = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lock_hash
			)
		);

		return is_string( $stored_token ) && hash_equals( $lock_token, $stored_token ) ? $lock_token : false;
	}

	/**
	 * Release a lock only when the caller owns its token.
	 *
	 * @param string $session_id Verified session UUID.
	 * @param string $lock_token Ownership token.
	 * @return void
	 */
	public function release( string $session_id, string $lock_token ): void {
		global $wpdb;

		$table     = Migrator::request_lock_table();
		$lock_hash = hash_hmac( 'sha256', 'session:' . $session_id, wp_salt( 'nonce' ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE lock_hash = %s AND lock_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lock_hash,
				$lock_token
			)
		);
	}

	/**
	 * Delete abandoned expired locks.
	 *
	 * @return void
	 */
	public function cleanup(): void {
		global $wpdb;

		$table = Migrator::request_lock_table();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE expires_at <= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				time()
			)
		);
	}
}
