<?php
/**
 * WordPress post, page, and FAQ knowledge source.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Synchronize published WordPress content into knowledge chunks.
 */
final class PostIndexer {

	public const INDEX_VERSION_OPTION = 'wpdsac_knowledge_index_version';

	public const INDEX_VERSION = '2';

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
		add_action( 'elementor/editor/after_save', array( $this, 'sync_elementor_post' ), 20, 1 );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_index' ), 30 );
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

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->post_types(), true ) || 'publish' !== $post->post_status ) {
			$this->delete_post( $post_id );
			return;
		}

		$this->index_post( $post );
	}

	/**
	 * Reindex after Elementor has persisted its JSON document meta.
	 *
	 * @param int $post_id Elementor document post ID.
	 * @return void
	 */
	public function sync_elementor_post( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$this->sync_post( $post_id, $post, true );
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
		$this->repository->delete_source( 'product', $post_id );
	}

	/**
	 * Rebuild every published public content source in bounded query batches.
	 *
	 * @param int $limit Optional maximum source count. Zero indexes all sources.
	 * @return int Number of indexed sources.
	 */
	public function reindex_all( int $limit = 0 ): int {
		$indexed = 0;
		$offset  = 0;
		$batch   = 100;

		do {
			$remaining = $limit > 0 ? $limit - $offset : $batch;
			$per_page  = $limit > 0 ? min( $batch, max( 0, $remaining ) ) : $batch;

			if ( 0 === $per_page ) {
				break;
			}

			$post_ids = get_posts(
				array(
					'post_type'              => $this->post_types(),
					'post_status'            => 'publish',
					'posts_per_page'         => $per_page,
					'offset'                 => $offset,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $post_ids as $post_id ) {
				$post = get_post( $post_id );

				if ( $post instanceof \WP_Post && $this->index_post( $post ) > 0 ) {
					++$indexed;
				}
			}

			$count   = count( $post_ids );
			$offset += $count;
		} while ( $count === $per_page );

		return $indexed;
	}

	/**
	 * Rebuild existing content once after the index extraction rules change.
	 *
	 * @return void
	 */
	public function maybe_upgrade_index(): void {
		if ( ! current_user_can( 'manage_options' ) || self::INDEX_VERSION === get_option( self::INDEX_VERSION_OPTION ) ) {
			return;
		}

		$this->reindex_all();

		if ( false === get_option( self::INDEX_VERSION_OPTION, false ) ) {
			add_option( self::INDEX_VERSION_OPTION, '', '', false );
		}

		update_option( self::INDEX_VERSION_OPTION, self::INDEX_VERSION, false );
	}

	/**
	 * Index one published source.
	 *
	 * @param \WP_Post $post Post object.
	 * @return int Number of stored chunks.
	 */
	public function index_post( \WP_Post $post ): int {
		$post_content = function_exists( 'do_blocks' ) ? do_blocks( $post->post_content ) : $post->post_content;
		$content      = $post->post_title . "\n\n" . $post->post_excerpt . "\n\n" . $post_content;
		$content      = (string) apply_filters( 'wpdsac_knowledge_post_content', $content, $post );
		$source_type  = FaqPostType::POST_TYPE === $post->post_type ? 'faq' : 'post';
		$source_type  = sanitize_key( (string) apply_filters( 'wpdsac_knowledge_post_source_type', $source_type, $post ) );
		$source_url   = 'faq' === $source_type ? '' : (string) get_permalink( $post );

		return $this->repository->replace_source(
			$source_type,
			(int) $post->ID,
			$post->post_title,
			$source_url,
			$this->chunker->split( $content )
		);
	}

	/**
	 * Public post types eligible as knowledge sources, including custom types.
	 *
	 * @return array<int, string>
	 */
	private function post_types(): array {
		$defaults   = array_values( get_post_types( array( 'public' => true ), 'names' ) );
		$defaults   = array_diff( $defaults, array( 'attachment' ) );
		$defaults[] = FaqPostType::POST_TYPE;
		$types      = apply_filters( 'wpdsac_knowledge_post_types', $defaults );
		$types      = is_array( $types ) ? array_map( 'sanitize_key', $types ) : $defaults;

		return array_values( array_filter( array_unique( $types ) ) );
	}
}
