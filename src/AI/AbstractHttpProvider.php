<?php
/**
 * Shared secure HTTP workflow for direct AI providers.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

abstract class AbstractHttpProvider implements ProviderInterface {

	protected $credentials;

	/**
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

		$options = Settings::get();
		$body    = $this->request_body( $message, $session_id, $options );

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

	/** @return string */
	abstract protected function provider_id(): string;

	/**
	 * @param array<string, mixed> $options Plugin settings.
	 * @return string
	 */
	abstract protected function endpoint( array $options ): string;

	/**
	 * @param string               $message    Visitor message.
	 * @param string               $session_id Session UUID.
	 * @param array<string, mixed> $options    Plugin settings.
	 * @return array<string, mixed>
	 */
	abstract protected function request_body( string $message, string $session_id, array $options ): array;

	/**
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

	/** @return \WP_Error */
	private function unavailable_error(): \WP_Error {
		return new \WP_Error(
			'wpdsac_provider_unavailable',
			__( 'The AI service is temporarily unavailable.', 'wp-ds-aichatbot' ),
			array( 'status' => 502 )
		);
	}
}
