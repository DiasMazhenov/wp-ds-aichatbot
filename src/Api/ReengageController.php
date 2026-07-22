<?php
/**
 * Re-engagement REST endpoint.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

use DiasMazhenov\WPDsAiChatbot\AI\QuickReplyParser;
use DiasMazhenov\WPDsAiChatbot\AI\ReengageService;

defined( 'ABSPATH' ) || exit;

/**
 * Dedicated endpoint for proactive re-engagement follow-ups.
 */
final class ReengageController {

	/**
	 * Session token service.
	 *
	 * @var SessionToken
	 */
	private $tokens;

	/**
	 * Re-engagement guard.
	 *
	 * @var ReengageService
	 */
	private $reengage;

	/**
	 * Quick reply parser.
	 *
	 * @var QuickReplyParser
	 */
	private $parser;

	/**
	 * In-flight request lock.
	 *
	 * @var RequestLock
	 */
	private $request_lock;

	/**
	 * Rate limiter.
	 *
	 * @var RateLimiter
	 */
	private $limiter;

	/**
	 * Verified session UUID for the current request.
	 *
	 * @var string
	 */
	private $session_id = '';

	/**
	 * Store re-engage dependencies.
	 *
	 * @param SessionToken     $tokens       Session token service.
	 * @param ReengageService  $reengage     Re-engagement guard.
	 * @param QuickReplyParser $parser       Quick reply parser.
	 * @param RequestLock      $request_lock In-flight request lock.
	 * @param RateLimiter      $limiter      Atomic request limiter.
	 */
	public function __construct(
		SessionToken $tokens,
		ReengageService $reengage,
		QuickReplyParser $parser,
		RequestLock $request_lock,
		RateLimiter $limiter
	) {
		$this->tokens       = $tokens;
		$this->reengage     = $reengage;
		$this->parser       = $parser;
		$this->request_lock = $request_lock;
		$this->limiter      = $limiter;
	}

	/**
	 * Register route hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the re-engage REST route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'wp-ds-aichatbot/v1',
			'/reengage',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'respond' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'session'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_session_param' ),
					),
					'history'      => array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => array( $this, 'sanitize_history' ),
						'validate_callback' => array( $this, 'validate_history' ),
					),
					'visitor_name' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_visitor_name' ),
					),
				),
			)
		);
	}

	/**
	 * Validate session token parameter format.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function validate_session_param( $value ): bool {
		return is_string( $value ) && strlen( $value ) <= 1024 && false !== strpos( $value, '.' );
	}

	/**
	 * Validate and set session ID.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public function permissions_check( \WP_REST_Request $request ) {
		$session_id = $this->tokens->validate( (string) $request->get_param( 'session' ) );

		if ( is_wp_error( $session_id ) ) {
			return $session_id;
		}

		$this->session_id = $session_id;

		return true;
	}

	/**
	 * Validate visitor name length.
	 *
	 * @param mixed $value Raw visitor name.
	 * @return bool
	 */
	public function validate_visitor_name( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );

		return $length <= 100;
	}

	/**
	 * Validate conversation history identical to /chat.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function validate_history( $value ): bool {
		if ( ! is_array( $value ) || count( $value ) > 30 ) {
			return false;
		}

		$total_length = 0;

		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) || ! in_array( $entry['role'] ?? '', array( 'user', 'assistant' ), true ) || ! is_string( $entry['content'] ?? null ) ) {
				return false;
			}

			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $entry['content'] ) : strlen( $entry['content'] );

			if ( 0 === $length || $length > 4000 ) {
				return false;
			}

			$total_length += $length;
		}

		return $total_length <= 20000;
	}

	/**
	 * Sanitize history without persistence.
	 *
	 * @param mixed $value Raw history value.
	 * @return array<int, array{role: string, content: string}>
	 */
	public function sanitize_history( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$history = array();

		foreach ( array_slice( $value, -30 ) as $entry ) {
			if ( ! is_array( $entry ) || ! in_array( $entry['role'] ?? '', array( 'user', 'assistant' ), true ) ) {
				continue;
			}

			$content = sanitize_textarea_field( (string) ( $entry['content'] ?? '' ) );
			$content = function_exists( 'mb_substr' ) ? mb_substr( $content, 0, 4000 ) : substr( $content, 0, 4000 );

			if ( '' !== trim( $content ) ) {
				$history[] = array(
					'role'    => (string) $entry['role'],
					'content' => $content,
				);
			}
		}

		return $history;
	}

	/**
	 * Build a safe reengage state structure from guard result.
	 *
	 * @param array $guard Guard result.
	 * @return array
	 */
	private function reengage_state( array $guard ): array {
		return array(
			'allowed'     => (bool) ( $guard['allowed'] ?? false ),
			'reason'      => $guard['reason'] ?? 'disabled',
			'count'       => (int) ( $guard['count'] ?? 0 ),
			'max_count'   => (int) ( $guard['max_count'] ?? 0 ),
			'retry_after' => (int) ( $guard['retry_after'] ?? 0 ),
		);
	}

	/**
	 * Dispatch re-engage follow-up through the AI provider.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function respond( \WP_REST_Request $request ) {
		$guard = $this->reengage->guard( $this->session_id );

		if ( ! $guard['allowed'] ) {
			return new \WP_REST_Response(
				array(
					'reply'         => '',
					'quick_replies' => array(),
					'reengage'      => $this->reengage_state( $guard ),
				),
				200
			);
		}

		$lock_ttl   = min( 120, max( 10, absint( apply_filters( 'wpdsac_request_lock_ttl', 45 ) ) ) );
		$lock_token = $this->request_lock->acquire( $this->session_id, $lock_ttl );

		if ( ! is_string( $lock_token ) ) {
			$guard['reason']  = 'request_in_progress';
			$guard['allowed'] = false;

			return new \WP_REST_Response(
				array(
					'reply'         => '',
					'quick_replies' => array(),
					'reengage'      => $this->reengage_state( $guard ),
				),
				200
			);
		}

		try {
			$options    = \DiasMazhenov\WPDsAiChatbot\Admin\Settings::get();
			$rate_limit = $this->limiter->consume_request(
				$this->session_id,
				(int) $options['rate_limit_requests'],
				(int) $options['rate_limit_window']
			);

			if ( ! $rate_limit['allowed'] ) {
				$guard['reason']      = 'rate_limited';
				$guard['allowed']     = false;
				$guard['retry_after'] = (int) $rate_limit['retry_after'];

				return new \WP_REST_Response(
					array(
						'reply'         => '',
						'quick_replies' => array(),
						'reengage'      => $this->reengage_state( $guard ),
					),
					200
				);
			}

			$budget = $this->limiter->consume_daily_budget( (int) $options['daily_request_limit'] );

			if ( ! $budget['allowed'] ) {
				$guard['reason']      = 'daily_limit';
				$guard['allowed']     = false;
				$guard['retry_after'] = (int) $budget['retry_after'];

				return new \WP_REST_Response(
					array(
						'reply'         => '',
						'quick_replies' => array(),
						'reengage'      => $this->reengage_state( $guard ),
					),
					200
				);
			}

			$this->reengage->start_cooldown( $this->session_id );

			$history      = (array) $request->get_param( 'history' );
			$prompt       = $this->reengage->build_prompt( $history );
			$visitor_name = trim( sanitize_text_field( (string) $request->get_param( 'visitor_name' ) ) );

			/**
			 * Filter the re-engage prompt before sending to the AI provider.
			 *
			 * @param string            $prompt     Re-engage prompt text.
			 * @param string            $session_id Verified session UUID.
			 * @param array<int, mixed> $history    Conversation history.
			 * @param string            $visitor_name Visitor name.
			 */
			$prompt = apply_filters( 'wpdsac_reengage_prompt', $prompt, $this->session_id, $history, $visitor_name );

			$request_data = new \WP_REST_Request( 'POST' );
			$request_data->set_param( 'session', $request->get_param( 'session' ) );
			$request_data->set_param( 'message', $prompt );
			$request_data->set_param( 'visitor_name', $visitor_name );
			$request_data->set_param( 'history', $history );

			$reply = apply_filters( 'wpdsac_reengage_exchange', null, $prompt, $this->session_id, $request_data );

			if ( is_wp_error( $reply ) || ( ! is_string( $reply ) && null !== $reply ) ) {
				$guard['reason']  = 'provider_error';
				$guard['allowed'] = false;

				return new \WP_REST_Response(
					array(
						'reply'         => '',
						'quick_replies' => array(),
						'reengage'      => $this->reengage_state( $guard ),
					),
					200
				);
			}

			if ( null === $reply || '' === trim( (string) $reply ) ) {
				$guard['reason']  = 'empty_reply';
				$guard['allowed'] = false;

				return new \WP_REST_Response(
					array(
						'reply'         => '',
						'quick_replies' => array(),
						'reengage'      => $this->reengage_state( $guard ),
					),
					200
				);
			}

			$parsed = $this->parser->parse( $reply );

			if ( '' !== trim( $parsed['reply'] ) ) {
				$this->reengage->increment_count( $this->session_id );
				$guard['count'] = $guard['count'] + 1;
			}

			/**
			 * Fires after a re-engage exchange completes with a non-empty reply.
			 *
			 * @param string $session_id      Verified session UUID.
			 * @param string $prompt          Re-engage prompt sent.
			 * @param string $reply           Clean AI reply.
			 * @param array  $quick_replies   Parsed quick replies.
			 */
			do_action( 'wpdsac_reengage_exchange_completed', $this->session_id, $prompt, $parsed['reply'], $parsed['quick_replies'] );

			$response = new \WP_REST_Response(
				array(
					'reply'         => sanitize_textarea_field( $parsed['reply'] ),
					'quick_replies' => $parsed['quick_replies'],
					'reengage'      => $this->reengage_state( $guard ),
				),
				200
			);
			$response->header( 'Cache-Control', 'no-store' );

			return $response;
		} finally {
			$this->request_lock->release( $this->session_id, $lock_token );
		}
	}
}
