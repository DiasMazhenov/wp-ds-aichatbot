<?php
/**
 * Knowledge indexing administration page.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\Knowledge\PostIndexer;
use DiasMazhenov\WPDsAiChatbot\Knowledge\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Provide a capability- and nonce-protected indexing action.
 */
final class KnowledgePage {

	/**
	 * Post indexer.
	 *
	 * @var PostIndexer
	 */
	private $indexer;

	/**
	 * Knowledge repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Store administration dependencies.
	 *
	 * @param PostIndexer $indexer    WordPress source indexer.
	 * @param Repository  $repository Knowledge repository.
	 */
	public function __construct( PostIndexer $indexer, Repository $repository ) {
		$this->indexer    = $indexer;
		$this->repository = $repository;
	}

	/**
	 * Register admin page and action hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_wpdsac_reindex_knowledge', array( $this, 'handle_reindex' ) );
	}

	/**
	 * Add the knowledge page under Tools.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_management_page(
			esc_html__( 'DS AI Knowledge', 'wp-ds-aichatbot' ),
			esc_html__( 'DS AI Knowledge', 'wp-ds-aichatbot' ),
			'manage_options',
			'wpdsac-knowledge',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render knowledge status and reindex form.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$indexed = isset( $_GET['wpdsac_indexed'] ) ? absint( wp_unslash( $_GET['wpdsac_indexed'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$options = Settings::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DS AI Knowledge', 'wp-ds-aichatbot' ); ?></h1>
			<?php if ( null !== $indexed ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: indexed source count. */
							__( 'Knowledge index updated from %d sources.', 'wp-ds-aichatbot' ),
							$indexed
						)
					);
					?>
				</p></div>
			<?php endif; ?>
			<p>
				<?php esc_html_e( 'Published pages, posts, and AI FAQs are split into bounded fragments and stored in a dedicated non-autoloaded table.', 'wp-ds-aichatbot' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Stored fragments:', 'wp-ds-aichatbot' ); ?></strong>
				<?php echo esc_html( number_format_i18n( $this->repository->count() ) ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Retrieval:', 'wp-ds-aichatbot' ); ?></strong>
				<?php echo ! empty( $options['knowledge_enabled'] ) ? esc_html__( 'Enabled', 'wp-ds-aichatbot' ) : esc_html__( 'Disabled in plugin settings', 'wp-ds-aichatbot' ); ?>
			</p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="wpdsac_reindex_knowledge">
				<?php wp_nonce_field( 'wpdsac_reindex_knowledge' ); ?>
				<?php submit_button( __( 'Reindex pages, posts, and FAQs', 'wp-ds-aichatbot' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Rebuild the bounded source index and redirect back to status.
	 *
	 * @return void
	 */
	public function handle_reindex(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the knowledge index.', 'wp-ds-aichatbot' ) );
		}

		check_admin_referer( 'wpdsac_reindex_knowledge' );

		$indexed = $this->indexer->reindex_all();
		$url     = add_query_arg(
			array(
				'page'           => 'wpdsac-knowledge',
				'wpdsac_indexed' => $indexed,
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
