<?php
/**
 * Email notifications for conversations without a contact request.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Data;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Debounce chat notifications and send one bounded plain-text transcript.
 */
final class ConversationNotifier {

	private const SEND_HOOK        = 'wpdsac_send_conversation_notification';
	private const TRANSIENT_PREFIX = 'wpdsac_chat_mail_';

	/**
	 * Register exchange, lead and delayed-send hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wpdsac_chat_exchange', array( $this, 'queue' ), 20, 4 );
		add_action( 'wpdsac_lead_created', array( $this, 'cancel_for_lead' ), 10, 2 );
		add_action( self::SEND_HOOK, array( $this, 'send' ) );
	}

	/**
	 * Store the latest bounded transcript and postpone delivery after every exchange.
	 *
	 * @param string           $session_id Verified session UUID.
	 * @param string           $message    Latest visitor message.
	 * @param string           $reply      Latest assistant reply.
	 * @param \WP_REST_Request $request    Chat request with prior history and visitor name.
	 * @return void
	 */
	public function queue( string $session_id, string $message, string $reply, \WP_REST_Request $request ): void {
		$options = Settings::get();

		if ( empty( $options['conversation_email_enabled'] ) ) {
			return;
		}

		$key        = $this->session_key( $session_id );
		$transcript = $this->build_transcript( $request->get_param( 'history' ), $message, $reply );

		set_transient(
			self::TRANSIENT_PREFIX . $key,
			array(
				'name'       => $this->bounded_text( $request->get_param( 'visitor_name' ), 100, false ),
				'transcript' => $transcript,
				'retries'    => 0,
			),
			DAY_IN_SECONDS
		);

		$args  = array( $key );
		$delay = min( 60, max( 1, absint( $options['conversation_email_delay_minutes'] ?? 5 ) ) ) * MINUTE_IN_SECONDS;

		wp_clear_scheduled_hook( self::SEND_HOOK, $args );
		wp_schedule_single_event( time() + $delay, self::SEND_HOOK, $args );
	}

	/**
	 * Cancel the general chat email when the same session becomes a lead.
	 *
	 * @param string $phone      Submitted phone number, unused.
	 * @param string $session_id Verified session UUID.
	 * @return void
	 */
	public function cancel_for_lead( string $phone, string $session_id ): void {
		unset( $phone );
		$key = $this->session_key( $session_id );

		wp_clear_scheduled_hook( self::SEND_HOOK, array( $key ) );
		delete_transient( self::TRANSIENT_PREFIX . $key );
	}

	/**
	 * Send a queued transcript to the configured notification address.
	 *
	 * @param string $key One-way session key.
	 * @return bool
	 */
	public function send( string $key ): bool {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			return false;
		}

		$data = get_transient( self::TRANSIENT_PREFIX . $key );

		if ( ! is_array( $data ) ) {
			return false;
		}

		$options = Settings::get();
		$to      = sanitize_email( (string) $options['lead_notification_email'] );
		$to      = is_email( $to ) ? $to : sanitize_email( (string) get_option( 'admin_email', '' ) );

		if ( '' === $to || ! is_email( $to ) ) {
			return false;
		}

		$name       = $this->bounded_text( $data['name'] ?? '', 100, false );
		$transcript = $this->bounded_text( $data['transcript'] ?? '', 20000 );
		$subject    = sprintf(
			/* translators: %s: visitor name. */
			__( 'New chatbot conversation with %s', 'wp-ds-aichatbot' ),
			'' !== $name ? $name : __( 'website visitor', 'wp-ds-aichatbot' )
		);
		$body = implode(
			"\n",
			array(
				__( 'A visitor started a chatbot conversation but has not submitted a contact request.', 'wp-ds-aichatbot' ),
				'',
				__( 'Name:', 'wp-ds-aichatbot' ) . ' ' . ( '' !== $name ? $name : __( 'Not provided', 'wp-ds-aichatbot' ) ),
				'',
				__( 'Chat transcript:', 'wp-ds-aichatbot' ),
				'' !== $transcript ? $transcript : __( 'No messages yet.', 'wp-ds-aichatbot' ),
			)
		);
		$sent = wp_mail( $to, sanitize_text_field( $subject ), $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );

		if ( $sent ) {
			delete_transient( self::TRANSIENT_PREFIX . $key );
			return true;
		}

		$retries = absint( $data['retries'] ?? 0 );
		if ( $retries < 2 ) {
			$data['retries'] = $retries + 1;
			set_transient( self::TRANSIENT_PREFIX . $key, $data, DAY_IN_SECONDS );
			wp_schedule_single_event( time() + ( 10 * MINUTE_IN_SECONDS ), self::SEND_HOOK, array( $key ) );
		}

		return false;
	}

	/**
	 * Build a chronological transcript from prior history and the latest exchange.
	 *
	 * @param mixed  $history Prior browser history.
	 * @param string $message Latest visitor message.
	 * @param string $reply   Latest assistant reply.
	 * @return string
	 */
	private function build_transcript( $history, string $message, string $reply ): string {
		$lines = array();

		if ( is_array( $history ) ) {
			foreach ( array_slice( $history, -30 ) as $entry ) {
				if ( ! is_array( $entry ) || ! in_array( $entry['role'] ?? '', array( 'user', 'assistant' ), true ) ) {
					continue;
				}

				$content = $this->bounded_text( $entry['content'] ?? '', 4000 );
				if ( '' !== $content ) {
					$lines[] = ( 'user' === $entry['role'] ? __( 'Visitor:', 'wp-ds-aichatbot' ) : __( 'Chatbot:', 'wp-ds-aichatbot' ) ) . ' ' . $content;
				}
			}
		}

		$lines[] = __( 'Visitor:', 'wp-ds-aichatbot' ) . ' ' . $this->bounded_text( $message, 2000 );
		$lines[] = __( 'Chatbot:', 'wp-ds-aichatbot' ) . ' ' . $this->bounded_text( $reply, 4000 );

		return $this->bounded_text( implode( "\n", $lines ), 20000 );
	}

	/**
	 * Generate a non-reversible identifier suitable for cron arguments.
	 *
	 * @param string $session_id Verified session UUID.
	 * @return string
	 */
	private function session_key( string $session_id ): string {
		return substr( hash_hmac( 'sha256', $session_id, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Sanitize and bound visitor-controlled content.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $limit    Maximum characters.
	 * @param bool  $textarea Preserve line breaks.
	 * @return string
	 */
	private function bounded_text( $value, int $limit, bool $textarea = true ): string {
		$value = is_string( $value ) ? $value : '';
		$value = $textarea ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}
}
