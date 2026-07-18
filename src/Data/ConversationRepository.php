<?php
/**
 * Privacy-aware conversation persistence.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Data;

use DiasMazhenov\WPDsAiChatbot\Lifecycle\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Store optional chat logs with hashed sessions and mandatory expiry.
 */
final class ConversationRepository {

	/**
	 * Store one visitor/assistant exchange.
	 *
	 * @param string $session_id    Verified session UUID.
	 * @param int    $user_id       Authenticated WordPress user ID or zero.
	 * @param string $visitor_text  Visitor message.
	 * @param string $assistant_text Assistant reply.
	 * @param int    $retention_days Retention period in days.
	 * @return bool
	 */
	public function log_exchange( string $session_id, int $user_id, string $visitor_text, string $assistant_text, int $retention_days ): bool {
		global $wpdb;

		$now          = time();
		$expires_at   = $now + ( min( 365, max( 1, $retention_days ) ) * DAY_IN_SECONDS );
		$session_hash = hash_hmac( 'sha256', $session_id, wp_salt( 'auth' ) );
		$table        = Migrator::conversations_table();
		$query        = $wpdb->prepare(
			'INSERT INTO %i (session_hash, user_id, created_at, updated_at, expires_at)
			VALUES (%s, %d, %d, %d, %d)
			ON DUPLICATE KEY UPDATE
			user_id = IF(VALUES(user_id) > 0, VALUES(user_id), user_id),
			updated_at = VALUES(updated_at),
			expires_at = VALUES(expires_at)',
			$table,
			$session_hash,
			absint( $user_id ),
			$now,
			$now,
			$expires_at
		);

		if ( false === $wpdb->query( $query ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Atomic conversation upsert.
			return false;
		}

		$conversation_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT id FROM %i WHERE session_hash = %s',
				$table,
				$session_hash
			)
		);
		$conversation_id = absint( $conversation_id );

		if ( 0 === $conversation_id ) {
			return false;
		}

		$visitor_saved   = $this->insert_message( $conversation_id, 'visitor', $visitor_text, $now );
		$assistant_saved = $this->insert_message( $conversation_id, 'assistant', $assistant_text, $now );

		return $visitor_saved && $assistant_saved;
	}

	/**
	 * Delete expired conversations and their messages in bounded batches.
	 *
	 * @param int $limit Maximum conversations per cleanup.
	 * @return int Removed conversation count.
	 */
	public function cleanup_expired( int $limit = 500 ): int {
		global $wpdb;

		$conversation_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT id FROM %i WHERE expires_at <= %d ORDER BY id ASC LIMIT %d',
				Migrator::conversations_table(),
				time(),
				min( 1000, max( 1, $limit ) )
			)
		);

		return $this->delete_conversations( array_map( 'absint', $conversation_ids ) );
	}

	/**
	 * Export messages belonging to an authenticated WordPress user.
	 *
	 * @param int $user_id User ID.
	 * @param int $page    One-based page number.
	 * @return array<int, array<string, mixed>>
	 */
	public function export_for_user( int $user_id, int $page = 1 ): array {
		global $wpdb;

		$per_page = 100;
		$offset   = max( 0, $page - 1 ) * $per_page;
		$sql      = $wpdb->prepare(
			'SELECT m.id, m.conversation_id, m.role, m.content, m.created_at
			FROM %i m INNER JOIN %i c ON c.id = m.conversation_id
			WHERE c.user_id = %d ORDER BY m.id ASC LIMIT %d OFFSET %d',
			Migrator::messages_table(),
			Migrator::conversations_table(),
			absint( $user_id ),
			$per_page,
			$offset
		);
		$rows     = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy export reads authoritative log data.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Erase all conversations linked to one authenticated WordPress user.
	 *
	 * @param int $user_id User ID.
	 * @return int Removed conversation count.
	 */
	public function erase_user( int $user_id ): int {
		global $wpdb;

		$conversation_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT id FROM %i WHERE user_id = %d',
				Migrator::conversations_table(),
				absint( $user_id )
			)
		);

		return $this->delete_conversations( array_map( 'absint', $conversation_ids ) );
	}

	/**
	 * Store one message row.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $role            Message role.
	 * @param string $content         Message text.
	 * @param int    $created_at      Unix timestamp.
	 * @return bool
	 */
	private function insert_message( int $conversation_id, string $role, string $content, int $created_at ): bool {
		global $wpdb;

		$content = sanitize_textarea_field( $content );
		$content = function_exists( 'mb_substr' ) ? mb_substr( $content, 0, 12000 ) : substr( $content, 0, 12000 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Dedicated plugin repository table.
		$inserted = $wpdb->insert(
			Migrator::messages_table(),
			array(
				'conversation_id' => absint( $conversation_id ),
				'role'            => sanitize_key( $role ),
				'content'         => $content,
				'created_at'      => absint( $created_at ),
			),
			array( '%d', '%s', '%s', '%d' )
		);

		return false !== $inserted;
	}

	/**
	 * Delete conversations and child messages.
	 *
	 * @param array<int, int> $conversation_ids Conversation IDs.
	 * @return int Removed conversation count.
	 */
	private function delete_conversations( array $conversation_ids ): int {
		global $wpdb;

		$removed = 0;

		foreach ( array_filter( array_unique( $conversation_ids ) ) as $conversation_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit retention/privacy deletion.
			$wpdb->delete( Migrator::messages_table(), array( 'conversation_id' => $conversation_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit retention/privacy deletion.
			$deleted = $wpdb->delete( Migrator::conversations_table(), array( 'id' => $conversation_id ), array( '%d' ) );

			if ( false !== $deleted && $deleted > 0 ) {
				++$removed;
			}
		}

		return $removed;
	}
}
