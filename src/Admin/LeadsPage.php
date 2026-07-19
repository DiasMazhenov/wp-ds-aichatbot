<?php
/**
 * Protected lead administration page.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;
use DiasMazhenov\WPDsAiChatbot\Support\PluginInfo;

defined( 'ABSPATH' ) || exit;

/**
 * Show recent consented leads to administrators.
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
	 * Render recent leads with escaped values.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rows = $this->repository->latest();
		?>
		<div class="wrap wpdsac-admin-page wpdsac-leads-page">
			<h1><?php echo esc_html( PluginInfo::versioned_label( __( 'DS AI Leads', 'wp-ds-aichatbot' ) ) ); ?></h1>
			<p><?php esc_html_e( 'Recent consented contact requests. Expired rows are removed automatically.', 'wp-ds-aichatbot' ); ?></p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Email', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Request', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Consent', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'wp-ds-aichatbot' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'wp-ds-aichatbot' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( array() === $rows ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No leads have been collected.', 'wp-ds-aichatbot' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row['name'] ); ?></td>
							<td><a href="mailto:<?php echo esc_attr( (string) $row['email'] ); ?>"><?php echo esc_html( (string) $row['email'] ); ?></a></td>
							<td><?php echo esc_html( (string) $row['phone'] ); ?></td>
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
}
