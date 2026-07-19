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
			'bot_text_color'     => '#172033',
			'user_message_color' => '#2563eb',
			'user_text_color'    => '#ffffff',
			'input_color'        => '#ffffff',
			'input_text_color'   => '#172033',
			'send_button_color'  => '#2563eb',
			'send_text_color'    => '#ffffff',
			'muted_text_color'   => '#64748b',
			'border_color'       => '#dce2ee',
			'chat_width'         => 380,
			'chat_border_radius' => 18,
			'toggle_radius'      => 18,
			'message_radius'     => 14,
			'input_radius'       => 18,
			'chat_font_size'     => 16,
			'panel_padding'      => 18,
			'messages_height'    => 320,
			'launcher_size'      => 60,
			'shadow_opacity'     => 16,
			'font_family'        => 'system',
			'show_toggle_icon'   => true,
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

		$font_family                = sanitize_key( $input['font_family'] ?? '' );
		$output['font_family']      = array_key_exists( $font_family, self::font_families() )
			? $font_family
			: $defaults['font_family'];
		$output['show_toggle_icon'] = ! empty( $input['show_toggle_icon'] );

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

		$properties = array(
			'--wpdsac-accent'          => $values['accent_color'],
			'--wpdsac-accent-text'     => $values['accent_text_color'],
			'--wpdsac-surface'         => $values['surface_color'],
			'--wpdsac-text'            => $values['text_color'],
			'--wpdsac-bot-message'     => $values['bot_message_color'],
			'--wpdsac-bot-text'        => $values['bot_text_color'],
			'--wpdsac-user-message'    => $values['user_message_color'],
			'--wpdsac-user-text'       => $values['user_text_color'],
			'--wpdsac-input'           => $values['input_color'],
			'--wpdsac-input-text'      => $values['input_text_color'],
			'--wpdsac-send'            => $values['send_button_color'],
			'--wpdsac-send-text'       => $values['send_text_color'],
			'--wpdsac-muted'           => $values['muted_text_color'],
			'--wpdsac-border'          => $values['border_color'],
			'--wpdsac-width'           => $values['chat_width'] . 'px',
			'--wpdsac-radius'          => $values['chat_border_radius'] . 'px',
			'--wpdsac-toggle-radius'   => $values['toggle_radius'] . 'px',
			'--wpdsac-message-radius'  => $values['message_radius'] . 'px',
			'--wpdsac-input-radius'    => $values['input_radius'] . 'px',
			'--wpdsac-font-size'       => $values['chat_font_size'] . 'px',
			'--wpdsac-panel-padding'   => $values['panel_padding'] . 'px',
			'--wpdsac-messages-height' => $values['messages_height'] . 'px',
			'--wpdsac-launcher-size'   => $values['launcher_size'] . 'px',
			'--wpdsac-shadow-opacity'  => $values['shadow_opacity'] . '%',
			'--wpdsac-font-family'     => self::font_families()[ $values['font_family'] ],
			'--wpdsac-offset-x'        => $values['global_offset_x'] . 'px',
			'--wpdsac-offset-y'        => $values['global_offset_y'] . 'px',
		);
		$style      = '';

		foreach ( $properties as $property => $value ) {
			$style .= $property . ':' . $value . ';';
		}

		return $style;
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
			'bot_text_color',
			'user_message_color',
			'user_text_color',
			'input_color',
			'input_text_color',
			'send_button_color',
			'send_text_color',
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
			'toggle_radius'      => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'message_radius'     => array(
				'min'  => 0,
				'max'  => 32,
				'unit' => 'px',
			),
			'input_radius'       => array(
				'min'  => 0,
				'max'  => 32,
				'unit' => 'px',
			),
			'panel_padding'      => array(
				'min'  => 8,
				'max'  => 40,
				'unit' => 'px',
			),
			'messages_height'    => array(
				'min'  => 120,
				'max'  => 640,
				'unit' => 'px',
			),
			'launcher_size'      => array(
				'min'  => 44,
				'max'  => 96,
				'unit' => 'px',
			),
			'shadow_opacity'     => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => '%',
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

	/**
	 * Safe font-family presets.
	 *
	 * @return array<string, string>
	 */
	public static function font_families(): array {
		return array(
			'system'  => '-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
			'modern'  => 'Arial,"Helvetica Neue",sans-serif',
			'rounded' => 'ui-rounded,"Arial Rounded MT Bold",sans-serif',
			'mono'    => 'ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace',
		);
	}
}
