<?php
/**
 * Consent-aware lead persistence.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Data;

use DiasMazhenov\WPDsAiChatbot\Lifecycle\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Store and manage leads outside autoloaded options.
 */
final class LeadRepository {

	/**
	 * Last attempted SQL for diagnostics.
	 *
	 * @var string
	 */
	public static $last_sql = '';

	/**
	 * Store one consented lead per signed chat session.
	 *
	 * @param string $session_id    Verified session UUID.
	 * @param int    $user_id       Authenticated WordPress user ID or zero.
	 * @param string $name          Visitor name.
	 * @param string $email         Validated visitor email.
	 * @param string $phone         Visitor phone number.
	 * @param string $request_text  Visitor request text.
	 * @param string $consent_text  Consent statement shown at submission.
	 * @param int    $retention_days Retention period in days.
	 * @return bool
	 */
	public function save( string $session_id, int $user_id, string $name, string $email, string $phone, string $request_text, string $consent_text, int $retention_days ): bool {
		global $wpdb;

		$table = Migrator::leads_table();

		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence guard.
			Migrator::migrate();
		}

		$now          = time();
		$session_hash = hash_hmac( 'sha256', $session_id, wp_salt( 'auth' ) );
		$expires_at   = $now + ( min( 730, max( 1, $retention_days ) ) * DAY_IN_SECONDS );
		$safe_name    = sanitize_text_field( $name );
		$safe_email   = sanitize_email( $email );
		$safe_phone   = substr( sanitize_text_field( $phone ), 0, 50 );
		$safe_request = $this->bounded_text( $request_text, 4000 );
		$safe_consent = sanitize_textarea_field( $consent_text );

		self::$last_sql = 'INSERT INTO `' . $table . '` (session_hash, user_id, name, email, phone, request_text, consent_text, created_at, expires_at) VALUES ('
			. "'" . $wpdb->_real_escape( $session_hash ) . "', "
			. absint( $user_id ) . ', '
			. "'" . $wpdb->_real_escape( $safe_name ) . "', "
			. "'" . $wpdb->_real_escape( $safe_email ) . "', "
			. "'" . $wpdb->_real_escape( $safe_phone ) . "', "
			. "'" . $wpdb->_real_escape( $safe_request ) . "', "
			. "'" . $wpdb->_real_escape( $safe_consent ) . "', "
			. $now . ', '
			. $expires_at
			. ') ON DUPLICATE KEY UPDATE '
			. 'user_id = VALUES(user_id), name = VALUES(name), email = VALUES(email), '
			. 'phone = VALUES(phone), request_text = VALUES(request_text), consent_text = VALUES(consent_text), '
			. 'created_at = VALUES(created_at), expires_at = VALUES(expires_at)';

		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query -- All values independently sanitized; $wpdb->query() rejects valid SQL on some hosts.
		$result = mysqli_query( $wpdb->dbh, self::$last_sql );

		if ( ! $result ) {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_error -- Human-readable diagnostics, no visitor data exposed.
			$wpdb->last_error = 'MySQL error: ' . mysqli_error( $wpdb->dbh );
			return false;
		}

		return true;
	}

	/**
	 * Sanitize and bound a stored text value.
	 *
	 * @param string $value Raw value.
	 * @param int    $limit Maximum characters.
	 * @return string
	 */
	private function bounded_text( string $value, int $limit ): string {
		$value = sanitize_textarea_field( $value );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	/**
	 * Return recent leads for the protected administration page.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function latest( int $limit = 100 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Protected admin report.
			$wpdb->prepare(
				'SELECT id, name, email, phone, request_text, consent_text, created_at, expires_at FROM %i ORDER BY id DESC LIMIT %d',
				Migrator::leads_table(),
				min( 500, max( 1, $limit ) )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Export leads matching an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  One-based page.
	 * @return array<int, array<string, mixed>>
	 */
	public function export_email( string $email, int $page = 1 ): array {
		global $wpdb;

		$per_page = 100;
		$rows     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy export.
			$wpdb->prepare(
				'SELECT id, name, email, phone, request_text, consent_text, created_at, expires_at FROM %i WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d',
				Migrator::leads_table(),
				sanitize_email( $email ),
				$per_page,
				max( 0, $page - 1 ) * $per_page
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Erase leads matching an email address.
	 *
	 * @param string $email Email address.
	 * @return int Removed rows.
	 */
	public function erase_email( string $email ): int {
		global $wpdb;

		$removed = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure.
			Migrator::leads_table(),
			array( 'email' => sanitize_email( $email ) ),
			array( '%s' )
		);

		return false === $removed ? 0 : absint( $removed );
	}

	/**
	 * Check whether the signed chat session already has a lead.
	 *
	 * @param string $session_id Verified session UUID.
	 * @return bool
	 */
	public function exists_for_session( string $session_id ): bool {
		global $wpdb;

		$session_hash = hash_hmac( 'sha256', $session_id, wp_salt( 'auth' ) );
		$count        = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fast existence lookup.
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE session_hash = %s',
				Migrator::leads_table(),
				$session_hash
			)
		);

		return absint( $count ) > 0;
	}

	/**
	 * Delete expired leads.
	 *
	 * @param int $limit Maximum rows per run.
	 * @return int Removed rows.
	 */
	public function cleanup_expired( int $limit = 500 ): int {
		global $wpdb;

		$ids     = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Retention cleanup.
			$wpdb->prepare(
				'SELECT id FROM %i WHERE expires_at <= %d ORDER BY id ASC LIMIT %d',
				Migrator::leads_table(),
				time(),
				min( 1000, max( 1, $limit ) )
			)
		);
		$removed = 0;

		foreach ( array_map( 'absint', $ids ) as $id ) {
			$deleted  = $wpdb->delete( Migrator::leads_table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Retention cleanup.
			$removed += false !== $deleted ? absint( $deleted ) : 0;
		}

		return $removed;
	}
}
