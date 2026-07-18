<?php
/**
 * Contract for AI response providers.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

interface ProviderInterface {

	/**
	 * Generate a reply for one visitor message.
	 *
	 * @param string $message    Sanitized visitor message.
	 * @param string $session_id Verified internal session UUID.
	 * @return string|\WP_Error
	 */
	public function generate( string $message, string $session_id );
}
