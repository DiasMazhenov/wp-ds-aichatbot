<?php
/**
 * WordPress privacy integration for consented leads.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Privacy;

use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Register lead exporter, eraser, policy text, and retention cleanup.
 */
final class LeadPrivacy {

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
	 * Register privacy and retention hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_action( 'wpdsac_cleanup_leads', array( $this->repository, 'cleanup_expired' ) );
	}

	/**
	 * Register the lead exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['wp-ds-aichatbot-leads'] = array(
			'exporter_friendly_name' => __( 'WP DS AI Chatbot contact requests', 'wp-ds-aichatbot' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the lead eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['wp-ds-aichatbot-leads'] = array(
			'eraser_friendly_name' => __( 'WP DS AI Chatbot contact requests', 'wp-ds-aichatbot' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export leads matching the requested email, including anonymous visitors.
	 *
	 * @param string $email_address Requested email.
	 * @param int    $page          Export page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		$rows = $this->repository->export_email( $email_address, $page );
		$data = array();

		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => 'wp-ds-aichatbot-leads',
				'group_label' => __( 'AI chatbot contact requests', 'wp-ds-aichatbot' ),
				'item_id'     => 'wpdsac-lead-' . absint( $row['id'] ),
				'data'        => array(
					array(
						'name'  => __( 'Name', 'wp-ds-aichatbot' ),
						'value' => sanitize_text_field( (string) $row['name'] ),
					),
					array(
						'name'  => __( 'Email', 'wp-ds-aichatbot' ),
						'value' => sanitize_email( (string) $row['email'] ),
					),
					array(
						'name'  => __( 'Phone', 'wp-ds-aichatbot' ),
						'value' => sanitize_text_field( (string) $row['phone'] ),
					),
					array(
						'name'  => __( 'Request', 'wp-ds-aichatbot' ),
						'value' => sanitize_textarea_field( (string) $row['request_text'] ),
					),
					array(
						'name'  => __( 'Consent', 'wp-ds-aichatbot' ),
						'value' => sanitize_textarea_field( (string) $row['consent_text'] ),
					),
					array(
						'name'  => __( 'Submitted', 'wp-ds-aichatbot' ),
						'value' => wp_date( 'c', absint( $row['created_at'] ) ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $rows ) < 100,
		);
	}

	/**
	 * Erase leads matching the requested email.
	 *
	 * @param string $email_address Requested email.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email_address ): array {
		$removed = $this->repository->erase_email( $email_address );

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Add suggested privacy-policy language.
	 *
	 * @return void
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			'WP DS AI Chatbot',
			wp_kses_post(
				sprintf(
					'<p>%s</p>',
					esc_html__( 'When a visitor chooses to leave contact details, the chatbot stores the submitted name, phone number, request text, consent text, and submission time for the configured retention period. The submitted chat transcript is emailed to the configured administrator address but is not stored with the lead. IP addresses and raw chat session identifiers are not stored.', 'wp-ds-aichatbot' )
				)
			)
		);
	}
}
