<?php
/**
 * Knowledge indexing administration page.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\Knowledge\PostIndexer;
use DiasMazhenov\WPDsAiChatbot\Knowledge\PdfIndexer;
use DiasMazhenov\WPDsAiChatbot\Knowledge\Repository;
use DiasMazhenov\WPDsAiChatbot\Support\PluginInfo;

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
	 * PDF indexer.
	 *
	 * @var PdfIndexer
	 */
	private $pdf_indexer;

	/**
	 * Store administration dependencies.
	 *
	 * @param PostIndexer $indexer     WordPress source indexer.
	 * @param PdfIndexer  $pdf_indexer Selected PDF source indexer.
	 * @param Repository  $repository  Knowledge repository.
	 */
	public function __construct( PostIndexer $indexer, PdfIndexer $pdf_indexer, Repository $repository ) {
		$this->indexer     = $indexer;
		$this->pdf_indexer = $pdf_indexer;
		$this->repository  = $repository;
	}

	/**
	 * Register admin page and action hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_wpdsac_reindex_knowledge', array( $this, 'handle_reindex' ) );
		add_action( 'admin_post_wpdsac_save_pdf_sources', array( $this, 'handle_pdf_sources' ) );
	}

	/**
	 * Add the knowledge page under Tools.
	 *
	 * @return void
	 */
	public function add_page(): void {
		$label = PluginInfo::versioned_label( __( 'DS AI Knowledge', 'wp-ds-aichatbot' ) );

		add_management_page(
			esc_html( $label ),
			esc_html( $label ),
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

		$indexed         = isset( $_GET['wpdsac_indexed'] ) ? absint( wp_unslash( $_GET['wpdsac_indexed'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$pdf_indexed     = isset( $_GET['wpdsac_pdf_indexed'] ) ? absint( wp_unslash( $_GET['wpdsac_pdf_indexed'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$pdf_failed      = isset( $_GET['wpdsac_pdf_failed'] ) ? absint( wp_unslash( $_GET['wpdsac_pdf_failed'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$options         = Settings::get();
		$pdf_ids         = $this->pdf_indexer->selected_ids();
		$pdf_attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( PluginInfo::versioned_label( __( 'DS AI Knowledge', 'wp-ds-aichatbot' ) ) ); ?></h1>
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
			<?php if ( null !== $pdf_indexed ) : ?>
				<div class="notice <?php echo $pdf_failed > 0 ? 'notice-warning' : 'notice-success'; ?> is-dismissible"><p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: indexed PDF count, 2: failed PDF count. */
							__( 'PDF sources updated: %1$d indexed, %2$d skipped.', 'wp-ds-aichatbot' ),
							$pdf_indexed,
							$pdf_failed
						)
					);
					?>
				</p></div>
			<?php endif; ?>
			<p>
				<?php esc_html_e( 'Published pages, posts, AI FAQs, WooCommerce products, and selected PDFs are split into bounded fragments and stored in a dedicated non-autoloaded table.', 'wp-ds-aichatbot' ); ?>
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
				<?php submit_button( __( 'Reindex website knowledge', 'wp-ds-aichatbot' ), 'primary', 'submit', false ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'PDF knowledge sources', 'wp-ds-aichatbot' ); ?></h2>
			<p><?php esc_html_e( 'Select text-based PDFs from the Media Library. Scanned images require OCR before upload. Maximum 50 files and 10 MB per file.', 'wp-ds-aichatbot' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="wpdsac_save_pdf_sources">
				<?php wp_nonce_field( 'wpdsac_save_pdf_sources' ); ?>
				<?php if ( array() === $pdf_attachments ) : ?>
					<p><?php esc_html_e( 'No PDF attachments were found in the Media Library.', 'wp-ds-aichatbot' ); ?></p>
				<?php else : ?>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Select PDF knowledge sources', 'wp-ds-aichatbot' ); ?></legend>
						<?php foreach ( $pdf_attachments as $attachment ) : ?>
							<p><label>
								<input type="checkbox" name="pdf_attachment_ids[]" value="<?php echo esc_attr( (string) $attachment->ID ); ?>" <?php checked( in_array( (int) $attachment->ID, $pdf_ids, true ) ); ?>>
								<?php echo esc_html( get_the_title( $attachment ) ); ?>
							</label></p>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
				<?php submit_button( __( 'Save and index selected PDFs', 'wp-ds-aichatbot' ), 'secondary', 'submit', false ); ?>
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

		$indexed = $this->indexer->reindex_all() + $this->pdf_indexer->reindex_selected();
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

	/**
	 * Save and rebuild explicitly selected PDF sources.
	 *
	 * @return void
	 */
	public function handle_pdf_sources(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage PDF knowledge sources.', 'wp-ds-aichatbot' ) );
		}

		check_admin_referer( 'wpdsac_save_pdf_sources' );

		$raw_ids = isset( $_POST['pdf_attachment_ids'] )
			? array_map( 'absint', (array) wp_unslash( $_POST['pdf_attachment_ids'] ) )
			: array();
		$result  = $this->pdf_indexer->save_selection( $raw_ids );
		$url     = add_query_arg(
			array(
				'page'               => 'wpdsac-knowledge',
				'wpdsac_pdf_indexed' => $result['indexed'],
				'wpdsac_pdf_failed'  => $result['failed'],
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
