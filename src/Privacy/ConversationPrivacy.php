<?php
/**
 * WordPress privacy integration for optional conversation logs.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Privacy;

use DiasMazhenov\WPDsAiChatbot\Data\ConversationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Register exporter, eraser, and suggested privacy policy text.
 */
final class ConversationPrivacy {

	/**
	 * Conversation repository.
	 *
	 * @var ConversationRepository
	 */
	private $repository;

	/**
	 * Store repository dependency.
	 *
	 * @param ConversationRepository $repository Conversation repository.
	 */
	public function __construct( ConversationRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register privacy hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
	}

	/**
	 * Add the conversation exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['wp-ds-aichatbot-conversations'] = array(
			'exporter_friendly_name' => __( 'WP DS AI Chatbot conversations', 'wp-ds-aichatbot' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Add the conversation eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['wp-ds-aichatbot-conversations'] = array(
			'eraser_friendly_name' => __( 'WP DS AI Chatbot conversations', 'wp-ds-aichatbot' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export logged messages linked to a registered user's email.
	 *
	 * @param string $email_address Requested email address.
	 * @param int    $page          Export page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user instanceof \WP_User ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$rows = $this->repository->export_for_user( (int) $user->ID, $page );
		$data = array();

		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => 'wp-ds-aichatbot-conversations',
				'group_label' => __( 'AI chatbot conversations', 'wp-ds-aichatbot' ),
				'item_id'     => 'wpdsac-message-' . absint( $row['id'] ),
				'data'        => array(
					array(
						'name'  => __( 'Role', 'wp-ds-aichatbot' ),
						'value' => sanitize_key( (string) $row['role'] ),
					),
					array(
						'name'  => __( 'Message', 'wp-ds-aichatbot' ),
						'value' => sanitize_textarea_field( (string) $row['content'] ),
					),
					array(
						'name'  => __( 'Time', 'wp-ds-aichatbot' ),
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
	 * Erase logged conversations linked to a registered user's email.
	 *
	 * @param string $email_address Requested email address.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email_address ): array {
		$user    = get_user_by( 'email', $email_address );
		$removed = $user instanceof \WP_User ? $this->repository->erase_user( (int) $user->ID ) : 0;

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
					esc_html__( 'When conversation logging is enabled, chatbot messages are stored for the configured retention period. Session identifiers are stored only as one-way hashes. Logs associated with authenticated WordPress users can be exported or erased using the WordPress privacy tools. Anonymous logs cannot be linked back to an email address.', 'wp-ds-aichatbot' )
				)
			)
		);
	}
}
