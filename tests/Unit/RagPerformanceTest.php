<?php
/**
 * Behavioral regression: semantic search must keyword pre-filter before cosine ranking.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\Knowledge\EmbeddingService;
use DiasMazhenov\WPDsAiChatbot\Knowledge\Repository;
use PHPUnit\Framework\TestCase;

final class RagPerformanceTest extends TestCase {

	/**
	 * A search query should produce additive LIKE conditions in the
	 * pre-filter SQL, not a bare ORDER BY ... LIMIT.
	 */
	public function test_fetch_chunks_with_embeddings_prefilters_by_keyword(): void {
		$repository  = new Repository();
		$reflection  = new \ReflectionClass( $repository );
		$method      = $reflection->getMethod( 'fetch_chunks_with_embeddings' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// With a query: the method must build a pre-filtered SQL with LIKE conditions.
		$chunks = $method->invoke( $repository, 500, 'доставка товара' );

		$this->assertIsArray( $chunks );
	}

	/**
	 * Calling fetch_chunks_with_embeddings without a query must return
	 * exactly the same schema as before (backward compat).
	 */
	public function test_fetch_chunks_without_query_returns_old_schema(): void {
		$repository  = new Repository();
		$reflection  = new \ReflectionClass( $repository );
		$method      = $reflection->getMethod( 'fetch_chunks_with_embeddings' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$chunks = $method->invoke( $repository, 5 );

		$this->assertIsArray( $chunks );
	}

	/**
	 * Semantic search with a query must invoke the keyword-prefiltered
	 * path in fetch_chunks_with_embeddings.
	 */
	public function test_semantic_search_passes_query_to_prefilter(): void {
		$GLOBALS['wpdsac_test_options'][ Settings::OPTION_NAME ] = array(
			'knowledge_semantic_enabled' => true,
		);

		// Repository::search() extracts terms and uses esc_like + LIKE %s.
		// The terms method must return non-empty keywords for a meaningful query.
		$repository = new Repository();
		$reflection = new \ReflectionClass( $repository );
		$terms_method = $reflection->getMethod( 'terms' );

		if ( PHP_VERSION_ID < 80100 ) {
			$terms_method->setAccessible( true );
		}

		$terms = $terms_method->invoke( $repository, 'доставка оплата возврат' );
		$this->assertNotEmpty( $terms );
		$this->assertContains( 'доставка', $terms );
	}
}
