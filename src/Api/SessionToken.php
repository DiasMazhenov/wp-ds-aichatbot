<?php
/**
 * Signed stateless public chat sessions.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

defined( 'ABSPATH' ) || exit;

final class SessionToken {

	private const LIFETIME = DAY_IN_SECONDS;

	/**
	 * Issue a signed session token.
	 *
	 * @return array<string, int|string>
	 */
	public function issue(): array {
		$issued_at = time();
		$payload   = array(
			'id'  => wp_generate_uuid4(),
			'iat' => $issued_at,
			'exp' => $issued_at + self::LIFETIME,
		);
		$encoded   = $this->base64url_encode( (string) wp_json_encode( $payload ) );
		$signature = $this->sign( $encoded );

		return array(
			'token'      => $encoded . '.' . $signature,
			'expires_at' => $payload['exp'],
			'expires_in' => self::LIFETIME,
		);
	}

	/**
	 * Validate a token and return its server-issued UUID.
	 *
	 * @param string $token Signed token.
	 * @return string|\WP_Error
	 */
	public function validate( string $token ) {
		$parts = explode( '.', $token, 2 );

		if ( 2 !== count( $parts ) || ! hash_equals( $this->sign( $parts[0] ), $parts[1] ) ) {
			return new \WP_Error(
				'wpdsac_invalid_session',
				__( 'The chat session is invalid.', 'wp-ds-aichatbot' ),
				array( 'status' => 401 )
			);
		}

		$decoded = $this->base64url_decode( $parts[0] );
		$payload = false !== $decoded ? json_decode( $decoded, true ) : null;

		if (
			! is_array( $payload ) ||
			empty( $payload['id'] ) ||
			empty( $payload['iat'] ) ||
			empty( $payload['exp'] ) ||
			! wp_is_uuid( $payload['id'], 4 ) ||
			(int) $payload['iat'] > time() + MINUTE_IN_SECONDS ||
			(int) $payload['exp'] < time()
		) {
			return new \WP_Error(
				'wpdsac_expired_session',
				__( 'The chat session has expired.', 'wp-ds-aichatbot' ),
				array( 'status' => 401 )
			);
		}

		return (string) $payload['id'];
	}

	/**
	 * Sign an encoded payload with a WordPress secret salt.
	 *
	 * @param string $payload Encoded payload.
	 * @return string
	 */
	private function sign( string $payload ): string {
		return $this->base64url_encode( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ), true ) );
	}

	/**
	 * URL-safe base64 encode.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function base64url_encode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * URL-safe base64 decode.
	 *
	 * @param string $value Encoded value.
	 * @return string|false
	 */
	private function base64url_decode( string $value ) {
		$padding = strlen( $value ) % 4;
		if ( $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}
}

