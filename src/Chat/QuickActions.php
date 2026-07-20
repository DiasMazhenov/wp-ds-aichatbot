<?php
/**
 * Custom quick-action normalization.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Keep administrator-defined actions bounded and safe for frontend rendering.
 */
final class QuickActions {

	private const MAX_ACTIONS = 8;

	/**
	 * Sanitize a custom action repeater.
	 *
	 * @param mixed $value Raw submitted rows.
	 * @return array<int, array{id: string, label: string, type: string, value: string}>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$actions = array();

		foreach ( array_slice( $value, 0, self::MAX_ACTIONS ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = self::limit( sanitize_text_field( (string) ( $row['label'] ?? '' ) ), 60 );
			$type  = sanitize_key( (string) ( $row['type'] ?? 'message' ) );
			$type  = in_array( $type, array( 'message', 'url' ), true ) ? $type : 'message';
			$raw   = (string) ( $row['value'] ?? '' );
			$value = 'url' === $type
				? esc_url_raw( $raw, array( 'http', 'https' ) )
				: self::limit( sanitize_text_field( $raw ), 500 );

			if ( '' === $label || '' === $value ) {
				continue;
			}

			$actions[] = array(
				'id'    => 'custom-' . substr( md5( $type . '|' . $label . '|' . $value ), 0, 12 ),
				'label' => $label,
				'type'  => $type,
				'value' => $value,
			);
		}

		return $actions;
	}

	/**
	 * Limit a Unicode string when mbstring is available.
	 *
	 * @param string $value  Input value.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private static function limit( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
