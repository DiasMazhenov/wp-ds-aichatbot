<?php
/**
 * Optional conversation logging coordinator.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Data;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Log successful exchanges only when explicitly enabled.
 */
final class ConversationLogger {

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
	 * Register logging and cleanup hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wpdsac_chat_exchange', array( $this, 'log' ), 10, 4 );
		add_action( 'wpdsac_cleanup_conversations', array( $this, 'cleanup' ) );
	}

	/**
	 * Persist a successful exchange when logging is enabled.
	 *
	 * @param string           $session_id Verified session UUID.
	 * @param string           $message    Visitor message.
	 * @param string           $reply      Assistant reply.
	 * @param \WP_REST_Request $request    REST request.
	 * @return void
	 */
	public function log( string $session_id, string $message, string $reply, \WP_REST_Request $request ): void {
		unset( $request );

		$options = Settings::get();

		if ( empty( $options['logging_enabled'] ) ) {
			return;
		}

		$this->repository->log_exchange(
			$session_id,
			get_current_user_id(),
			$message,
			$reply,
			(int) $options['log_retention_days']
		);
	}

	/**
	 * Remove expired logs.
	 *
	 * @return void
	 */
	public function cleanup(): void {
		$this->repository->cleanup_expired();
	}
}
