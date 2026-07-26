<?php
/**
 * Protected lead administration page with CSV export.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;
use DiasMazhenov\WPDsAiChatbot\Support\PluginInfo;

defined( 'ABSPATH' ) || exit;

/**
 * Show recent consented leads to administrators and export them as CSV.
 */
final class LeadsPage {

	/**
	 * Lead repository.
	 *
	 * @var LeadRepository
	 */
	private $repository;

	/**
	 * Store repository dependency.
	 *
	 * @param LeadRepository $repository Lead repository.
	 */
	public function __construct( LeadRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register the Tools page.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	/**
	 * Add the protected page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		$label = PluginInfo::versioned_label( __( 'DS AI Leads', 'wp-ds-aichatbot' ) );

		add_submenu_page(
			Settings::PAGE_SLUG,
			esc_html( $label ),
			esc_html__( 'Leads', 'wp-ds-aichatbot' ),
			'manage_options',
			'wpdsac-leads',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render recent leads or serve CSV export.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->is_csv_export_request() ) {
			$this->handle_csv_export();
			return;
		}

		$rows    = $this->repository->latest();
		$csv_url = wp_nonce_url(
			add_query_arg( 'wpdsac_csv_export', '1', admin_url( 'admin.php?page=wpdsac-leads' ) ),
			'wpdsac_csv_export'
		);
		?>
		<div class="wrap wpdsac-admin-page wpdsac-leads-page">
			<h1><?php echo esc_html( PluginInfo::versioned_label( __( 'DS AI Leads', 'wp-ds-aichatbot' ) ) ); ?></h1>
			<p><?php esc_html_e( 'Recent consented contact requests. Expired rows are removed automatically.', 'wp-ds-aichatbot' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $csv_url ); ?>" class="button button-primary"><?php esc_html_e( 'Export CSV', 'wp-ds-aichatbot' ); ?></a>
			</p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Request', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Consent', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'wp-ds-aichatbot' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( array() === $rows ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No leads have been collected.', 'wp-ds-aichatbot' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row['name'] ); ?></td>
							<td><a href="tel:<?php echo esc_attr( (string) $row['phone'] ); ?>"><?php echo esc_html( (string) $row['phone'] ); ?></a></td>
							<td><?php echo esc_html( (string) $row['request_text'] ); ?></td>
							<td><?php echo esc_html( (string) $row['consent_text'] ); ?></td>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', absint( $row['created_at'] ) ) ); ?></td>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', absint( $row['expires_at'] ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Detect a CSV export request.
	 *
	 * @return bool
	 */
	private function is_csv_export_request(): bool {
		return isset( $_GET['wpdsac_csv_export'] ) && '1' === $_GET['wpdsac_csv_export']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in handle_csv_export().
	}

	/**
	 * Stream a CSV download to the browser.
	 *
	 * @return void
	 */
	private function handle_csv_export(): void {
		if ( ! check_admin_referer( 'wpdsac_csv_export' ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'wp-ds-aichatbot' ), 403 );
		}

		$rows = $this->repository->all();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpdsac-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

		$handle = fopen( 'php://output', 'w' );

		// UTF-8 BOM for Excel compatibility.
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing to php://output stream, not filesystem.

		// CSV header row.
		fputcsv(
			$handle,
			array(
				'ID',
				__( 'Name', 'wp-ds-aichatbot' ),
				__( 'Phone', 'wp-ds-aichatbot' ),
				__( 'Request', 'wp-ds-aichatbot' ),
				__( 'Consent', 'wp-ds-aichatbot' ),
				__( 'Submitted', 'wp-ds-aichatbot' ),
				__( 'Expires', 'wp-ds-aichatbot' ),
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$handle,
				array(
					absint( $row['id'] ),
					$this->sanitize_csv_cell( (string) $row['name'] ),
					$this->sanitize_csv_cell( (string) $row['phone'] ),
					$this->sanitize_csv_cell( (string) $row['request_text'] ),
					$this->sanitize_csv_cell( (string) $row['consent_text'] ),
					wp_date( 'Y-m-d H:i', absint( $row['created_at'] ) ),
					wp_date( 'Y-m-d H:i', absint( $row['expires_at'] ) ),
				)
			);
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream, not filesystem.
		exit;
	}

	/**
	 * Neutralize CSV formula injection characters.
	 *
	 * Cells beginning with =, +, -, @ are prefixed with a single quote
	 * to prevent spreadsheet formula execution.
	 *
	 * @param string $value Raw cell value.
	 * @return string Sanitized value.
	 */
	public static function sanitize_csv_cell( string $value ): string {
		$prefixes = array( '=', '+', '-', '@' );
		$first    = mb_substr( $value, 0, 1 );

		if ( in_array( $first, $prefixes, true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
