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

	/**
	 * Stream a reply, calling $on_delta for each text fragment.
	 *
	 * Providers that support SSE streaming override this with a real curl
	 * stream. The default contract is: accumulate text, invoke the callback
	 * per chunk, then return the complete text so post-processing (greeting
	 * removal, quick-reply parsing) can run on the full reply.
	 *
	 * @param string   $message    Sanitized visitor message.
	 * @param string   $session_id Verified internal session UUID.
	 * @param callable $on_delta  Receives one string argument per text fragment.
	 * @return string|\WP_Error  Full accumulated text, or WP_Error on failure.
	 */
	public function stream( string $message, string $session_id, callable $on_delta );
}
