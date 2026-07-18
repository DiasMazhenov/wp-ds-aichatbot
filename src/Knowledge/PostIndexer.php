<?php
/**
 * WordPress post, page, and FAQ knowledge source.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Synchronize published WordPress content into knowledge chunks.
 */
final class PostIndexer {

	/**
	 * Knowledge repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Text chunker.
	 *
	 * @var Chunker
	 */
	private $chunker;

	/**
	 * Store indexing dependencies.
	 *
	 * @param Repository $repository Knowledge repository.
	 * @param Chunker    $chunker    Text chunker.
	 */
	public function __construct( Repository $repository, Chunker $chunker ) {
		$this->repository = $repository;
		$this->chunker    = $chunker;
	}

	/**
	 * Register automatic source synchronization.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'save_post', array( $this, 'sync_post' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'delete_post' ) );
	}

	/**
	 * Synchronize a saved public post or page.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 * @return void
	 */
	public function sync_post( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );

		if ( ! $this->enabled() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->post_types(), true ) || 'publish' !== $post->post_status ) {
			$this->delete_post( $post_id );
			return;
		}

		$this->index_post( $post );
	}

	/**
	 * Remove an unpublished or deleted source.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_post( int $post_id ): void {
		$this->repository->delete_source( 'post', $post_id );
		$this->repository->delete_source( 'faq', $post_id );
	}

	/**
	 * Rebuild published pages and posts with a hard per-request bound.
	 *
	 * @param int $limit Maximum source count.
	 * @return int Number of indexed sources.
	 */
	public function reindex_all( int $limit = 200 ): int {
		$post_ids = get_posts(
			array(
				'post_type'              => $this->post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => min( 500, max( 1, $limit ) ),
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$indexed = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post && $this->index_post( $post ) > 0 ) {
				++$indexed;
			}
		}

		return $indexed;
	}

	/**
	 * Index one published source.
	 *
	 * @param \WP_Post $post Post object.
	 * @return int Number of stored chunks.
	 */
	public function index_post( \WP_Post $post ): int {
		$content     = $post->post_title . "\n\n" . $post->post_excerpt . "\n\n" . $post->post_content;
		$source_type = FaqPostType::POST_TYPE === $post->post_type ? 'faq' : 'post';
		$source_url  = 'faq' === $source_type ? '' : (string) get_permalink( $post );

		return $this->repository->replace_source(
			$source_type,
			(int) $post->ID,
			$post->post_title,
			$source_url,
			$this->chunker->split( $content )
		);
	}

	/**
	 * Whether knowledge retrieval is enabled.
	 *
	 * @return bool
	 */
	private function enabled(): bool {
		$options = Settings::get();

		return ! empty( $options['knowledge_enabled'] );
	}

	/**
	 * Public post types eligible as knowledge sources.
	 *
	 * @return array<int, string>
	 */
	private function post_types(): array {
		$defaults = array( 'page', 'post', FaqPostType::POST_TYPE );
		$types    = apply_filters( 'wpdsac_knowledge_post_types', $defaults );
		$types    = is_array( $types ) ? array_map( 'sanitize_key', $types ) : $defaults;

		return array_values( array_filter( array_unique( $types ) ) );
	}
}
