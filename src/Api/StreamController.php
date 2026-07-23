<?php
/**
 * Public chat streaming REST endpoint.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\AI\QuickReplyParser;
use DiasMazhenov\WPDsAiChatbot\Security\UrlDenylist;

defined( 'ABSPATH' ) || exit;

/**
 * Stream AI replies via Server-Sent Events.
 *
 * Reuses ChatController's validation pattern but writes SSE frames
 * directly instead of returning a WP_REST_Response.
 */
final class StreamController {

	private const REST_NAMESPACE = 'wp-ds-aichatbot/v1';

	/**
	 * Session token service.
	 *
	 * @var SessionToken
	 */
	private $tokens;

	/**
	 * Atomic request limiter.
	 *
	 * @var RateLimiter
	 */
	private $limiter;

	/**
	 * In-flight request lock.
	 *
	 * @var RequestLock
	 */
	private $request_lock;

	/**
	 * Verified session UUID for the current request.
	 *
	 * @var string
	 */
	private $session_id = '';

	/**
	 * Store REST endpoint dependencies.
	 *
	 * @param SessionToken $tokens       Session token service.
	 * @param RateLimiter  $limiter      Atomic request limiter.
	 * @param RequestLock  $request_lock In-flight request lock.
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
	 * Register the streaming chat endpoint and request schema.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/chat/stream',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'stream' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'session'            => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_session_token' ),
					),
					'message'            => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => array( $this, 'validate_message' ),
					),
					'visitor_name'       => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_visitor_name' ),
					),
					'history'            => array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => array( $this, 'sanitize_history' ),
						'validate_callback' => array( $this, 'validate_history' ),
					),
					'navigation_targets' => array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => array( $this, 'sanitize_navigation_targets' ),
						'validate_callback' => array( $this, 'validate_navigation_targets' ),
					),
				),
			)
		);
	}

	/**
	 * Validate malformed session tokens before signature validation.
	 *
	 * Mirrors ChatController::validate_session_token().
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
	 * Validate the optional visitor name.
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
	 * Validate bounded browser conversation history.
	 *
	 * @param mixed $value Raw history value.
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
	 * Sanitize conversation history.
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
	 * Validate bounded navigation allowlist.
	 *
	 * @param mixed $value Raw target rows.
	 * @return bool
	 */
	public function validate_navigation_targets( $value ): bool {
		if ( ! is_array( $value ) || count( $value ) > 40 ) {
			return false;
		}

		foreach ( $value as $target ) {
			if ( ! is_array( $target ) || ! is_string( $target['label'] ?? null ) || ! is_string( $target['url'] ?? null ) ) {
				return false;
			}

			if ( self::length( $target['label'] ) > 120 || self::length( $target['url'] ) > 500 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize navigation targets to same-origin HTTP(S) destinations.
	 *
	 * @param mixed $value Raw target rows.
	 * @return array<int, array{label: string, url: string}>
	 */
	public function sanitize_navigation_targets( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$home_url    = home_url( '/' );
		$home_scheme = strtolower( (string) wp_parse_url( $home_url, PHP_URL_SCHEME ) );
		$home_host   = strtolower( (string) wp_parse_url( $home_url, PHP_URL_HOST ) );
		$home_port   = absint( wp_parse_url( $home_url, PHP_URL_PORT ) );
		$targets     = array();

		foreach ( array_slice( $value, 0, 40 ) as $target ) {
			if ( ! is_array( $target ) ) {
				continue;
			}

			$label  = str_replace( array( '|', '[', ']' ), '', sanitize_text_field( (string) ( $target['label'] ?? '' ) ) );
			$url    = esc_url_raw( (string) ( $target['url'] ?? '' ), array( 'http', 'https' ) );
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			$port   = absint( wp_parse_url( $url, PHP_URL_PORT ) );

			if ( '' === $label || '' === $url || '' === $home_host || $home_scheme !== $scheme || $home_host !== $host || $home_port !== $port ) {
				continue;
			}

			if ( UrlDenylist::is_blocked( $url ) ) {
				continue;
			}

			$targets[] = array(
				'label' => self::slice( $label, 120 ),
				'url'   => self::slice( str_replace( '|', '%7C', $url ), 500 ),
			);
		}

		return $targets;
	}

	/**
	 * Rate-limit and stream the AI reply via Server-Sent Events.
	 *
	 * Writes SSE frames directly to the response body and terminates the
	 * request with exit to prevent WordPress JSON serialization.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return void  Does not return — calls exit after streaming.
	 */
	public function stream( \WP_REST_Request $request ) {
		$this->send_sse_headers();

		$lock_ttl   = min( 120, max( 10, absint( apply_filters( 'wpdsac_request_lock_ttl', 45 ) ) ) );
		$lock_token = $this->request_lock->acquire( $this->session_id, $lock_ttl );

		if ( ! is_string( $lock_token ) ) {
			$this->send_sse_error( 'wpdsac_request_in_progress', __( 'A chat request is already in progress. Please wait.', 'wp-ds-aichatbot' ) );
			$this->finish_sse();

			return;
		}

		try {
			$options = Settings::get();
			$limit   = $this->limiter->consume_request(
				$this->session_id,
				(int) $options['rate_limit_requests'],
				(int) $options['rate_limit_window']
			);

			if ( ! $limit['allowed'] ) {
				$this->send_sse_event(
					'rate_limit',
					array(
						'code'        => 'wpdsac_rate_limited',
						'message'     => __( 'Too many chat requests. Please try again later.', 'wp-ds-aichatbot' ),
						'retry_after' => (int) $limit['retry_after'],
					)
				);

				return;
			}

			$budget = $this->limiter->consume_daily_budget( (int) $options['daily_request_limit'] );

			if ( ! $budget['allowed'] ) {
				$this->send_sse_event(
					'rate_limit',
					array(
						'code'        => 'wpdsac_daily_budget_exhausted',
						'message'     => __( 'The daily AI request budget has been reached. Please try again later.', 'wp-ds-aichatbot' ),
						'retry_after' => (int) $budget['retry_after'],
					)
				);

				return;
			}

			$message   = (string) $request->get_param( 'message' );
			$manager   = $this->resolve_manager();
			$full_text = '';

			$reply = $manager->stream(
				$message,
				$this->session_id,
				$request,
				function ( string $fragment ) use ( &$full_text ) {
					$full_text .= $fragment;
					$this->send_sse_event( 'delta', array( 'content' => $fragment ) );
				}
			);

			if ( is_wp_error( $reply ) ) {
				$this->send_sse_error( $reply->get_error_code(), $reply->get_error_message() );

				return;
			}

			$final_reply = is_string( $reply ) && '' !== trim( $reply ) ? $reply : $full_text;

			if ( ! is_string( $final_reply ) || '' === trim( $final_reply ) ) {
				$this->send_sse_error( 'wpdsac_provider_unavailable', __( 'The AI provider is not configured yet.', 'wp-ds-aichatbot' ) );

				return;
			}

			$parsed = ( new QuickReplyParser() )->parse( $final_reply );

			$this->send_sse_event(
				'done',
				array(
					'reply'           => sanitize_textarea_field( $parsed['reply'] ),
					'quick_replies'   => $parsed['quick_replies'],
					'remaining'       => (int) $limit['remaining'],
					'daily_remaining' => (int) $budget['remaining'],
				)
			);

			/**
			 * Fires after a successful streaming chat exchange.
			 *
			 * @param string           $session_id Verified session UUID.
			 * @param string           $message    Visitor message.
			 * @param string           $reply      Clean visitor-facing AI reply.
			 * @param \WP_REST_Request $request    REST request.
			 */
			do_action( 'wpdsac_chat_exchange', $this->session_id, $message, $parsed['reply'], $request );
		} catch ( \Throwable $e ) {
			/** This action is documented in ProviderManager::generate(). */
			do_action( 'wpdsac_provider_exception', $e );

			$this->send_sse_error( 'wpdsac_fatal', __( 'The AI service encountered an unexpected error. Please try again later.', 'wp-ds-aichatbot' ) );
		} finally {
			$this->request_lock->release( $this->session_id, $lock_token );
			$this->finish_sse();
		}
	}

	/**
	 * Resolve ProviderManager from the global plugin container.
	 *
	 * @return \DiasMazhenov\WPDsAiChatbot\AI\ProviderManager
	 */
	private function resolve_manager(): \DiasMazhenov\WPDsAiChatbot\AI\ProviderManager {
		$plugin = \DiasMazhenov\WPDsAiChatbot\Plugin::instance();

		return $plugin->provider_manager();
	}

	/**
	 * Write SSE response headers.
	 *
	 * @return void
	 */
	private function send_sse_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'X-Accel-Buffering: no' );

		if ( function_exists( 'apache_setenv' ) ) {
			apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv -- Required to disable gzip for SSE streaming.
		}

		if ( function_exists( 'ini_set' ) ) {
			ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Disabling response buffering is required for SSE streaming.
		}

		if ( function_exists( 'ob_get_level' ) && ob_get_level() > 0 ) {
			while ( ob_get_level() > 0 ) {
				ob_end_flush();
			}
		}

		flush();
	}

	/**
	 * Send a single SSE event frame.
	 *
	 * @param string               $event Event name (delta, done, error, rate_limit).
	 * @param array<string, mixed> $data  Data payload.
	 * @return void
	 */
	private function send_sse_event( string $event, array $data ): void {
		$json = wp_json_encode( $data );

		if ( ! is_string( $json ) ) {
			return;
		}

		echo "event: {$event}\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE frame, not HTML output.
		echo "data: {$json}\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE frame with JSON-encoded payload.

		if ( function_exists( 'ob_get_level' ) && ob_get_level() > 0 ) {
			while ( ob_get_level() > 0 ) {
				ob_end_flush();
			}
		}

		flush();
	}

	/**
	 * Send an SSE error event.
	 *
	 * @param string $code    Public error code.
	 * @param string $message Public error message.
	 * @return void
	 */
	private function send_sse_error( string $code, string $message ): void {
		$this->send_sse_event(
			'error',
			array(
				'code'    => $code,
				'message' => $message,
			)
		);
	}

	/**
	 * Send the terminal [DONE] event and close the connection.
	 *
	 * @return void
	 */
	private function finish_sse(): void {
		echo "data: [DONE]\n\n";

		if ( function_exists( 'ob_get_level' ) && ob_get_level() > 0 ) {
			while ( ob_get_level() > 0 ) {
				ob_end_flush();
			}
		}

		flush();
		exit;
	}

	/**
	 * Count Unicode characters when available.
	 *
	 * @param string $value Input text.
	 * @return int
	 */
	private static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * Slice Unicode text when available.
	 *
	 * @param string $value Input text.
	 * @param int    $limit Maximum characters.
	 * @return string
	 */
	private static function slice( string $value, int $limit ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}
}
