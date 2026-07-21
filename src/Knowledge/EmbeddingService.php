<?php
/**
 * Embeddings orchestration: generate vectors and store them alongside chunks.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

use DiasMazhenov\WPDsAiChatbot\AI\EmbeddingsProviderInterface;
use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Generate and persist vector embeddings for knowledge chunks.
 */
final class EmbeddingService {

	private const QUEUE_HOOK = 'wpdsac_process_embedding_queue';

	/**
	 * The active embedding provider.
	 *
	 * @var EmbeddingsProviderInterface|null
	 */
	private $provider;

	/**
	 * Chunk persistence.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Store dependencies.
	 *
	 * @param EmbeddingsProviderInterface|null $provider   Embedding provider, or null when disabled.
	 * @param Repository                       $repository Chunk storage.
	 */
	public function __construct( ?EmbeddingsProviderInterface $provider, Repository $repository ) {
		$this->provider   = $provider;
		$this->repository = $repository;
	}

	/**
	 * Register deferred embedding generation hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wpdsac_knowledge_chunk_stored', array( $this, 'schedule_queue' ) );
		add_action( self::QUEUE_HOOK, array( $this, 'process_queue' ) );
		add_action( 'admin_init', array( $this, 'schedule_queue' ), 40 );
	}

	/**
	 * Return whether embeddings are configured and available.
	 *
	 * @return bool
	 */
	public function available(): bool {
		return $this->provider instanceof EmbeddingsProviderInterface && $this->provider->is_configured();
	}

	/**
	 * Schedule one background queue runner when semantic search is enabled.
	 *
	 * @return void
	 */
	public function schedule_queue(): void {
		$options = Settings::get();

		if ( empty( $options['knowledge_semantic_enabled'] ) || ! $this->available() ) {
			return;
		}

		if ( false === wp_next_scheduled( self::QUEUE_HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::QUEUE_HOOK );
		}
	}

	/**
	 * Generate a small embedding batch without delaying post saves or admin requests.
	 *
	 * @return void
	 */
	public function process_queue(): void {
		$options = Settings::get();

		if ( empty( $options['knowledge_semantic_enabled'] ) || ! $this->available() ) {
			return;
		}

		$chunks = $this->repository->fetch_chunks_without_embeddings( 5 );
		$stored = 0;

		foreach ( $chunks as $chunk ) {
			if ( $this->embed_chunk( absint( $chunk['id'] ), (string) $chunk['content'] ) ) {
				++$stored;
			}
		}

		if ( 5 === $stored && false === wp_next_scheduled( self::QUEUE_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::QUEUE_HOOK );
		}
	}

	/**
	 * Compute and store an embedding for a single chunk.
	 *
	 * @param int    $chunk_id Database row ID.
	 * @param string $content  Chunk plain-text content.
	 * @return bool
	 */
	public function embed_chunk( int $chunk_id, string $content ): bool {
		if ( ! $this->available() ) {
			return false;
		}

		$vector = $this->provider->embed( $content );

		if ( ! is_array( $vector ) ) {
			return false;
		}

		return $this->repository->store_embedding( $chunk_id, $vector );
	}

	/**
	 * Cosine similarity between two normalized vectors.
	 *
	 * @param array<int, float> $a Normalized query vector.
	 * @param array<int, float> $b Normalized document vector.
	 * @return float Similarity score in [-1, 1].
	 */
	public static function cosine_similarity( array $a, array $b ): float {
		$dot   = 0.0;
		$count = min( count( $a ), count( $b ) );

		for ( $i = 0; $i < $count; $i++ ) {
			$dot += (float) $a[ $i ] * (float) $b[ $i ];
		}

		return $dot;
	}

	/**
	 * Semantic search: rank chunks by cosine similarity to the query embedding.
	 *
	 * @param string $query Visitor question.
	 * @param int    $limit Maximum results.
	 * @return array<int, array{id: int, content: string, title: string, url: string, score: float}>
	 */
	public function search( string $query, int $limit = 5 ): array {
		if ( ! $this->available() || ! $this->repository->has_embeddings() ) {
			return array();
		}

		$query_vector = $this->provider->embed( $query );

		if ( ! is_array( $query_vector ) ) {
			return array();
		}

		$chunks = $this->repository->fetch_chunks_with_embeddings( 500 );
		$scored = array();

		foreach ( $chunks as $chunk ) {
			$vector = json_decode( $chunk['embedding'] ?? '', true );

			if ( ! is_array( $vector ) ) {
				continue;
			}

			$scored[] = array(
				'id'      => (int) $chunk['id'],
				'content' => (string) $chunk['content'],
				'title'   => (string) $chunk['title'],
				'url'     => (string) ( $chunk['source_url'] ?? '' ),
				'score'   => self::cosine_similarity( $query_vector, $vector ),
			);
		}

		usort(
			$scored,
			function ( array $a, array $b ): int {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $scored, 0, $limit );
	}
}
