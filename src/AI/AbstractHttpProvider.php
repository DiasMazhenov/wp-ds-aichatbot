<?php
/**
 * Shared secure HTTP workflow for direct AI providers.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Base implementation for direct JSON-over-HTTPS AI providers.
 */
abstract class AbstractHttpProvider implements ProviderInterface {

	/**
	 * Server-side credential resolver.
	 *
	 * @var CredentialResolver
	 */
	protected $credentials;

	/**
	 * Store the credential resolver.
	 *
	 * @param CredentialResolver $credentials Credential resolver.
	 */
	public function __construct( CredentialResolver $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Generate one non-streaming provider response.
	 *
	 * @param string $message    Sanitized visitor message.
	 * @param string $session_id Verified internal session UUID.
	 * @return string|\WP_Error
	 */
	final public function generate( string $message, string $session_id ) {
		$provider_id = $this->provider_id();
		$api_key     = $this->credentials->get_api_key( $provider_id );

		if ( '' === $api_key ) {
			return new \WP_Error(
				'wpdsac_provider_not_configured',
				__( 'The selected AI provider is not configured yet.', 'wp-ds-aichatbot' ),
				array( 'status' => 503 )
			);
		}

		$options                    = Settings::get();
		$options['ai_instructions'] = PromptGuard::protected_instructions(
			(string) $options['ai_instructions'],
			(string) $options['topic_scope'],
			(string) $options['guard_refusal_message'],
			(string) $options['title']
		);
		$body                       = $this->request_body( $message, $session_id, $options );

		/**
		 * Filter every direct provider request body.
		 *
		 * @param array<string, mixed> $body        Request body.
		 * @param string               $provider_id Provider identifier.
		 * @param string               $session_id  Verified session UUID.
		 */
		$body = apply_filters( 'wpdsac_ai_request_body', $body, $provider_id, $session_id );

		/** This filter is documented by each concrete provider integration. */
		$body = apply_filters( 'wpdsac_' . $provider_id . '_request_body', $body, $session_id );

		if ( ! is_array( $body ) ) {
			$this->report_error( 0, '', 'invalid_request_body' );

			return $this->unavailable_error();
		}

		$encoded_body = wp_json_encode( $body );

		if ( ! is_string( $encoded_body ) ) {
			$this->report_error( 0, '', 'json_encode_failed' );

			return $this->unavailable_error();
		}

		$response = wp_safe_remote_post(
			$this->endpoint( $options ),
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => $this->request_headers( $api_key ),
				'body'        => $encoded_body,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->report_error( 0, '', $response->get_error_code() );

			return $this->unavailable_error();
		}

		$status     = (int) wp_remote_retrieve_response_code( $response );
		$request_id = $this->request_id( $response );
		$decoded    = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$error_code = $this->extract_error_code( $decoded );

			$this->report_error( $status, $request_id, $error_code );

			if ( 429 === $status ) {
				return new \WP_Error(
					'wpdsac_provider_rate_limited',
					__( 'The AI service is busy. Please try again later.', 'wp-ds-aichatbot' ),
					array( 'status' => 429 )
				);
			}

			return $this->unavailable_error();
		}

		if ( ! is_array( $decoded ) ) {
			$this->report_error( $status, $request_id, 'invalid_json' );

			return $this->unavailable_error();
		}

		$output = trim( $this->extract_output_text( $decoded ) );

		if ( '' === $output ) {
			$this->report_error( $status, $request_id, 'empty_output' );

			return $this->unavailable_error();
		}

		return $output;
	}

	/**
	 * Stream a reply via raw curl, invoking $on_delta for each text fragment.
	 *
	 * Falls back to generate() when curl is unavailable or the provider does
	 * not implement extract_stream_delta().
	 *
	 * @param string   $message    Sanitized visitor message.
	 * @param string   $session_id Verified internal session UUID.
	 * @param callable $on_delta  Callback receiving each text fragment.
	 * @return string|\WP_Error
	 */
	public function stream( string $message, string $session_id, callable $on_delta ) {
		if ( ! function_exists( 'curl_init' ) || ! $this->supports_streaming() ) {
			$result = $this->generate( $message, $session_id );

			if ( is_string( $result ) && '' !== trim( $result ) ) {
				$on_delta( $result );
			}

			return $result;
		}

		$provider_id = $this->provider_id();
		$api_key      = $this->credentials->get_api_key( $provider_id );

		if ( '' === $api_key ) {
			return new \WP_Error(
				'wpdsac_provider_not_configured',
				__( 'The selected AI provider is not configured yet.', 'wp-ds-aichatbot' ),
				array( 'status' => 503 )
			);
		}

		$options                    = Settings::get();
		$options['ai_instructions'] = PromptGuard::protected_instructions(
			(string) $options['ai_instructions'],
			(string) $options['topic_scope'],
			(string) $options['guard_refusal_message'],
			(string) $options['title']
		);
		$body                       = $this->stream_request_body( $message, $session_id, $options );

		/** This filter is documented in AbstractHttpProvider::generate(). */
		$body = apply_filters( 'wpdsac_ai_request_body', $body, $provider_id, $session_id );
		$body = apply_filters( 'wpdsac_' . $provider_id . '_request_body', $body, $session_id );

		if ( ! is_array( $body ) ) {
			$this->report_error( 0, '', 'invalid_request_body' );

			return $this->unavailable_error();
		}

		$body['stream'] = true;

		$encoded_body = wp_json_encode( $body );

		if ( ! is_string( $encoded_body ) ) {
			$this->report_error( 0, '', 'json_encode_failed' );

			return $this->unavailable_error();
		}

		$full_text = '';
		$endpoint  = $this->endpoint( $options );
		$hdr       = $this->curl_headers( $this->request_headers( $api_key ) );

		$ch = curl_init( $endpoint );

		if ( false === $ch ) {
			$this->report_error( 0, '', 'curl_init_failed' );

			return $this->unavailable_error();
		}

		curl_setopt( $ch, \CURLOPT_POST, true );
		curl_setopt( $ch, \CURLOPT_POSTFIELDS, $encoded_body );
		curl_setopt( $ch, \CURLOPT_HTTPHEADER, $hdr );
		curl_setopt( $ch, \CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, \CURLOPT_TIMEOUT, 60 );
		curl_setopt( $ch, \CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $ch, \CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, \CURLOPT_FOLLOWLOCATION, false );
		curl_setopt( $ch, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTPS );
		curl_setopt(
			$ch,
			\CURLOPT_WRITEFUNCTION,
			static function ( $curl, $data ) use ( &$full_text, $on_delta, $provider_id ) {
				$length = strlen( $data );

				if ( $length > 0 ) {
					$delta = static::extract_stream_delta( $data );

					if ( '' !== $delta ) {
						$full_text .= $delta;
						$on_delta( $delta );
					}
				}

				return $length;
			}
		);

		$exec_result = curl_exec( $ch );
		$status      = (int) curl_getinfo( $ch, \CURLINFO_RESPONSE_CODE );
		$curl_error  = curl_error( $ch );
		$request_id  = $this->curl_request_id( $ch );

		if ( false === $exec_result || $status < 200 || $status >= 300 ) {
			$this->report_error( $status, $request_id, '' !== $curl_error ? 'curl_error' : 'stream_http_error' );

			if ( '' !== trim( $full_text ) ) {
				return trim( $full_text );
			}

			if ( 429 === $status ) {
				return new \WP_Error(
					'wpdsac_provider_rate_limited',
					__( 'The AI service is busy. Please try again later.', 'wp-ds-aichatbot' ),
					array( 'status' => 429 )
				);
			}

			return $this->unavailable_error();
		}

		$full_text = trim( $full_text );

		if ( '' === $full_text ) {
			$this->report_error( $status, $request_id, 'empty_stream_output' );

			return $this->unavailable_error();
		}

		return $full_text;
	}

	/**
	 * Whether this provider implements real SSE streaming.
	 *
	 * Override in concrete providers that parse SSE deltas.
	 *
	 * @return bool
	 */
	protected function supports_streaming(): bool {
		return false;
	}

	/**
	 * Build the streaming request body.
	 *
	 * Concrete providers can override to adjust the body for streaming.
	 * The default implementation delegates to request_body().
	 *
	 * @param string               $message    Visitor message.
	 * @param string               $session_id Session UUID.
	 * @param array<string, mixed> $options    Plugin settings.
	 * @return array<string, mixed>
	 */
	protected function stream_request_body( string $message, string $session_id, array $options ): array {
		return $this->request_body( $message, $session_id, $options );
	}

	/**
	 * Extract text from a raw SSE chunk received via curl.
	 *
	 * Override in concrete providers. The default returns an empty string,
	 * which causes stream() to fall back to generate().
	 *
	 * @param string $raw Raw chunk from curl WRITEFUNCTION.
	 * @return string
	 */
	protected static function extract_stream_delta( string $raw ): string {
		return '';
	}

	/**
	 * Convert an associative header array to curl's `Header: Value` list.
	 *
	 * @param array<string, string> $headers Associative headers.
	 * @return list<string>
	 */
	protected function curl_headers( array $headers ): array {
		$lines = array();

		foreach ( $headers as $name => $value ) {
			$lines[] = $name . ': ' . $value;
		}

		return $lines;
	}

	/**
	 * Read a request-id header from a curl handle.
	 *
	 * When streaming with RETURNTRANSFER=false, response headers are not
	 * directly accessible via curl. Returns an empty string — the request-id
	 * is only used for optional sanitized diagnostics.
	 *
	 * @param \CurlHandle|resource $ch Curl handle.
	 * @return string
	 */
	protected function curl_request_id( $ch ): string {
		return '';
	}

	/**
	 * Return the provider identifier.
	 *
	 * @return string
	 */
	abstract protected function provider_id(): string;

	/**
	 * Return the provider endpoint.
	 *
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	abstract protected function endpoint( array $options ): string;

	/**
	 * Build the provider request body.
	 *
	 * @param string               $message    Visitor message.
	 * @param string               $session_id Session UUID.
	 * @param array<string, mixed> $options    Plugin settings.
	 * @return array<string, mixed>
	 */
	abstract protected function request_body( string $message, string $session_id, array $options ): array;

	/**
	 * Extract displayable text from a decoded provider response.
	 *
	 * @param array<string, mixed> $response Decoded provider response.
	 * @return string
	 */
	abstract protected function extract_output_text( array $response ): string;

	/**
	 * Build provider authentication headers.
	 *
	 * @param string $api_key Provider API key.
	 * @return array<string, string>
	 */
	protected function request_headers( string $api_key ): array {
		return array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Read a provider request ID without exposing response content.
	 *
	 * @param mixed $response WordPress HTTP response.
	 * @return string
	 */
	protected function request_id( $response ): string {
		$request_id = wp_remote_retrieve_header( $response, 'x-request-id' );

		if ( ! is_string( $request_id ) || '' === $request_id ) {
			$request_id = wp_remote_retrieve_header( $response, 'request-id' );
		}

		return is_string( $request_id ) ? $request_id : '';
	}

	/**
	 * Extract only a safe machine-readable provider error code.
	 *
	 * @param mixed $response Decoded response.
	 * @return string
	 */
	private function extract_error_code( $response ): string {
		if ( ! is_array( $response ) ) {
			return 'http_error';
		}

		$error = isset( $response['error'] ) && is_array( $response['error'] ) ? $response['error'] : array();

		foreach ( array( $error['code'] ?? null, $error['type'] ?? null, $response['error_type'] ?? null ) as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				return sanitize_key( $candidate );
			}
		}

		return 'http_error';
	}

	/**
	 * Emit sanitized diagnostics for optional logging integrations.
	 *
	 * @param int    $status     HTTP status, or zero for transport failures.
	 * @param string $request_id Provider request ID.
	 * @param string $error_code Sanitized error code.
	 * @return void
	 */
	private function report_error( int $status, string $request_id, string $error_code ): void {
		$context = array(
			'provider'   => $this->provider_id(),
			'status'     => $status,
			'request_id' => sanitize_text_field( $request_id ),
			'error_code' => sanitize_key( $error_code ),
		);

		do_action( 'wpdsac_ai_provider_error', $context );

		if ( 'openai' === $this->provider_id() ) {
			do_action( 'wpdsac_openai_error', $context );
		}
	}

	/**
	 * Build the generic public provider error.
	 *
	 * @return \WP_Error
	 */
	private function unavailable_error(): \WP_Error {
		return new \WP_Error(
			'wpdsac_provider_unavailable',
			__( 'The AI service is temporarily unavailable.', 'wp-ds-aichatbot' ),
			array( 'status' => 502 )
		);
	}
}
