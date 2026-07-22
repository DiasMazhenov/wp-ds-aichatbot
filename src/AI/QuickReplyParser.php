<?php
/**
 * Extract and validate [[WPDSAC_QA|...]] markers from provider responses.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Parse quick reply variants out of an AI reply, returning clean text.
 */
final class QuickReplyParser {

	private const MARKER = '/\[\[WPDSAC_QA\|([^|\]]+)\|message\|([^\]]+)\]\]/u';

	private const MAX_VARIANTS  = 5;
	private const MIN_VARIANTS  = 2;
	private const MAX_LABEL_LEN = 80;
	private const MAX_MSG_LEN   = 500;

	/**
	 * Parse a provider reply and separate clean text from quick replies.
	 *
	 * @param string $reply Raw provider output possibly containing markers.
	 * @return array{reply: string, quick_replies: array<int, array{label: string, message: string}>}
	 */
	public function parse( string $reply ): array {
		$matches       = array();
		$quick_replies = array();
		$clean_reply   = $reply;

		if ( 0 === preg_match_all( self::MARKER, $reply, $matches, PREG_SET_ORDER ) ) {
			return array(
				'reply'         => $reply,
				'quick_replies' => array(),
			);
		}

		foreach ( $matches as $match ) {
			$label   = $this->sanitize_label( $match[1] );
			$message = $this->sanitize_message( $match[2] );

			if ( '' === $label || '' === $message ) {
				continue;
			}

			$quick_replies[] = array(
				'label'   => $label,
				'message' => $message,
			);
		}

		$quick_replies = $this->validate_bounds( $quick_replies );

		$clean_reply = preg_replace( self::MARKER, '', $reply );
		$clean_reply = trim( preg_replace( '/\n{3,}/', "\n\n", (string) $clean_reply ) );

		if ( '' === $clean_reply ) {
			$clean_reply = $reply;
		}

		return array(
			'reply'         => $clean_reply,
			'quick_replies' => $quick_replies,
		);
	}

	/**
	 * Strip HTML, marker characters and bound label length.
	 *
	 * @param string $raw Raw label from marker.
	 * @return string
	 */
	private function sanitize_label( string $raw ): string {
		$label = wp_strip_all_tags( $raw );
		$label = str_replace( array( '|', '[', ']' ), '', $label );
		$label = sanitize_text_field( $label );
		$label = trim( $label );

		if ( '' === $label ) {
			return '';
		}

		return function_exists( 'mb_substr' )
			? mb_substr( $label, 0, self::MAX_LABEL_LEN )
			: substr( $label, 0, self::MAX_LABEL_LEN );
	}

	/**
	 * Strip HTML and bound message length.
	 *
	 * @param string $raw Raw message from marker.
	 * @return string
	 */
	private function sanitize_message( string $raw ): string {
		$message = wp_strip_all_tags( $raw );
		$message = str_replace( array( '|', '[', ']' ), '', $message );
		$message = sanitize_textarea_field( $message );
		$message = trim( $message );

		if ( '' === $message ) {
			return '';
		}

		return function_exists( 'mb_substr' )
			? mb_substr( $message, 0, self::MAX_MSG_LEN )
			: substr( $message, 0, self::MAX_MSG_LEN );
	}

	/**
	 * Enforce the 2–5 variant count limit.
	 *
	 * @param array<int, array{label: string, message: string}> $replies Candidate replies.
	 * @return array<int, array{label: string, message: string}>
	 */
	private function validate_bounds( array $replies ): array {
		$count = count( $replies );

		if ( $count < self::MIN_VARIANTS || $count > self::MAX_VARIANTS ) {
			return array();
		}

		return array_slice( $replies, 0, self::MAX_VARIANTS );
	}
}
