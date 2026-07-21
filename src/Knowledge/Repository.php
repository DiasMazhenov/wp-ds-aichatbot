<?php
/**
 * Knowledge chunk persistence and retrieval.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

use DiasMazhenov\WPDsAiChatbot\Lifecycle\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Store public source fragments outside autoloaded options.
 */
final class Repository {

	/**
	 * Replace all fragments belonging to one source.
	 *
	 * @param string             $source_type Source type.
	 * @param int                $source_id   Source object ID.
	 * @param string             $title       Public source title.
	 * @param string             $source_url  Public source URL.
	 * @param array<int, string> $chunks      Normalized text chunks.
	 * @return int Number of stored chunks.
	 */
	public function replace_source( string $source_type, int $source_id, string $title, string $source_url, array $chunks ): int {
		global $wpdb;

		$source_type = sanitize_key( $source_type );
		$source_id   = absint( $source_id );

		if ( '' === $source_type || 0 === $source_id ) {
			return 0;
		}

		$this->delete_source( $source_type, $source_id );

		$stored = 0;

		foreach ( array_slice( $chunks, 0, 200 ) as $index => $content ) {
			$content = sanitize_textarea_field( $content );

			if ( '' === $content ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Dedicated plugin repository table.
			$inserted = $wpdb->insert(
				Migrator::knowledge_table(),
				array(
					'source_type'  => $source_type,
					'source_id'    => $source_id,
					'chunk_index'  => absint( $index ),
					'title'        => sanitize_text_field( $title ),
					'source_url'   => esc_url_raw( $source_url ),
					'content'      => $content,
					'content_hash' => hash( 'sha256', $content ),
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false !== $inserted ) {
				++$stored;
			}
		}

		return $stored;
	}

	/**
	 * Remove all fragments for one source.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object ID.
	 * @return void
	 */
	public function delete_source( string $source_type, int $source_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit source synchronization.
		$wpdb->delete(
			Migrator::knowledge_table(),
			array(
				'source_type' => sanitize_key( $source_type ),
				'source_id'   => absint( $source_id ),
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Find relevant chunks with a bounded local keyword search.
	 *
	 * @param string $query Visitor question.
	 * @param int    $limit Maximum returned chunks.
	 * @return array<int, array{title: string, source_url: string, content: string}>
	 */
	public function search( string $query, int $limit = 4 ): array {
		global $wpdb;

		$terms = $this->terms( $query );

		if ( array() === $terms ) {
			return array();
		}

		$conditions = array();
		$arguments  = array( Migrator::knowledge_table() );

		foreach ( $terms as $term ) {
			$like         = '%' . $wpdb->esc_like( $term ) . '%';
			$conditions[] = '(content LIKE %s OR title LIKE %s)';
			$arguments[]  = $like;
			$arguments[]  = $like;
		}

		$sql = 'SELECT title, source_url, content FROM %i WHERE '
			. implode( ' OR ', $conditions )
			. ' ORDER BY updated_at DESC LIMIT 80';
		$sql = $wpdb->prepare( $sql, $arguments ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders and clauses are built internally.

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded live knowledge retrieval.

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$haystack      = $this->lower( (string) $row['content'] );
			$title         = $this->lower( (string) $row['title'] );
			$row['_score'] = 0;

			foreach ( $terms as $term ) {
				$row['_score'] += substr_count( $haystack, $term );
				$row['_score'] += 3 * substr_count( $title, $term );
			}
		}
		unset( $row );

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				return (int) $right['_score'] <=> (int) $left['_score'];
			}
		);

		$results = array();

		foreach ( array_slice( $rows, 0, min( 8, max( 1, $limit ) ) ) as $row ) {
			$results[] = array(
				'title'      => sanitize_text_field( (string) $row['title'] ),
				'source_url' => esc_url_raw( (string) $row['source_url'] ),
				'content'    => sanitize_textarea_field( (string) $row['content'] ),
			);
		}

		return $results;
	}

	/**
	 * Persist a JSON-encoded embedding vector for a chunk.
	 *
	 * @param int               $chunk_id Chunk row ID.
	 * @param array<int, float> $vector   Normalized float vector.
	 * @return bool
	 */
	public function store_embedding( int $chunk_id, array $vector ): bool {
		global $wpdb;

		$json = wp_json_encode( $vector );

		if ( false === $json ) {
			return false;
		}

		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Embedding persistence.
			Migrator::knowledge_table(),
			array( 'embedding' => $json ),
			array( 'id' => absint( $chunk_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Fetch chunks that have stored embeddings, bounded.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array{id: int, title: string, source_url: string, content: string, embedding: string}>
	 */
	public function fetch_chunks_with_embeddings( int $limit = 20 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Semantic retrieval.
			$wpdb->prepare(
				'SELECT id, title, source_url, content, embedding FROM %i WHERE embedding IS NOT NULL ORDER BY updated_at DESC LIMIT %d',
				Migrator::knowledge_table(),
				min( 100, max( 1, $limit ) )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count stored fragments.
	 *
	 * @return int
	 */
	public function count(): int {
		global $wpdb;

		$table = Migrator::knowledge_table();
		$sql   = $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table );

		return absint( $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin status count.
	}

	/**
	 * Extract a bounded set of searchable words.
	 *
	 * @param string $query Search query.
	 * @return array<int, string>
	 */
	private function terms( string $query ): array {
		$parts     = preg_split( '/[^\p{L}\p{N}_-]+/u', $this->lower( $query ) );
		$stopwords = array(
			'about',
			'give',
			'link',
			'page',
			'please',
			'show',
			'site',
			'the',
			'website',
			'где',
			'дай',
			'дайте',
			'как',
			'мне',
			'на',
			'покажи',
			'пожалуйста',
			'сайт',
			'сайта',
			'ссылку',
			'страницу',
		);

		if ( ! is_array( $parts ) ) {
			return array();
		}

		$parts = array_filter(
			array_unique( $parts ),
			static function ( string $term ) use ( $stopwords ): bool {
				$length = function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term );

				return $length >= 3 && ! in_array( $term, $stopwords, true );
			}
		);

		return array_slice( array_values( $parts ), 0, 8 );
	}

	/**
	 * Lowercase text with an mbstring fallback.
	 *
	 * @param string $value Text value.
	 * @return string
	 */
	private function lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}
}
