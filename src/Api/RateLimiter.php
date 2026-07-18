<?php
/**
 * Atomic database-backed public request limiter.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

use DiasMazhenov\WPDsAiChatbot\Lifecycle\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Enforce atomic session, IP and site-wide request budgets.
 */
final class RateLimiter {

	/**
	 * Register scheduled cleanup.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wpdsac_cleanup_rate_limits', array( $this, 'cleanup' ) );
	}

	/**
	 * Consume session and IP buckets.
	 *
	 * @param string $session_id Server-issued session UUID.
	 * @param int    $limit      Per-session request limit.
	 * @param int    $window     Window length in seconds.
	 * @return array<string, bool|int>
	 */
	public function consume_request( string $session_id, int $limit, int $window ): array {
		$session = $this->consume( 'session:' . $session_id, $limit, $window );
		$ip      = $this->consume( 'ip:' . $this->client_ip(), $limit * 3, $window );

		if ( ! $session['allowed'] || ! $ip['allowed'] ) {
			return array(
				'allowed'     => false,
				'remaining'   => 0,
				'retry_after' => max( $session['retry_after'], $ip['retry_after'] ),
			);
		}

		return array(
			'allowed'     => true,
			'remaining'   => min( $session['remaining'], $ip['remaining'] ),
			'retry_after' => 0,
		);
	}

	/**
	 * Consume the site-wide rolling 24-hour request budget.
	 *
	 * @param int $limit Maximum provider calls, or zero to disable the budget.
	 * @return array<string, bool|int>
	 */
	public function consume_daily_budget( int $limit ): array {
		if ( $limit <= 0 ) {
			return array(
				'allowed'     => true,
				'remaining'   => -1,
				'retry_after' => 0,
			);
		}

		return $this->consume( 'budget:site', $limit, DAY_IN_SECONDS );
	}

	/**
	 * Consume conservative per-session and peer lead-submission buckets.
	 *
	 * @param string $session_id Server-issued session UUID.
	 * @return array<string, bool|int>
	 */
	public function consume_lead( string $session_id ): array {
		$session = $this->consume( 'lead-session:' . $session_id, 3, HOUR_IN_SECONDS );
		$ip      = $this->consume( 'lead-ip:' . $this->client_ip(), 10, HOUR_IN_SECONDS );

		return array(
			'allowed'     => $session['allowed'] && $ip['allowed'],
			'remaining'   => min( $session['remaining'], $ip['remaining'] ),
			'retry_after' => max( $session['retry_after'], $ip['retry_after'] ),
		);
	}

	/**
	 * Delete expired buckets.
	 *
	 * @return void
	 */
	public function cleanup(): void {
		global $wpdb;

		$table = Migrator::rate_limit_table();
		// Direct writes are required for atomic counters and cannot use object caching.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'DELETE FROM %i WHERE expires_at <= %d',
				$table,
				time()
			)
		);
	}

	/**
	 * Atomically increment a single bucket.
	 *
	 * @param string $bucket Raw internal bucket key.
	 * @param int    $limit  Maximum requests.
	 * @param int    $window Window length in seconds.
	 * @return array<string, bool|int>
	 */
	private function consume( string $bucket, int $limit, int $window ): array {
		global $wpdb;

		$now        = time();
		$expires_at = $now + $window;
		$table      = Migrator::rate_limit_table();
		$hash       = hash_hmac( 'sha256', $bucket, wp_salt( 'nonce' ) );
		$query      = $wpdb->prepare(
			'INSERT INTO %i (bucket_hash, request_count, expires_at)
			VALUES (%s, 1, %d)
			ON DUPLICATE KEY UPDATE
			request_count = IF(expires_at <= %d, 1, request_count + 1),
			expires_at = IF(expires_at <= %d, %d, expires_at)',
			$table,
			$hash,
			$expires_at,
			$now,
			$now,
			$expires_at
		);

		// The statement is prepared immediately above; the write must remain atomic.
		if ( false === $wpdb->query( $query ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return array(
				'allowed'     => false,
				'remaining'   => 0,
				'retry_after' => $window,
			);
		}

		// Read the authoritative counter written by the atomic upsert above.
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT request_count, expires_at FROM %i WHERE bucket_hash = %s',
				$table,
				$hash
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return array(
				'allowed'     => false,
				'remaining'   => 0,
				'retry_after' => $window,
			);
		}

		$count = (int) $row['request_count'];

		return array(
			'allowed'     => $count <= $limit,
			'remaining'   => max( 0, $limit - $count ),
			'retry_after' => max( 1, (int) $row['expires_at'] - $now ),
		);
	}

	/**
	 * Read the direct peer address without trusting spoofable forwarding headers.
	 *
	 * @return string
	 */
	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = apply_filters( 'wpdsac_client_ip', $ip );

		return is_string( $ip ) && filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
	}
}
