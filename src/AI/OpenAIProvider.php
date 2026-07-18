<?php
/**
 * OpenAI Responses API provider.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;

defined( 'ABSPATH' ) || exit;

final class OpenAIProvider implements ProviderInterface {

	private const ENDPOINT = 'https://api.openai.com/v1/responses';

	private $credentials;

	/**
	 * @param CredentialResolver $credentials Credential resolver.
	 */
	public function __construct( CredentialResolver $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Request one non-streaming response from OpenAI.
	 *
	 * @param string $message    Sanitized visitor message.
	 * @param string $session_id Verified internal session UUID.
	 * @return string|\WP_Error
	 */
	public function generate( string $message, string $session_id ) {
		$api_key = $this->credentials->get_api_key();

		if ( '' === $api_key ) {
			return new \WP_Error(
				'wpdsac_openai_not_configured',
				__( 'The AI provider is not configured yet.', 'wp-ds-aichatbot' ),
				array( 'status' => 503 )
			);
		}

		$options = Settings::get();
		$body    = array(
			'model'             => (string) $options['openai_model'],
			'instructions'      => (string) $options['openai_instructions'],
			'input'             => $message,
			'max_output_tokens' => (int) $options['openai_max_output_tokens'],
			'store'             => false,
		);

		/**
		 * Filter the OpenAI request body. Never add an API key to this array.
		 *
		 * @param array<string, mixed> $body       Request body.
		 * @param string               $session_id Verified session UUID.
		 */
		$body = apply_filters( 'wpdsac_openai_request_body', $body, $session_id );

		if ( ! is_array( $body ) ) {
			$this->report_error( 0, '', 'invalid_request_body' );

			return $this->unavailable_error();
		}

		$encoded_body = wp_json_encode( $body );

		if ( ! is_string( $encoded_body ) ) {
			$this->report_error( 0, '', 'json_encode_failed' );

			return $this->unavailable_error();
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'        => $encoded_body,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->report_error( 0, '', $response->get_error_code() );

			return $this->unavailable_error();
		}

		$status     = (int) wp_remote_retrieve_response_code( $response );
		$request_id = (string) wp_remote_retrieve_header( $response, 'x-request-id' );
		$decoded    = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$error_code = is_array( $decoded ) && isset( $decoded['error']['code'] )
				? sanitize_key( (string) $decoded['error']['code'] )
				: 'http_error';

			$this->report_error( $status, $request_id, $error_code );

			if ( 429 === $status ) {
				return new \WP_Error(
					'wpdsac_openai_rate_limited',
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

		$output = $this->extract_output_text( $decoded );

		if ( '' === $output ) {
			$this->report_error( $status, $request_id, 'empty_output' );

			return $this->unavailable_error();
		}

		return $output;
	}

	/**
	 * Extract the SDK convenience field or the underlying output content.
	 *
	 * @param array<string, mixed> $response Decoded Responses API payload.
	 * @return string
	 */
	private function extract_output_text( array $response ): string {
		if ( isset( $response['output_text'] ) && is_string( $response['output_text'] ) ) {
			return trim( $response['output_text'] );
		}

		if ( empty( $response['output'] ) || ! is_array( $response['output'] ) ) {
			return '';
		}

		$parts = array();

		foreach ( $response['output'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['content'] ) || ! is_array( $item['content'] ) ) {
				continue;
			}

			foreach ( $item['content'] as $content ) {
				if ( is_array( $content ) && 'output_text' === ( $content['type'] ?? '' ) && isset( $content['text'] ) && is_string( $content['text'] ) ) {
					$parts[] = $content['text'];
				}
			}
		}

		return trim( implode( "\n", $parts ) );
	}

	/**
	 * Emit sanitized diagnostics for optional logging integrations.
	 *
	 * @param int    $status     HTTP status, or zero for transport failures.
	 * @param string $request_id OpenAI request ID.
	 * @param string $error_code Sanitized error code.
	 * @return void
	 */
	private function report_error( int $status, string $request_id, string $error_code ): void {
		do_action(
			'wpdsac_openai_error',
			array(
				'status'     => $status,
				'request_id' => sanitize_text_field( $request_id ),
				'error_code' => sanitize_key( $error_code ),
			)
		);
	}

	/**
	 * Return a generic public error without leaking provider details.
	 *
	 * @return \WP_Error
	 */
	private function unavailable_error(): \WP_Error {
		return new \WP_Error(
			'wpdsac_openai_unavailable',
			__( 'The AI service is temporarily unavailable.', 'wp-ds-aichatbot' ),
			array( 'status' => 502 )
		);
	}
}
