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

	private $session_id = '';

	/**
	 * @param SessionToken $tokens  Session token service.
	 * @param RateLimiter $limiter Atomic request limiter.
	 */
	public function __construct( SessionToken $tokens, RateLimiter $limiter ) {
		$this->tokens  = $tokens;
		$this->limiter = $limiter;
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
		$options = Settings::get();
		$limit   = $this->limiter->consume_request(
			$this->session_id,
			(int) $options['rate_limit_requests'],
			(int) $options['rate_limit_window']
		);

		if ( ! $limit['allowed'] ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'wpdsac_rate_limited',
					'message' => __( 'Too many chat requests. Please try again later.', 'wp-ds-aichatbot' ),
				),
				429
			);
			$response->header( 'Retry-After', (string) $limit['retry_after'] );
			$response->header( 'X-RateLimit-Remaining', '0' );

			return $response;
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
				'reply'     => wp_kses_post( $reply ),
				'remaining' => (int) $limit['remaining'],
			),
			200
		);
		$response->header( 'X-RateLimit-Remaining', (string) $limit['remaining'] );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
