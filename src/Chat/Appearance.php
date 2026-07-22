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
			'accent_color'              => '#2563eb',
			'accent_text_color'         => '#ffffff',
			'surface_color'             => '#ffffff',
			'text_color'                => '#172033',
			'bot_message_color'         => '#eff4ff',
			'bot_text_color'            => '#172033',
			'user_message_color'        => '#2563eb',
			'user_text_color'           => '#ffffff',
			'input_color'               => '#ffffff',
			'input_text_color'          => '#172033',
			'send_button_color'         => '#2563eb',
			'send_text_color'           => '#ffffff',
			'muted_text_color'          => '#64748b',
			'border_color'              => '#dce2ee',
			'quick_action_color'        => '#ffffff',
			'quick_action_text'         => '#2563eb',
			'quick_action_border'       => '#b8c8ea',
			'launcher_gradient_1'       => '#2563eb',
			'launcher_gradient_2'       => '#7c3aed',
			'launcher_gradient_3'       => '#06b6d4',
			'chat_width'                => 380,
			'chat_height'               => 500,
			'chat_border_radius'        => 18,
			'toggle_radius'             => 18,
			'message_radius'            => 14,
			'input_radius'              => 18,
			'chat_font_size'            => 16,
			'chat_line_height'          => 150,
			'title_font_size'           => 16,
			'title_font_weight'         => 700,
			'message_font_size'         => 16,
			'message_line_height'       => 150,
			'input_font_size'           => 16,
			'button_font_size'          => 16,
			'panel_padding'             => 18,
			'messages_height'           => 320,
			'quick_action_font_size'    => 13,
			'quick_action_padding_x'    => 10,
			'quick_action_padding_y'    => 6,
			'quick_action_radius'       => 16,
			'quick_action_gap'          => 6,
			'launcher_size'             => 60,
			'launcher_animation'        => 'gradient',
			'launcher_anim_speed'       => 6,
			'launcher_anim_intensity'   => 40,
			'message_animation_enabled' => true,
			'message_word_delay'        => 70,
			'shadow_opacity'            => 16,
			'font_family'               => 'system',
			'show_toggle_icon'          => true,
			'global_position'           => 'bottom_right',
			'global_offset_x'           => 24,
			'global_offset_y'           => 24,
			'messages_bg_mode'          => 'solid',
			'messages_blur'             => 14,
			'messages_saturation'       => 125,
			'messages_radius'           => 10,
			'messages_border_color'     => '#ffffff',
			'messages_border_opacity'   => 45,
			'messages_border_width'     => 1,
			'messages_glare'            => 40,
			'messages_shadow'           => 12,
			'messages_padding'          => 20,
			'messages_composer_spacing' => 12,
			'composer_bg_color'         => '#ffffff',
			'composer_bg_opacity'       => 0,
			'composer_border_top'       => true,
			'panel_blur'                => 2,
			'panel_bg_opacity'          => 50,
			'panel_border_opacity'      => 20,
			'messages_bg_color'         => '#111827',
			'messages_bg_opacity'       => 63,
			'composer_radius'           => 20,
			'composer_padding'          => 0,
			'composer_gap'              => 10,
			'composer_scrollable'       => false,
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

		$font_family                         = sanitize_key( $input['font_family'] ?? '' );
		$output['font_family']               = array_key_exists( $font_family, self::font_families() )
			? $font_family
			: $defaults['font_family'];
		$output['show_toggle_icon']          = ! empty( $input['show_toggle_icon'] );
		$output['message_animation_enabled'] = ! empty( $input['message_animation_enabled'] );
		$output['composer_scrollable']       = ! empty( $input['composer_scrollable'] );
		$output['composer_border_top']       = ! isset( $input['composer_border_top'] ) || ! empty( $input['composer_border_top'] );

		$bg_mode                    = sanitize_key( $input['messages_bg_mode'] ?? '' );
		$output['messages_bg_mode'] = in_array( $bg_mode, array( 'solid', 'glass', 'transparent' ), true )
			? $bg_mode
			: $defaults['messages_bg_mode'];

		$launcher_animation           = sanitize_key( $input['launcher_animation'] ?? '' );
		$output['launcher_animation'] = in_array( $launcher_animation, self::launcher_animations(), true )
			? $launcher_animation
			: $defaults['launcher_animation'];

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
			'--wpdsac-accent'              => $values['accent_color'],
			'--wpdsac-accent-text'         => $values['accent_text_color'],
			'--wpdsac-surface'             => $values['surface_color'],
			'--wpdsac-text'                => $values['text_color'],
			'--wpdsac-bot-message'         => $values['bot_message_color'],
			'--wpdsac-bot-text'            => $values['bot_text_color'],
			'--wpdsac-user-message'        => $values['user_message_color'],
			'--wpdsac-user-text'           => $values['user_text_color'],
			'--wpdsac-input'               => $values['input_color'],
			'--wpdsac-input-text'          => $values['input_text_color'],
			'--wpdsac-send'                => $values['send_button_color'],
			'--wpdsac-send-text'           => $values['send_text_color'],
			'--wpdsac-muted'               => $values['muted_text_color'],
			'--wpdsac-border'              => $values['border_color'],
			'--wpdsac-quick-bg'            => $values['quick_action_color'],
			'--wpdsac-quick-text'          => $values['quick_action_text'],
			'--wpdsac-quick-border'        => $values['quick_action_border'],
			'--wpdsac-launcher-color-1'    => $values['launcher_gradient_1'],
			'--wpdsac-launcher-color-2'    => $values['launcher_gradient_2'],
			'--wpdsac-launcher-color-3'    => $values['launcher_gradient_3'],
			'--wpdsac-width'               => $values['chat_width'] . 'px',
			'--wpdsac-height'              => $values['chat_height'] . 'px',
			'--wpdsac-radius'              => $values['chat_border_radius'] . 'px',
			'--wpdsac-toggle-radius'       => $values['toggle_radius'] . 'px',
			'--wpdsac-message-radius'      => $values['message_radius'] . 'px',
			'--wpdsac-input-radius'        => $values['input_radius'] . 'px',
			'--wpdsac-font-size'           => $values['chat_font_size'] . 'px',
			'--wpdsac-line-height'         => $values['chat_line_height'] . '%',
			'--wpdsac-title-font-size'     => $values['title_font_size'] . 'px',
			'--wpdsac-title-weight'        => (string) $values['title_font_weight'],
			'--wpdsac-message-size'        => $values['message_font_size'] . 'px',
			'--wpdsac-message-height'      => $values['message_line_height'] . '%',
			'--wpdsac-input-font-size'     => $values['input_font_size'] . 'px',
			'--wpdsac-button-size'         => $values['button_font_size'] . 'px',
			'--wpdsac-panel-padding'       => $values['panel_padding'] . 'px',
			'--wpdsac-messages-height'     => $values['messages_height'] . 'px',
			'--wpdsac-quick-font-size'     => $values['quick_action_font_size'] . 'px',
			'--wpdsac-quick-padding-x'     => $values['quick_action_padding_x'] . 'px',
			'--wpdsac-quick-padding-y'     => $values['quick_action_padding_y'] . 'px',
			'--wpdsac-quick-radius'        => $values['quick_action_radius'] . 'px',
			'--wpdsac-quick-gap'           => $values['quick_action_gap'] . 'px',
			'--wpdsac-launcher-size'       => $values['launcher_size'] . 'px',
			'--wpdsac-launcher-speed'      => $values['launcher_anim_speed'] . 's',
			'--wpdsac-launcher-glow'       => $values['launcher_anim_intensity'] . '%',
			'--wpdsac-launcher-scale'      => (string) round( 1 + ( $values['launcher_anim_intensity'] / 1000 ), 3 ),
			'--wpdsac-launcher-float'      => round( 2 + ( $values['launcher_anim_intensity'] / 20 ), 2 ) . 'px',
			'--wpdsac-shadow-opacity'      => $values['shadow_opacity'] . '%',
			'--wpdsac-font-family'         => self::font_families()[ $values['font_family'] ],
			'--wpdsac-offset-x'            => $values['global_offset_x'] . 'px',
			'--wpdsac-offset-y'            => $values['global_offset_y'] . 'px',
			'--wpdsac-msg-bg'              => self::messages_bg_value( $values ),
			'--wpdsac-msg-blur'            => $values['messages_blur'] . 'px',
			'--wpdsac-msg-saturation'      => $values['messages_saturation'] . '%',
			'--wpdsac-msg-radius'          => $values['messages_radius'] . 'px',
			'--wpdsac-msg-border-color'    => self::rgba(
				$values['messages_border_color'],
				min( 100, max( 0, (int) $values['messages_border_opacity'] ) ) / 100
			),
			'--wpdsac-msg-border-width'    => $values['messages_border_width'] . 'px',
			'--wpdsac-msg-glare'           => self::glare( (int) $values['messages_glare'] ),
			'--wpdsac-msg-shadow'          => self::shadow( (int) $values['messages_shadow'] ),
			'--wpdsac-msg-padding'         => $values['messages_padding'] . 'px',
			'--wpdsac-msg-composer-gap'    => $values['messages_composer_spacing'] . 'px',
			'--wpdsac-panel-bg'            => self::rgba(
				$values['surface_color'],
				min( 100, max( 0, (int) $values['panel_bg_opacity'] ) ) / 100
			),
			'--wpdsac-panel-blur'          => $values['panel_blur'] . 'px',
			'--wpdsac-panel-border-color'  => self::rgba(
				'#ffffff',
				min( 100, max( 0, (int) $values['panel_border_opacity'] ) ) / 100
			),
			'--wpdsac-composer-bg'         => self::rgba(
				$values['composer_bg_color'],
				min( 100, max( 0, (int) $values['composer_bg_opacity'] ) ) / 100
			),
			'--wpdsac-composer-blur'       => '',
			'--wpdsac-composer-radius'     => $values['composer_radius'] . 'px',
			'--wpdsac-composer-padding'    => $values['composer_padding'] . 'px',
			'--wpdsac-composer-gap'        => $values['composer_gap'] . 'px',
			'--wpdsac-composer-border-top' => $values['composer_border_top']
				? '1px solid rgb(0 0 0 / 5%)'
				: 'none',
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
			'quick_action_color',
			'quick_action_text',
			'quick_action_border',
			'launcher_gradient_1',
			'launcher_gradient_2',
			'launcher_gradient_3',
			'composer_bg_color',
			'messages_bg_color',
			'messages_border_color',
		);
	}

	/**
	 * Numeric visual constraints.
	 *
	 * @return array<string, array{min: int, max: int, unit: string}>
	 */
	public static function number_constraints(): array {
		return array(
			'chat_width'                => array(
				'min'  => 280,
				'max'  => 640,
				'unit' => 'px',
			),
			'chat_height'               => array(
				'min'  => 360,
				'max'  => 760,
				'unit' => 'px',
			),
			'chat_border_radius'        => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'chat_font_size'            => array(
				'min'  => 12,
				'max'  => 22,
				'unit' => 'px',
			),
			'chat_line_height'          => array(
				'min'  => 120,
				'max'  => 200,
				'unit' => '%',
			),
			'title_font_size'           => array(
				'min'  => 12,
				'max'  => 28,
				'unit' => 'px',
			),
			'title_font_weight'         => array(
				'min'  => 400,
				'max'  => 800,
				'unit' => '',
			),
			'message_font_size'         => array(
				'min'  => 12,
				'max'  => 22,
				'unit' => 'px',
			),
			'message_line_height'       => array(
				'min'  => 120,
				'max'  => 200,
				'unit' => '%',
			),
			'input_font_size'           => array(
				'min'  => 12,
				'max'  => 22,
				'unit' => 'px',
			),
			'button_font_size'          => array(
				'min'  => 12,
				'max'  => 22,
				'unit' => 'px',
			),
			'toggle_radius'             => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'message_radius'            => array(
				'min'  => 0,
				'max'  => 32,
				'unit' => 'px',
			),
			'input_radius'              => array(
				'min'  => 0,
				'max'  => 32,
				'unit' => 'px',
			),
			'panel_padding'             => array(
				'min'  => 8,
				'max'  => 40,
				'unit' => 'px',
			),
			'messages_height'           => array(
				'min'  => 120,
				'max'  => 640,
				'unit' => 'px',
			),
			'quick_action_font_size'    => array(
				'min'  => 10,
				'max'  => 18,
				'unit' => 'px',
			),
			'quick_action_padding_x'    => array(
				'min'  => 4,
				'max'  => 24,
				'unit' => 'px',
			),
			'quick_action_padding_y'    => array(
				'min'  => 2,
				'max'  => 16,
				'unit' => 'px',
			),
			'quick_action_radius'       => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'quick_action_gap'          => array(
				'min'  => 2,
				'max'  => 20,
				'unit' => 'px',
			),
			'launcher_size'             => array(
				'min'  => 44,
				'max'  => 96,
				'unit' => 'px',
			),
			'launcher_anim_speed'       => array(
				'min'  => 2,
				'max'  => 20,
				'unit' => 's',
			),
			'launcher_anim_intensity'   => array(
				'min'  => 0,
				'max'  => 100,
				'unit' => '%',
			),
			'message_word_delay'        => array(
				'min'  => 20,
				'max'  => 250,
				'unit' => 'ms',
			),
			'shadow_opacity'            => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => '%',
			),
			'global_offset_x'           => array(
				'min'  => 0,
				'max'  => 120,
				'unit' => 'px',
			),
			'global_offset_y'           => array(
				'min'  => 0,
				'max'  => 120,
				'unit' => 'px',
			),
			'messages_blur'             => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'messages_saturation'       => array(
				'min'  => 50,
				'max'  => 200,
				'unit' => '%',
			),
			'messages_radius'           => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'messages_bg_opacity'       => array(
				'min'  => 0,
				'max'  => 100,
				'unit' => '%',
			),
			'messages_border_opacity'   => array(
				'min'  => 0,
				'max'  => 100,
				'unit' => '%',
			),
			'messages_border_width'     => array(
				'min'  => 0,
				'max'  => 4,
				'unit' => 'px',
			),
			'messages_glare'            => array(
				'min'  => 0,
				'max'  => 100,
				'unit' => '%',
			),
			'messages_shadow'           => array(
				'min'  => 0,
				'max'  => 100,
				'unit' => '%',
			),
			'messages_padding'          => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'messages_composer_spacing' => array(
				'min'  => 0,
				'max'  => 30,
				'unit' => 'px',
			),
			'composer_bg_opacity'       => array(
				'min'  => 0,
				'max'  => 100,
				'unit' => '%',
			),
			'composer_radius'           => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'composer_padding'          => array(
				'min'  => 4,
				'max'  => 30,
				'unit' => 'px',
			),
			'composer_gap'              => array(
				'min'  => 0,
				'max'  => 24,
				'unit' => 'px',
			),
			'panel_blur'                => array(
				'min'  => 0,
				'max'  => 40,
				'unit' => 'px',
			),
			'panel_bg_opacity'          => array(
				'min'  => 0,
				'max'  => 100,
				'unit' => '%',
			),
			'panel_border_opacity'      => array(
				'min'  => 0,
				'max'  => 100,
				'unit' => '%',
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

	/**
	 * Supported collapsed-launcher animation IDs.
	 *
	 * @return array<int, string>
	 */
	public static function launcher_animations(): array {
		return array( 'none', 'gradient', 'glow', 'orbit', 'float' );
	}

	/**
	 * Convert hex color to rgba with alpha.
	 *
	 * @param string $hex   Hex color.
	 * @param float  $alpha Opacity 0-1.
	 * @return string
	 */
	private static function rgba( string $hex, float $alpha ): string {
		$alpha = min( 1, max( 0, $alpha ) );
		if ( '' === $hex ) {
			return 'transparent';
		}
		$hex = ltrim( $hex, '#' );
		$r   = hexdec( substr( $hex, 0, 2 ) );
		$g   = hexdec( substr( $hex, 2, 2 ) );
		$b   = hexdec( substr( $hex, 4, 2 ) );

		return "rgb({$r} {$g} {$b} / {$alpha})";
	}

	/**
	 * Build box-shadow from intensity percentage.
	 *
	 * @param int $intensity 0-100.
	 * @return string
	 */
	private static function shadow( int $intensity ): string {
		$alpha = min( 0.4, max( 0, $intensity / 100 * 0.4 ) );

		return "0 12px 30px rgb(15 23 42 / {$alpha})";
	}

	/**
	 * Build the background value for the messages window.
	 *
	 * @param array<string, mixed> $values Sanitized values.
	 * @return string
	 */
	private static function messages_bg_value( array $values ): string {
		if ( 'transparent' === $values['messages_bg_mode'] ) {
			return 'transparent';
		}

		return self::rgba(
			$values['messages_bg_color'],
			min( 100, max( 0, (int) $values['messages_bg_opacity'] ) ) / 100
		);
	}

	/**
	 * Build inset highlight from intensity percentage.
	 *
	 * @param int $intensity 0-100.
	 * @return string
	 */
	private static function glare( int $intensity ): string {
		$alpha = min( 0.4, max( 0, $intensity / 100 * 0.4 ) );

		return "inset 0 1px 0 rgb(255 255 255 / {$alpha})";
	}
}
