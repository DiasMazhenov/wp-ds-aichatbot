<?php
/**
 * Chat appearance settings and CSS variables.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Normalize visual settings shared by every chatbot surface.
 */
final class Appearance {

	/**
	 * Default visual settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'accent_color'       => '#2563eb',
			'accent_text_color'  => '#ffffff',
			'surface_color'      => '#ffffff',
			'text_color'         => '#172033',
			'bot_message_color'  => '#eff4ff',
			'muted_text_color'   => '#64748b',
			'border_color'       => '#dce2ee',
			'chat_width'         => 380,
			'chat_border_radius' => 18,
			'chat_font_size'     => 16,
			'global_position'    => 'bottom_right',
			'global_offset_x'    => 24,
			'global_offset_y'    => 24,
		);
	}

	/**
	 * Sanitize visual settings against fixed color, range and position rules.
	 *
	 * @param array<string, mixed> $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$output   = array();

		foreach ( self::color_keys() as $key ) {
			$color          = sanitize_hex_color( $input[ $key ] ?? '' );
			$output[ $key ] = is_string( $color ) && '' !== $color ? $color : $defaults[ $key ];
		}

		foreach ( self::number_constraints() as $key => $constraint ) {
			$value          = absint( $input[ $key ] ?? $defaults[ $key ] );
			$output[ $key ] = min( $constraint['max'], max( $constraint['min'], $value ) );
		}

		$position                  = sanitize_key( $input['global_position'] ?? '' );
		$output['global_position'] = in_array( $position, array( 'bottom_left', 'bottom_right' ), true )
			? $position
			: $defaults['global_position'];

		return $output;
	}

	/**
	 * Build sanitized inline CSS custom properties.
	 *
	 * @param array<string, mixed> $settings Normalized plugin settings.
	 * @return string
	 */
	public static function inline_style( array $settings ): string {
		$values = self::sanitize( $settings );

		return sprintf(
			'--wpdsac-accent:%1$s;--wpdsac-accent-text:%2$s;--wpdsac-surface:%3$s;--wpdsac-text:%4$s;--wpdsac-bot-message:%5$s;--wpdsac-muted:%6$s;--wpdsac-border:%7$s;--wpdsac-width:%8$dpx;--wpdsac-radius:%9$dpx;--wpdsac-font-size:%10$dpx;--wpdsac-offset-x:%11$dpx;--wpdsac-offset-y:%12$dpx;',
			$values['accent_color'],
			$values['accent_text_color'],
			$values['surface_color'],
			$values['text_color'],
			$values['bot_message_color'],
			$values['muted_text_color'],
			$values['border_color'],
			$values['chat_width'],
			$values['chat_border_radius'],
			$values['chat_font_size'],
			$values['global_offset_x'],
			$values['global_offset_y']
		);
	}

	/**
	 * Return the safe global-position class.
	 *
	 * @param array<string, mixed> $settings Normalized plugin settings.
	 * @return string
	 */
	public static function position_class( array $settings ): string {
		$values = self::sanitize( $settings );

		return 'bottom_left' === $values['global_position']
			? 'wpdsac-position--bottom-left'
			: 'wpdsac-position--bottom-right';
	}

	/**
	 * Color setting keys.
	 *
	 * @return array<int, string>
	 */
	public static function color_keys(): array {
		return array(
			'accent_color',
			'accent_text_color',
			'surface_color',
			'text_color',
			'bot_message_color',
			'muted_text_color',
			'border_color',
		);
	}

	/**
	 * Numeric visual constraints.
	 *
	 * @return array<string, array{min: int, max: int, unit: string}>
	 */
	public static function number_constraints(): array {
		return array(
			'chat_width'         => array(
				'min'  => 280,
				'max'  => 640,
				'unit' => 'px',
			),
			'chat_border_radius' => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'chat_font_size'     => array(
				'min'  => 12,
				'max'  => 22,
				'unit' => 'px',
			),
			'global_offset_x'    => array(
				'min'  => 0,
				'max'  => 120,
				'unit' => 'px',
			),
			'global_offset_y'    => array(
				'min'  => 0,
				'max'  => 120,
				'unit' => 'px',
			),
		);
	}
}
