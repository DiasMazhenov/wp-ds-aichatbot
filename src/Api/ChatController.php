<?php
/**
 * Public chat REST endpoint.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

final class ChatController {

	private const REST_NAMESPACE = 'wp-ds-aichatbot/v1';

	private $tokens;

	private $limiter;

	private $request_lock;

	private $session_id = '';

	/**
	 * @param SessionToken $tokens       Session token service.
	 * @param RateLimiter $limiter      Atomic request limiter.
	 * @param RequestLock $request_lock In-flight request lock.
	 */
	public function __construct( SessionToken $tokens, RateLimiter $limiter, RequestLock $request_lock ) {
		$this->tokens       = $tokens;
		$this->limiter      = $limiter;
		$this->request_lock = $request_lock;
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
	 * Register the chat endpoint and request schema.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/chat',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'respond' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'session' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_session_token' ),
					),
					'message' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => array( $this, 'validate_message' ),
					),
				),
			)
		);
	}

	/**
	 * Reject malformed or oversized session tokens before signature validation.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function validate_session_token( $value ): bool {
		return is_string( $value ) && strlen( $value ) <= 1024 && false !== strpos( $value, '.' );
	}

	/**
	 * Validate the signed session before dispatch.
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
	 * Validate the public message length.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function validate_message( $value ): bool {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );

		return $length <= 2000;
	}

	/**
	 * Rate-limit and dispatch the message to the provider extension point.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function respond( \WP_REST_Request $request ) {
		$lock_ttl   = min( 120, max( 10, absint( apply_filters( 'wpdsac_request_lock_ttl', 45 ) ) ) );
		$lock_token = $this->request_lock->acquire( $this->session_id, $lock_ttl );

		if ( ! is_string( $lock_token ) ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'wpdsac_request_in_progress',
					'message' => __( 'A chat request is already in progress. Please wait.', 'wp-ds-aichatbot' ),
				),
				409
			);
			$response->header( 'Retry-After', '3' );

			return $response;
		}

		try {
			$options = Settings::get();
			$limit   = $this->limiter->consume_request(
				$this->session_id,
				(int) $options['rate_limit_requests'],
				(int) $options['rate_limit_window']
			);

			if ( ! $limit['allowed'] ) {
				return $this->limit_response(
					'wpdsac_rate_limited',
					__( 'Too many chat requests. Please try again later.', 'wp-ds-aichatbot' ),
					(int) $limit['retry_after'],
					'X-RateLimit-Remaining'
				);
			}

			$budget = $this->limiter->consume_daily_budget( (int) $options['daily_request_limit'] );

			if ( ! $budget['allowed'] ) {
				return $this->limit_response(
					'wpdsac_daily_budget_exhausted',
					__( 'The daily AI request budget has been reached. Please try again later.', 'wp-ds-aichatbot' ),
					(int) $budget['retry_after'],
					'X-Daily-Budget-Remaining'
				);
			}

			$message = (string) $request->get_param( 'message' );
			$reply   = apply_filters( 'wpdsac_chat_reply', null, $message, $this->session_id, $request );

			if ( is_wp_error( $reply ) ) {
				return $reply;
			}

			if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
				return new \WP_Error(
					'wpdsac_provider_unavailable',
					__( 'The AI provider is not configured yet.', 'wp-ds-aichatbot' ),
					array( 'status' => 503 )
				);
			}

			$response = new \WP_REST_Response(
				array(
					'reply'           => sanitize_textarea_field( $reply ),
					'remaining'       => (int) $limit['remaining'],
					'daily_remaining' => (int) $budget['remaining'],
				),
				200
			);
			$response->header( 'X-RateLimit-Remaining', (string) $limit['remaining'] );
			$response->header( 'X-Daily-Budget-Remaining', (string) $budget['remaining'] );
			$response->header( 'Cache-Control', 'no-store' );

			return $response;
		} finally {
			$this->request_lock->release( $this->session_id, $lock_token );
		}
	}

	/**
	 * Build a consistent HTTP 429 response.
	 *
	 * @param string $code        Public error code.
	 * @param string $message     Public error message.
	 * @param int    $retry_after Retry delay in seconds.
	 * @param string $header      Remaining-budget header name.
	 * @return \WP_REST_Response
	 */
	private function limit_response( string $code, string $message, int $retry_after, string $header ): \WP_REST_Response {
		$response = new \WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
			),
			429
		);
		$response->header( 'Retry-After', (string) $retry_after );
		$response->header( $header, '0' );

		return $response;
	}
}
