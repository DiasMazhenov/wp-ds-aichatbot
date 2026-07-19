<?php
/**
 * Knowledge indexing administration page.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\Knowledge\PostIndexer;
use DiasMazhenov\WPDsAiChatbot\Knowledge\PdfIndexer;
use DiasMazhenov\WPDsAiChatbot\Knowledge\FaqPostType;
use DiasMazhenov\WPDsAiChatbot\Knowledge\ContactSource;
use DiasMazhenov\WPDsAiChatbot\Knowledge\ManualSource;
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
	 * Administrator-authored knowledge source.
	 *
	 * @var ManualSource
	 */
	private $manual_source;

	/**
	 * Administrator contact source.
	 *
	 * @var ContactSource
	 */
	private $contact_source;

	/**
	 * Store administration dependencies.
	 *
	 * @param PostIndexer   $indexer       WordPress source indexer.
	 * @param PdfIndexer    $pdf_indexer   Selected PDF source indexer.
	 * @param ManualSource  $manual_source Administrator-authored source.
	 * @param ContactSource $contact_source Administrator contact source.
	 * @param Repository    $repository     Knowledge repository.
	 */
	public function __construct( PostIndexer $indexer, PdfIndexer $pdf_indexer, ManualSource $manual_source, ContactSource $contact_source, Repository $repository ) {
		$this->indexer        = $indexer;
		$this->pdf_indexer    = $pdf_indexer;
		$this->manual_source  = $manual_source;
		$this->contact_source = $contact_source;
		$this->repository     = $repository;
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
		add_action( 'admin_post_wpdsac_save_manual_knowledge', array( $this, 'handle_manual_knowledge' ) );
		add_action( 'admin_post_wpdsac_save_contact_information', array( $this, 'handle_contact_information' ) );
	}

	/**
	 * Add the unified knowledge page under the plugin menu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		$label = PluginInfo::versioned_label( __( 'DS AI Knowledge', 'wp-ds-aichatbot' ) );

		add_submenu_page(
			Settings::PAGE_SLUG,
			esc_html( $label ),
			esc_html__( 'Knowledge base', 'wp-ds-aichatbot' ),
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

		$indexed           = isset( $_GET['wpdsac_indexed'] ) ? absint( wp_unslash( $_GET['wpdsac_indexed'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$pdf_indexed       = isset( $_GET['wpdsac_pdf_indexed'] ) ? absint( wp_unslash( $_GET['wpdsac_pdf_indexed'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$pdf_failed        = isset( $_GET['wpdsac_pdf_failed'] ) ? absint( wp_unslash( $_GET['wpdsac_pdf_failed'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$manual_saved      = isset( $_GET['wpdsac_manual_saved'] ) ? absint( wp_unslash( $_GET['wpdsac_manual_saved'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$contacts_saved    = isset( $_GET['wpdsac_contacts_saved'] ) ? absint( wp_unslash( $_GET['wpdsac_contacts_saved'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice.
		$options           = Settings::get();
		$pdf_ids           = $this->pdf_indexer->selected_ids();
		$contact_fields    = $this->contact_source->fields();
		$knowledge_entries = get_posts(
			array(
				'post_type'      => FaqPostType::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 20,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$pdf_attachments   = get_posts(
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
		<div class="wrap wpdsac-admin-page wpdsac-knowledge-page">
			<h1><?php echo esc_html( PluginInfo::versioned_label( __( 'Knowledge base', 'wp-ds-aichatbot' ) ) ); ?></h1>
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
			<?php if ( null !== $manual_saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php esc_html_e( 'Additional knowledge saved and indexed.', 'wp-ds-aichatbot' ); ?>
				</p></div>
			<?php endif; ?>
			<?php if ( null !== $contacts_saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php esc_html_e( 'Contact information saved and indexed.', 'wp-ds-aichatbot' ); ?>
				</p></div>
			<?php endif; ?>
			<p>
				<?php esc_html_e( 'All published public content types, rendered Gutenberg blocks, Elementor widgets, knowledge entries, administrator text, WooCommerce products, and selected PDFs are split into bounded fragments and stored in a dedicated table.', 'wp-ds-aichatbot' ); ?>
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

			<div class="wpdsac-knowledge-grid">
				<section class="wpdsac-admin-card">
					<h2><?php esc_html_e( 'Instructions and additional knowledge', 'wp-ds-aichatbot' ); ?></h2>
					<p><?php esc_html_e( 'Add facts, policies, instructions, contacts, or other information that may not exist on public pages. The text is stored outside autoloaded options and added to the searchable knowledge index.', 'wp-ds-aichatbot' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="wpdsac_save_manual_knowledge">
						<?php wp_nonce_field( 'wpdsac_save_manual_knowledge' ); ?>
						<label class="screen-reader-text" for="wpdsac-manual-knowledge"><?php esc_html_e( 'Instructions and additional knowledge', 'wp-ds-aichatbot' ); ?></label>
						<textarea id="wpdsac-manual-knowledge" name="manual_knowledge" rows="10" class="large-text" maxlength="50000"><?php echo esc_textarea( $this->manual_source->content() ); ?></textarea>
						<?php submit_button( __( 'Save additional knowledge', 'wp-ds-aichatbot' ), 'primary', 'submit', false ); ?>
					</form>
				</section>

				<section class="wpdsac-admin-card">
					<h2><?php esc_html_e( 'Knowledge entries', 'wp-ds-aichatbot' ); ?></h2>
					<p><?php esc_html_e( 'Create structured question-and-answer entries using the WordPress editor. They are part of this knowledge base and no longer appear as a separate admin menu item.', 'wp-ds-aichatbot' ); ?></p>
					<p class="wpdsac-card-actions">
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . FaqPostType::POST_TYPE ) ); ?>"><?php esc_html_e( 'Add knowledge entry', 'wp-ds-aichatbot' ); ?></a>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . FaqPostType::POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage all entries', 'wp-ds-aichatbot' ); ?></a>
					</p>
					<?php if ( array() === $knowledge_entries ) : ?>
						<p class="description"><?php esc_html_e( 'No knowledge entries found.', 'wp-ds-aichatbot' ); ?></p>
					<?php else : ?>
						<ul class="wpdsac-knowledge-list">
							<?php foreach ( $knowledge_entries as $entry ) : ?>
								<?php
								$status       = get_post_status_object( $entry->post_status );
								$status_label = $status instanceof \WP_Post_Status ? $status->label : $entry->post_status;
								?>
								<li>
									<a href="<?php echo esc_url( get_edit_post_link( $entry->ID ) ); ?>"><?php echo esc_html( get_the_title( $entry ) ); ?></a>
									<span><?php echo esc_html( $status_label ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</div>

			<section class="wpdsac-admin-card">
				<h2><?php esc_html_e( 'Contact information', 'wp-ds-aichatbot' ); ?></h2>
				<p><?php esc_html_e( 'The chatbot may provide these contacts when a visitor asks how to call or message you. WhatsApp accepts a number or wa.me URL; Telegram accepts a username or t.me URL.', 'wp-ds-aichatbot' ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="wpdsac_save_contact_information">
					<?php wp_nonce_field( 'wpdsac_save_contact_information' ); ?>
					<div class="wpdsac-contact-fields">
						<label>
							<span><?php esc_html_e( 'Phone number', 'wp-ds-aichatbot' ); ?></span>
							<input type="text" class="regular-text" name="contact_information[phone]" value="<?php echo esc_attr( $contact_fields['phone'] ); ?>" autocomplete="tel" placeholder="+7 700 000 00 00">
						</label>
						<label>
							<span><?php esc_html_e( 'WhatsApp', 'wp-ds-aichatbot' ); ?></span>
							<input type="text" class="regular-text" name="contact_information[whatsapp]" value="<?php echo esc_attr( $contact_fields['whatsapp'] ); ?>" placeholder="77000000000">
						</label>
						<label>
							<span><?php esc_html_e( 'Telegram', 'wp-ds-aichatbot' ); ?></span>
							<input type="text" class="regular-text" name="contact_information[telegram]" value="<?php echo esc_attr( $contact_fields['telegram'] ); ?>" placeholder="@username">
						</label>
					</div>
					<?php submit_button( __( 'Save contact information', 'wp-ds-aichatbot' ), 'secondary', 'submit', false ); ?>
				</form>
			</section>

			<section class="wpdsac-admin-card">
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
			</section>
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

		$indexed = $this->indexer->reindex_all()
			+ $this->pdf_indexer->reindex_selected()
			+ $this->manual_source->reindex()
			+ $this->contact_source->reindex();
		$url     = add_query_arg(
			array(
				'page'           => 'wpdsac-knowledge',
				'wpdsac_indexed' => $indexed,
			),
			admin_url( 'admin.php' )
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
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Save and index administrator-authored knowledge.
	 *
	 * @return void
	 */
	public function handle_manual_knowledge(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the knowledge index.', 'wp-ds-aichatbot' ) );
		}

		check_admin_referer( 'wpdsac_save_manual_knowledge' );

		$content = isset( $_POST['manual_knowledge'] ) ? wp_unslash( $_POST['manual_knowledge'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ManualSource applies bounded textarea sanitization.
		$indexed = $this->manual_source->save( $content );
		$url     = add_query_arg(
			array(
				'page'                => 'wpdsac-knowledge',
				'wpdsac_manual_saved' => $indexed,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Save and index public contact information.
	 *
	 * @return void
	 */
	public function handle_contact_information(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the knowledge index.', 'wp-ds-aichatbot' ) );
		}

		check_admin_referer( 'wpdsac_save_contact_information' );

		$fields  = isset( $_POST['contact_information'] ) ? wp_unslash( $_POST['contact_information'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ContactSource validates every supported field.
		$indexed = $this->contact_source->save( $fields );
		$url     = add_query_arg(
			array(
				'page'                  => 'wpdsac-knowledge',
				'wpdsac_contacts_saved' => $indexed,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
