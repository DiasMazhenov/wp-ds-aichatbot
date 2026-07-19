<?php
/**
 * Lead email notifications.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Data;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Send a bounded plain-text lead summary and transcript through WordPress mail.
 */
final class LeadNotifier {

	/**
	 * Send one administrator notification.
	 *
	 * @param array<string, string> $lead Lead fields.
	 * @return bool
	 */
	public function send( array $lead ): bool {
		$options = Settings::get();
		$to      = sanitize_email( (string) $options['lead_notification_email'] );

		if ( '' === $to || ! is_email( $to ) ) {
			return false;
		}

		$name       = sanitize_text_field( $lead['name'] ?? '' );
		$phone      = sanitize_text_field( $lead['phone'] ?? '' );
		$request    = $this->bounded_text( $lead['request'] ?? '', 4000 );
		$transcript = $this->bounded_text( $lead['transcript'] ?? '', 20000 );
		$subject    = sprintf(
			/* translators: %s: visitor name. */
			__( 'New chatbot request from %s', 'wp-ds-aichatbot' ),
			'' !== $name ? $name : __( 'website visitor', 'wp-ds-aichatbot' )
		);
		$body = implode(
			"\n",
			array(
				__( 'A visitor submitted a contact request in the chatbot.', 'wp-ds-aichatbot' ),
				'',
				__( 'Name:', 'wp-ds-aichatbot' ) . ' ' . $name,
				__( 'Phone:', 'wp-ds-aichatbot' ) . ' ' . $phone,
				__( 'Request:', 'wp-ds-aichatbot' ) . ' ' . $request,
				'',
				__( 'Chat transcript:', 'wp-ds-aichatbot' ),
				'' !== $transcript ? $transcript : __( 'No messages yet.', 'wp-ds-aichatbot' ),
			)
		);
		return wp_mail( $to, sanitize_text_field( $subject ), $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * Sanitize and bound visitor-controlled text.
	 *
	 * @param mixed $value Raw text.
	 * @param int   $limit Maximum characters.
	 * @return string
	 */
	private function bounded_text( $value, int $limit ): string {
		$value = sanitize_textarea_field( is_string( $value ) ? $value : '' );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}
}
