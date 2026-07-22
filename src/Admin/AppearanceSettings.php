<?php
/**
 * Appearance settings fields and live preview.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\Chat\Appearance;
use DiasMazhenov\WPDsAiChatbot\Chat\GreetingResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Keep visual controls separate from provider and security settings.
 */
final class AppearanceSettings {

	/**
	 * Register the appearance section and fields.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		add_settings_section(
			'wpdsac_appearance_colors',
			esc_html__( 'Colors', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);
		add_settings_section( 'wpdsac_appearance_layout', esc_html__( 'Layout and typography', 'wp-ds-aichatbot' ), '__return_empty_string', 'wpdsac-settings' );
		add_settings_section( 'wpdsac_appearance_launcher', esc_html__( 'Collapsed launcher animation', 'wp-ds-aichatbot' ), '__return_empty_string', 'wpdsac-settings' );
		add_settings_section( 'wpdsac_appearance_messages', esc_html__( 'Message animation', 'wp-ds-aichatbot' ), '__return_empty_string', 'wpdsac-settings' );
		add_settings_section( 'wpdsac_appearance_controls', esc_html__( 'Controls and shapes', 'wp-ds-aichatbot' ), '__return_empty_string', 'wpdsac-settings' );
		add_settings_section( 'wpdsac_appearance_messages_window', esc_html__( 'Message window', 'wp-ds-aichatbot' ), '__return_empty_string', 'wpdsac-settings' );
		add_settings_section( 'wpdsac_appearance_composer', esc_html__( 'Bottom panel', 'wp-ds-aichatbot' ), '__return_empty_string', 'wpdsac-settings' );

		$this->add_field( 'accent_color', __( 'Header and launcher', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'accent_text_color', __( 'Header text and icon', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'surface_color', __( 'Panel', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'text_color', __( 'Main text', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'bot_message_color', __( 'Assistant message', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'bot_text_color', __( 'Assistant message text', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'user_message_color', __( 'Visitor message', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'user_text_color', __( 'Visitor message text', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'input_color', __( 'Message input', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'input_text_color', __( 'Message input text', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'send_button_color', __( 'Send button', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'send_text_color', __( 'Send button text', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'muted_text_color', __( 'Status text', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'border_color', __( 'Borders', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'quick_action_color', __( 'Quick button background', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'quick_action_text', __( 'Quick button text', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );
		$this->add_field( 'quick_action_border', __( 'Quick button border', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_colors' );

		$this->add_field( 'chat_width', __( 'Chat width (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'chat_height', __( 'Chat height (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'panel_padding', __( 'Panel padding (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'chat_font_size', __( 'Base font size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'chat_line_height', __( 'Base line height (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'title_font_size', __( 'Header title size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'title_font_weight', __( 'Header title weight', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'message_font_size', __( 'Message font size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'message_line_height', __( 'Message line height (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'input_font_size', __( 'Input font size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'button_font_size', __( 'Send button font size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'font_family', __( 'Font style', 'wp-ds-aichatbot' ), 'font', 'wpdsac_appearance_layout' );
		$this->add_field( 'shadow_opacity', __( 'Panel shadow (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'global_position', __( 'Global position', 'wp-ds-aichatbot' ), 'position', 'wpdsac_appearance_layout' );
		$this->add_field( 'global_offset_x', __( 'Horizontal offset (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'global_offset_y', __( 'Bottom offset (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );

		$this->add_field( 'launcher_animation', __( 'Animation', 'wp-ds-aichatbot' ), 'launcher_animation', 'wpdsac_appearance_launcher' );
		$this->add_field( 'launcher_gradient_1', __( 'Gradient color 1', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_launcher' );
		$this->add_field( 'launcher_gradient_2', __( 'Gradient color 2', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_launcher' );
		$this->add_field( 'launcher_gradient_3', __( 'Gradient color 3', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_launcher' );
		$this->add_field( 'launcher_anim_speed', __( 'Animation duration (seconds)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_launcher' );
		$this->add_field( 'launcher_anim_intensity', __( 'Animation intensity (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_launcher' );
		$this->add_field( 'launcher_size', __( 'Collapsed circle size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_launcher' );
		$this->add_field( 'message_animation_enabled', __( 'Word-by-word replies', 'wp-ds-aichatbot' ), 'message_animation_checkbox', 'wpdsac_appearance_messages' );
		$this->add_field( 'message_word_delay', __( 'Delay between words (ms)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages' );
		$this->add_field( 'panel_blur', __( 'Panel glass blur (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages' );
		$this->add_field( 'panel_bg_opacity', __( 'Panel background opacity (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages' );
		$this->add_field( 'panel_border_opacity', __( 'Panel border opacity (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages' );
		$this->add_field( 'chat_border_radius', __( 'Panel radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'toggle_radius', __( 'Expanded header radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'message_radius', __( 'Message radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'input_radius', __( 'Input and button radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'quick_action_font_size', __( 'Quick button font size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'quick_action_padding_x', __( 'Quick button horizontal padding (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'quick_action_padding_y', __( 'Quick button vertical padding (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'quick_action_radius', __( 'Quick button radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'quick_action_gap', __( 'Quick button gap (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'show_toggle_icon', __( 'Icon in expanded header', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_appearance_controls' );

		$this->add_field( 'messages_bg_mode', __( 'Background mode', 'wp-ds-aichatbot' ), 'messages_bg_mode', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_bg_color', __( 'Background color', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_bg_opacity', __( 'Background opacity (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_blur', __( 'Glass blur (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_saturation', __( 'Glass saturation (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_radius', __( 'Window radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_border_color', __( 'Border color', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_border_opacity', __( 'Border opacity (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_border_width', __( 'Border thickness (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_glare', __( 'Inner highlight intensity (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_shadow', __( 'Outer shadow intensity (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_padding', __( 'Inner padding (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );
		$this->add_field( 'messages_composer_spacing', __( 'Space between window and bottom panel (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_messages_window' );

		$this->add_field( 'composer_bg_color', __( 'Panel background', 'wp-ds-aichatbot' ), 'color', 'wpdsac_appearance_composer' );
		$this->add_field( 'composer_bg_opacity', __( 'Panel background opacity (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_composer' );
		$this->add_field( 'composer_radius', __( 'Corner radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_composer' );
		$this->add_field( 'composer_padding', __( 'Inner padding (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_composer' );
		$this->add_field( 'composer_gap', __( 'Gap between buttons and input (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_composer' );
		$this->add_field( 'composer_scrollable', __( 'Horizontal scroll for quick buttons', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_appearance_composer' );
		$this->add_field( 'composer_border_top', __( 'Separator line above bottom panel', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_appearance_composer' );
	}

	/**
	 * Enqueue the lightweight live preview only on this plugin's settings page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$is_settings_page = 'toplevel_page_' . Settings::PAGE_SLUG === $hook_suffix;
		$is_plugin_page   = $is_settings_page || false !== strpos( $hook_suffix, 'wpdsac-' );

		if ( ! $is_plugin_page ) {
			return;
		}

		if ( $is_settings_page ) {
			wp_enqueue_media();

			wp_enqueue_style(
				'wpdsac-admin-chat-preview',
				WPDSAC_URL . 'assets/build/chat.css',
				array(),
				WPDSAC_VERSION
			);
		}

		wp_enqueue_style(
			'wpdsac-admin',
			WPDSAC_URL . 'assets/build/admin.css',
			$is_settings_page ? array( 'wpdsac-admin-chat-preview' ) : array(),
			WPDSAC_VERSION
		);

		wp_enqueue_script(
			'wpdsac-admin',
			WPDSAC_URL . 'assets/build/admin.js',
			array(),
			WPDSAC_VERSION,
			true
		);

		wp_localize_script(
			'wpdsac-admin',
			'wpdsacAdmin',
			array(
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'wpdsac_save_settings' ),
				'savingText'          => __( 'Saving settings…', 'wp-ds-aichatbot' ),
				'savedText'           => __( 'Settings saved.', 'wp-ds-aichatbot' ),
				'errorText'           => __( 'Could not save settings. Please try again.', 'wp-ds-aichatbot' ),
				'savedKeyText'        => __( 'API key saved. Leave this field empty to keep it.', 'wp-ds-aichatbot' ),
				'savedKeyMask'        => '••••••••••••',
				'unsavedText'         => __( 'There are unsaved changes.', 'wp-ds-aichatbot' ),
				'configuredYes'       => __( 'Yes', 'wp-ds-aichatbot' ),
				'configuredNo'        => __( 'No', 'wp-ds-aichatbot' ),
				'lightTheme'          => __( 'Light mode', 'wp-ds-aichatbot' ),
				'darkTheme'           => __( 'Dark mode', 'wp-ds-aichatbot' ),
				'chooseAvatar'        => __( 'Select chatbot avatar', 'wp-ds-aichatbot' ),
				'useAvatar'           => __( 'Use this avatar', 'wp-ds-aichatbot' ),
				'providerDiagnostics' => Settings::all_provider_diagnostics(),
			)
		);
	}

	/**
	 * Render one appearance setting.
	 *
	 * @param array<string, string> $args Field arguments.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$options = Settings::get();
		$key     = $args['key'];
		$name    = Settings::OPTION_NAME . '[' . $key . ']';

		if ( 'position' === $args['type'] ) {
			$this->render_position_select( $name, (string) $options[ $key ] );
			return;
		}

		if ( 'font' === $args['type'] ) {
			$this->render_font_select( $name, (string) $options[ $key ] );
			return;
		}

		if ( 'launcher_animation' === $args['type'] ) {
			$this->render_launcher_animation_select( $name, (string) $options[ $key ] );
			return;
		}

		if ( 'messages_bg_mode' === $args['type'] ) {
			$this->render_messages_bg_mode_select( $name, (string) $options[ $key ] );
			return;
		}

		if ( 'checkbox' === $args['type'] ) {
			$descriptions = array(
				'show_toggle_icon'    => __( 'Show the decorative icon beside the title when the chat is open.', 'wp-ds-aichatbot' ),
				'composer_scrollable' => __( 'Allow horizontal scrolling of quick buttons when they do not fit in one row.', 'wp-ds-aichatbot' ),
				'composer_border_top' => __( 'Show a thin separator line between the message area and the bottom input panel.', 'wp-ds-aichatbot' ),
			);
			$desc         = $descriptions[ $key ] ?? '';
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>',
				esc_attr( $name ),
				checked( ! empty( $options[ $key ] ), true, false )
					. ( 'show_toggle_icon' === $key ? ' data-wpdsac-preview-icon' : '' ),
				esc_html( $desc )
			);
			return;
		}

		if ( 'message_animation_checkbox' === $args['type'] ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s data-wpdsac-message-animation> %3$s</label><p class="description">%4$s</p>',
				esc_attr( $name ),
				checked( ! empty( $options[ $key ] ), true, false ),
				esc_html__( 'Show each new AI reply one word at a time.', 'wp-ds-aichatbot' ),
				esc_html__( 'The full response is stored immediately. Reduced-motion mode always shows it without animation.', 'wp-ds-aichatbot' )
			);
			return;
		}

		if ( 'color' === $args['type'] ) {
			printf(
				'<input type="color" name="%1$s" value="%2$s" data-wpdsac-css-var="%3$s"> <code>%2$s</code>',
				esc_attr( $name ),
				esc_attr( (string) $options[ $key ] ),
				esc_attr( $this->css_variable( $key ) )
			);
			return;
		}

		$constraints = Appearance::number_constraints();
		$constraint  = $constraints[ $key ];

		printf(
			'<input class="small-text" type="number" min="%1$d" max="%2$d" step="1" name="%3$s" value="%4$d" data-wpdsac-css-var="%5$s" data-wpdsac-unit="%6$s">',
			absint( $constraint['min'] ),
			absint( $constraint['max'] ),
			esc_attr( $name ),
			(int) $options[ $key ],
			esc_attr( $this->css_variable( $key ) ),
			esc_attr( $constraint['unit'] )
		);
	}

	/**
	 * Render a live appearance preview without an active chat form.
	 *
	 * @return void
	 */
	public function render_preview(): void {
		$options    = Settings::get();
		$appearance = Appearance::sanitize( $options );
		$avatar_url = ! empty( $options['bot_avatar_id'] ) ? wp_get_attachment_image_url( absint( $options['bot_avatar_id'] ), 'wpdsac-avatar' ) : '';
		$avatar_url = $avatar_url ? $avatar_url : WPDSAC_URL . 'wp-chatbot.svg';
		$pos_x      = min( 100, max( 0, absint( $options['avatar_position_x'] ?? 50 ) ) );
		$pos_y      = min( 100, max( 0, absint( $options['avatar_position_y'] ?? 50 ) ) );
		$scale      = min( 200, max( 50, absint( $options['avatar_scale'] ?? 100 ) ) );
		$obj_pos    = sprintf( 'object-position:%d%% %d%%;transform:scale(%s)', $pos_x, $pos_y, round( $scale / 100, 2 ) );
		$sample     = trim( (string) $options['welcome_message'] );

		if ( '' === $sample ) {
			$greetings = array_values( array_filter( array_map( 'trim', explode( "\n", (string) ( $options['greetings_pool'] ?? '' ) ) ) ) );
			$sample    = $greetings[0] ?? __( 'Hello! I can help you choose the right service. What would you like to know?', 'wp-ds-aichatbot' );
		}
		$sample = GreetingResolver::resolve( $sample );

		$fallback_sample = __( 'Hello! I can help you choose the right service. What would you like to know?', 'wp-ds-aichatbot' );
		?>
		<div class="wpdsac-admin-preview-wrap" aria-live="polite">
			<h2><?php esc_html_e( 'Live preview', 'wp-ds-aichatbot' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Appearance changes are previewed before saving and apply to global, shortcode, and Elementor chatbots.', 'wp-ds-aichatbot' ); ?>
			</p>
			<div class="wpdsac-preview-toolbar" role="group" aria-label="<?php esc_attr_e( 'Preview state', 'wp-ds-aichatbot' ); ?>">
				<button type="button" class="button is-active" data-wpdsac-preview-state="expanded" aria-pressed="true"><?php esc_html_e( 'Open', 'wp-ds-aichatbot' ); ?></button>
				<button type="button" class="button" data-wpdsac-preview-state="collapsed" aria-pressed="false"><?php esc_html_e( 'Collapsed circle', 'wp-ds-aichatbot' ); ?></button>
				<button type="button" class="button" data-wpdsac-preview-typing><?php esc_html_e( 'Replay typing', 'wp-ds-aichatbot' ); ?></button>
			</div>
			<div class="wpdsac-admin-preview-stage">
				<section
					class="wpdsac-chat is-expanded<?php echo empty( $options['show_toggle_icon'] ) ? ' wpdsac-hide-header-icon' : ''; ?>"
					style="<?php echo esc_attr( Appearance::inline_style( $appearance ) ); ?>"
					data-wpdsac-preview
					data-wpdsac-launcher-animation="<?php echo esc_attr( (string) $appearance['launcher_animation'] ); ?>"
				>
					<button type="button" class="wpdsac-chat__toggle" aria-expanded="true">
						<span class="wpdsac-chat__toggle-title"><?php echo esc_html( (string) $options['title'] ); ?></span>
						<svg class="wpdsac-chat__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.75c.47 4.88 4.37 8.78 9.25 9.25-4.88.47-8.78 4.37-9.25 9.25C11.53 16.37 7.63 12.47 2.75 12 7.63 11.53 11.53 7.63 12 2.75Z"/></svg>
					</button>
					<div class="wpdsac-chat__panel" data-wpdsac-preview-panel>
						<div class="wpdsac-chat__conversation" data-wpdsac-conversation>
							<div class="wpdsac-chat__messages" aria-live="polite">
								<div class="wpdsac-chat__message-row wpdsac-chat__message-row--bot">
									<?php if ( '' !== $avatar_url && WPDSAC_URL . 'wp-chatbot.svg' !== $avatar_url ) : ?>
										<span class="wpdsac-chat__avatar-frame" aria-hidden="true">
											<img class="wpdsac-chat__avatar" src="<?php echo esc_url( $avatar_url ); ?>" width="32" height="32" alt="" data-wpdsac-admin-avatar style="<?php echo esc_attr( $obj_pos ); ?>">
										</span>
									<?php else : ?>
										<svg class="wpdsac-chat__avatar" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.75c.47 4.88 4.37 8.78 9.25 9.25-4.88.47-8.78 4.37-9.25 9.25C11.53 16.37 7.63 12.47 2.75 12 7.63 11.53 11.53 7.63 12 2.75Z"/></svg>
									<?php endif; ?>
								<div class="wpdsac-chat__message wpdsac-chat__message--bot" data-wpdsac-preview-message data-wpdsac-preview-fallback="<?php echo esc_attr( $fallback_sample ); ?>">
									<?php echo nl2br( esc_html( $sample ) ); ?>
								</div>
								</div>
								<div class="wpdsac-chat__message-row wpdsac-chat__message-row--user">
									<p class="wpdsac-chat__message wpdsac-chat__message--user">
										<?php esc_html_e( 'Please tell me about the available options and pricing.', 'wp-ds-aichatbot' ); ?>
									</p>
								</div>
							</div>
						</div>

						<div class="wpdsac-chat__composer" data-wpdsac-composer>
							<div class="wpdsac-chat__actions" data-wpdsac-actions>
								<div class="wpdsac-chat__quick-actions" aria-label="<?php esc_attr_e( 'Quick actions', 'wp-ds-aichatbot' ); ?>" data-wpdsac-quick-actions>
									<button type="button" class="wpdsac-chat__quick-action"><?php echo esc_html( (string) $options['quick_call_label'] ); ?></button>
									<button type="button" class="wpdsac-chat__quick-action"><?php echo esc_html( (string) $options['quick_lead_label'] ); ?></button>
								</div>
								<div
									class="wpdsac-chat__context-actions"
									data-wpdsac-context-actions
									role="group"
									aria-label="<?php esc_attr_e( 'Reply options', 'wp-ds-aichatbot' ); ?>"
									hidden
								></div>
							</div>

							<div class="wpdsac-chat__form" data-wpdsac-form>
							<label class="screen-reader-text" for="wpdsac-preview-input">
								<?php esc_html_e( 'Message', 'wp-ds-aichatbot' ); ?>
							</label>
							<input
								id="wpdsac-preview-input"
								type="text"
								maxlength="2000"
								placeholder="<?php echo esc_attr( (string) $options['message_placeholder'] ); ?>"
								autocomplete="off"
								disabled
							>
							<button type="submit" disabled aria-label="<?php esc_attr_e( 'Send message', 'wp-ds-aichatbot' ); ?>">
								<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2 .01 7Z"/></svg>
							</button>
							</div>
						</div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Add one appearance field.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Field label.
	 * @param string $type  Field type.
	 * @param string $section Settings section ID.
	 * @return void
	 */
	private function add_field( string $key, string $label, string $type, string $section ): void {
		add_settings_field(
			'wpdsac_' . $key,
			esc_html( $label ),
			array( $this, 'render_field' ),
			'wpdsac-settings',
			$section,
			array(
				'key'  => $key,
				'type' => $type,
			)
		);
	}

	/**
	 * Render safe font presets.
	 *
	 * @param string $name    Input name.
	 * @param string $current Current preset.
	 * @return void
	 */
	private function render_font_select( string $name, string $current ): void {
		$labels = array(
			'system'  => __( 'System', 'wp-ds-aichatbot' ),
			'modern'  => __( 'Modern sans-serif', 'wp-ds-aichatbot' ),
			'rounded' => __( 'Rounded', 'wp-ds-aichatbot' ),
			'mono'    => __( 'Monospace', 'wp-ds-aichatbot' ),
		);

		printf( '<select name="%s" data-wpdsac-font-select>', esc_attr( $name ) );

		foreach ( $labels as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}

		echo '</select>';
	}

	/**
	 * Render accessible launcher animation choices.
	 *
	 * @param string $name    Input name.
	 * @param string $current Current animation ID.
	 * @return void
	 */
	private function render_launcher_animation_select( string $name, string $current ): void {
		$labels = array(
			'none'     => __( 'No animation', 'wp-ds-aichatbot' ),
			'gradient' => __( 'Flowing gradient', 'wp-ds-aichatbot' ),
			'glow'     => __( 'Soft glow', 'wp-ds-aichatbot' ),
			'orbit'    => __( 'Orbiting ring', 'wp-ds-aichatbot' ),
			'float'    => __( 'Gentle float', 'wp-ds-aichatbot' ),
		);

		printf( '<select name="%s" data-wpdsac-launcher-animation>', esc_attr( $name ) );

		foreach ( $labels as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}

		echo '</select>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Animation runs only while the chat is collapsed. Reduced-motion preferences always disable it.', 'wp-ds-aichatbot' )
		);
	}

	/**
	 * Render messages background mode choices.
	 *
	 * @param string $name    Input name.
	 * @param string $current Current mode.
	 * @return void
	 */
	private function render_messages_bg_mode_select( string $name, string $current ): void {
		$labels = array(
			'transparent' => __( 'Transparent', 'wp-ds-aichatbot' ),
			'glass'       => __( 'Glass', 'wp-ds-aichatbot' ),
			'solid'       => __( 'Solid color', 'wp-ds-aichatbot' ),
		);

		printf( '<select name="%s" data-wpdsac-messages-bg-mode>', esc_attr( $name ) );

		foreach ( $labels as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}

		echo '</select>';
	}

	/**
	 * Render global position choices.
	 *
	 * @param string $name    Input name.
	 * @param string $current Current position.
	 * @return void
	 */
	private function render_position_select( string $name, string $current ): void {
		$positions = array(
			'bottom_right' => __( 'Bottom right', 'wp-ds-aichatbot' ),
			'bottom_left'  => __( 'Bottom left', 'wp-ds-aichatbot' ),
		);

		printf( '<select name="%s">', esc_attr( $name ) );

		foreach ( $positions as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Map one appearance setting to its frontend CSS custom property.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function css_variable( string $key ): string {
		$variables = array(
			'accent_color'              => '--wpdsac-accent',
			'accent_text_color'         => '--wpdsac-accent-text',
			'surface_color'             => '--wpdsac-surface',
			'text_color'                => '--wpdsac-text',
			'bot_message_color'         => '--wpdsac-bot-message',
			'bot_text_color'            => '--wpdsac-bot-text',
			'user_message_color'        => '--wpdsac-user-message',
			'user_text_color'           => '--wpdsac-user-text',
			'input_color'               => '--wpdsac-input',
			'input_text_color'          => '--wpdsac-input-text',
			'send_button_color'         => '--wpdsac-send',
			'send_text_color'           => '--wpdsac-send-text',
			'muted_text_color'          => '--wpdsac-muted',
			'border_color'              => '--wpdsac-border',
			'quick_action_color'        => '--wpdsac-quick-bg',
			'quick_action_text'         => '--wpdsac-quick-text',
			'quick_action_border'       => '--wpdsac-quick-border',
			'launcher_gradient_1'       => '--wpdsac-launcher-color-1',
			'launcher_gradient_2'       => '--wpdsac-launcher-color-2',
			'launcher_gradient_3'       => '--wpdsac-launcher-color-3',
			'chat_width'                => '--wpdsac-width',
			'chat_height'               => '--wpdsac-height',
			'chat_border_radius'        => '--wpdsac-radius',
			'chat_font_size'            => '--wpdsac-font-size',
			'chat_line_height'          => '--wpdsac-line-height',
			'title_font_size'           => '--wpdsac-title-font-size',
			'title_font_weight'         => '--wpdsac-title-weight',
			'message_font_size'         => '--wpdsac-message-size',
			'message_line_height'       => '--wpdsac-message-height',
			'input_font_size'           => '--wpdsac-input-font-size',
			'button_font_size'          => '--wpdsac-button-size',
			'toggle_radius'             => '--wpdsac-toggle-radius',
			'message_radius'            => '--wpdsac-message-radius',
			'input_radius'              => '--wpdsac-input-radius',
			'panel_padding'             => '--wpdsac-panel-padding',
			'panel_blur'                => '--wpdsac-panel-blur',
			'messages_padding'          => '--wpdsac-msg-padding',
			'messages_composer_spacing' => '--wpdsac-msg-composer-gap',
			'quick_action_font_size'    => '--wpdsac-quick-font-size',
			'quick_action_padding_x'    => '--wpdsac-quick-padding-x',
			'quick_action_padding_y'    => '--wpdsac-quick-padding-y',
			'quick_action_radius'       => '--wpdsac-quick-radius',
			'quick_action_gap'          => '--wpdsac-quick-gap',
			'launcher_size'             => '--wpdsac-launcher-size',
			'launcher_anim_speed'       => '--wpdsac-launcher-speed',
			'launcher_anim_intensity'   => '--wpdsac-launcher-glow',
			'messages_height'           => '--wpdsac-messages-height',
			'shadow_opacity'            => '--wpdsac-shadow-opacity',
			'global_offset_x'           => '--wpdsac-offset-x',
			'global_offset_y'           => '--wpdsac-offset-y',
		);

		return $variables[ $key ] ?? '';
	}
}
