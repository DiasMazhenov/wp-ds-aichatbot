<?php
/**
 * Server-side re-engagement logic.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Validate and build re-engagement prompts server-side.
 */
final class ReengageService {

	private const TRANSIENT_PREFIX = 'wpdsac_reengage_';
	private const COOLDOWN_MIN     = 30;
	private const ACTIVITY_TTL     = 2 * HOUR_IN_SECONDS;
	private const COUNT_TTL        = 3 * HOUR_IN_SECONDS;

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository|null
	 */
	private $leads;

	/**
	 * Store optional lead repository instance.
	 *
	 * @param LeadRepository|null $leads Lead persistence.
	 */
	public function __construct( ?LeadRepository $leads = null ) {
		$this->leads = $leads;
	}

	/**
	 * Run all server-side guard checks before sending a re-engage prompt.
	 *
	 * @param string                    $session_id Verified session UUID.
	 * @param array<string, mixed>|null $options Plugin settings.
	 * @return array{allowed: bool, reason: string, count: int, max_count: int, retry_after: int}
	 */
	public function guard( string $session_id, ?array $options = null ): array {
		if ( null === $options ) {
			$options = Settings::get();
		}

		if ( empty( $options['reengage_enabled'] ) ) {
			return $this->deny( 'disabled', 0, 0, 0 );
		}

		$max_count = min( 5, max( 0, absint( $options['reengage_max_count'] ?? 1 ) ) );
		if ( 0 === $max_count ) {
			return $this->deny( 'disabled', 0, 0, 0 );
		}

		if ( ! $this->has_activity( $session_id ) ) {
			return $this->deny( 'no_conversation', 0, $max_count, 0 );
		}

		if ( $this->leads instanceof LeadRepository && $this->leads->exists_for_session( $session_id ) ) {
			return $this->deny( 'lead_exists', 0, $max_count, 0 );
		}

		$session_hash = $this->session_hash( $session_id );
		$count        = absint( get_transient( self::TRANSIENT_PREFIX . 'count_' . $session_hash ) );
		$cooldown     = get_transient( self::TRANSIENT_PREFIX . 'cooldown_' . $session_hash );

		if ( false !== $cooldown ) {
			$elapsed = time() - (int) $cooldown;
			$remain  = max( 0, self::COOLDOWN_MIN - $elapsed );

			return $this->deny( 'cooldown', $count, $max_count, $remain );
		}

		if ( $count >= $max_count ) {
			return $this->deny( 'max_reached', $count, $max_count, 0 );
		}

		return array(
			'allowed'     => true,
			'reason'      => 'ok',
			'count'       => $count,
			'max_count'   => $max_count,
			'retry_after' => 0,
		);
	}

	/**
	 * Set cooldown BEFORE the AI call to prevent parallel re-engage requests.
	 *
	 * @param string $session_id Verified session UUID.
	 * @return void
	 */
	public function start_cooldown( string $session_id ): void {
		$session_hash = $this->session_hash( $session_id );
		set_transient(
			self::TRANSIENT_PREFIX . 'cooldown_' . $session_hash,
			time(),
			self::COOLDOWN_MIN
		);
	}

	/**
	 * Increment count only after a successful non-empty reply is confirmed.
	 *
	 * @param string $session_id Verified session UUID.
	 * @return void
	 */
	public function increment_count( string $session_id ): void {
		$session_hash = $this->session_hash( $session_id );
		$current      = absint( get_transient( self::TRANSIENT_PREFIX . 'count_' . $session_hash ) );
		set_transient(
			self::TRANSIENT_PREFIX . 'count_' . $session_hash,
			$current + 1,
			self::COUNT_TTL
		);
	}

	/**
	 * Mark that a real conversation exchange (user message → AI reply) happened.
	 *
	 * @param string $session_id Verified session UUID.
	 * @return void
	 */
	public function mark_activity( string $session_id ): void {
		$session_hash = $this->session_hash( $session_id );
		set_transient(
			self::TRANSIENT_PREFIX . 'active_' . $session_hash,
			time(),
			self::ACTIVITY_TTL
		);
	}

	/**
	 * Listen for successful chat exchanges to enable re-engage.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wpdsac_chat_exchange', array( $this, 'mark_activity' ), 10, 1 );
	}

	/**
	 * Check if the session had at least one real chat exchange.
	 *
	 * @param string $session_id Verified session UUID.
	 * @return bool
	 */
	private function has_activity( string $session_id ): bool {
		$session_hash = $this->session_hash( $session_id );

		return false !== get_transient( self::TRANSIENT_PREFIX . 'active_' . $session_hash );
	}

	/**
	 * Build the re-engage prompt from admin settings.
	 *
	 * @param array<int, mixed>         $history    Validated conversation history.
	 * @param array<string, mixed>|null $options Plugin settings.
	 * @return string
	 */
	public function build_prompt( array $history, ?array $options = null ): string {
		if ( null === $options ) {
			$options = Settings::get();
		}

		$custom = sanitize_textarea_field( (string) ( $options['reengage_instructions'] ?? '' ) );
		$custom = trim( $custom );

		if ( '' !== $custom ) {
			return $custom;
		}

		return __(
			'The visitor has been silent. Write one short, natural follow-up question (max 2 sentences) based on the conversation context. Do not greet, do not repeat what you said before, do not use LLM clichés. Be helpful and specific.',
			'wp-ds-aichatbot'
		);
	}

	/**
	 * HMAC identifier for transient keys.
	 *
	 * @param string $session_id Verified session UUID.
	 * @return string
	 */
	private function session_hash( string $session_id ): string {
		return hash_hmac( 'sha256', 'reengage:' . $session_id, wp_salt( 'auth' ) );
	}

	/**
	/**
	 * Build a consistent denial response with safe public codes.
	 *
	 * @param string $reason     Machine-readable reason code.
	 * @param int    $count      Current re-engage count.
	 * @param int    $max_count  Maximum re-engage count.
	 * @param int    $retry_after Seconds until next retry (cooldown).
	 * @return array{allowed: bool, reason: string, count: int, max_count: int, retry_after: int}
	 */
	private function deny( string $reason, int $count, int $max_count, int $retry_after ): array {
		return array(
			'allowed'     => false,
			'reason'      => $reason,
			'count'       => $count,
			'max_count'   => $max_count,
			'retry_after' => $retry_after,
		);
	}
}
