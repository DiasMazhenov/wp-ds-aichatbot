<?php
/**
 * Appearance settings fields and live preview.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\Chat\Appearance;

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
		add_settings_section( 'wpdsac_appearance_controls', esc_html__( 'Controls and shapes', 'wp-ds-aichatbot' ), '__return_empty_string', 'wpdsac-settings' );

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

		$this->add_field( 'chat_width', __( 'Chat width (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'messages_height', __( 'Messages area height (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'panel_padding', __( 'Panel padding (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'chat_font_size', __( 'Font size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'font_family', __( 'Font style', 'wp-ds-aichatbot' ), 'font', 'wpdsac_appearance_layout' );
		$this->add_field( 'shadow_opacity', __( 'Panel shadow (%)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'global_position', __( 'Global position', 'wp-ds-aichatbot' ), 'position', 'wpdsac_appearance_layout' );
		$this->add_field( 'global_offset_x', __( 'Horizontal offset (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );
		$this->add_field( 'global_offset_y', __( 'Bottom offset (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_layout' );

		$this->add_field( 'launcher_size', __( 'Collapsed circle size (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'chat_border_radius', __( 'Panel radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'toggle_radius', __( 'Expanded header radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'message_radius', __( 'Message radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'input_radius', __( 'Input and button radius (px)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_appearance_controls' );
		$this->add_field( 'show_toggle_icon', __( 'Icon in expanded header', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_appearance_controls' );
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

		if ( 'checkbox' === $args['type'] ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s data-wpdsac-preview-icon> %3$s</label>',
				esc_attr( $name ),
				checked( ! empty( $options[ $key ] ), true, false ),
				esc_html__( 'Show the decorative icon beside the title when the chat is open.', 'wp-ds-aichatbot' )
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
		$options = Settings::get();
		?>
		<div class="wpdsac-admin-preview-wrap" aria-live="polite">
			<h2><?php esc_html_e( 'Live preview', 'wp-ds-aichatbot' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Appearance changes are previewed before saving and apply to global, shortcode, and Elementor chatbots.', 'wp-ds-aichatbot' ); ?>
			</p>
			<div class="wpdsac-preview-toolbar" role="group" aria-label="<?php esc_attr_e( 'Preview state', 'wp-ds-aichatbot' ); ?>">
				<button type="button" class="button is-active" data-wpdsac-preview-state="expanded" aria-pressed="true"><?php esc_html_e( 'Open', 'wp-ds-aichatbot' ); ?></button>
				<button type="button" class="button" data-wpdsac-preview-state="collapsed" aria-pressed="false"><?php esc_html_e( 'Collapsed circle', 'wp-ds-aichatbot' ); ?></button>
			</div>
			<div class="wpdsac-admin-preview-stage">
				<section
					class="wpdsac-chat is-expanded<?php echo empty( $options['show_toggle_icon'] ) ? ' wpdsac-hide-header-icon' : ''; ?>"
					style="<?php echo esc_attr( Appearance::inline_style( $options ) ); ?>"
					data-wpdsac-preview
				>
					<button type="button" class="wpdsac-chat__toggle" aria-expanded="true">
						<span class="wpdsac-chat__toggle-title"><?php echo esc_html( (string) $options['title'] ); ?></span>
						<svg class="wpdsac-chat__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.75c.47 4.88 4.37 8.78 9.25 9.25-4.88.47-8.78 4.37-9.25 9.25C11.53 16.37 7.63 12.47 2.75 12 7.63 11.53 11.53 7.63 12 2.75Z"/></svg>
					</button>
					<div class="wpdsac-chat__panel" data-wpdsac-preview-panel>
						<p class="wpdsac-chat__message wpdsac-chat__message--bot">
							<?php echo nl2br( esc_html( (string) $options['welcome_message'] ) ); ?>
						</p>
						<div class="wpdsac-chat__form">
							<input type="text" placeholder="<?php echo esc_attr( (string) $options['message_placeholder'] ); ?>" disabled>
							<button type="button" disabled><?php esc_html_e( 'Send', 'wp-ds-aichatbot' ); ?></button>
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
			'accent_color'       => '--wpdsac-accent',
			'accent_text_color'  => '--wpdsac-accent-text',
			'surface_color'      => '--wpdsac-surface',
			'text_color'         => '--wpdsac-text',
			'bot_message_color'  => '--wpdsac-bot-message',
			'bot_text_color'     => '--wpdsac-bot-text',
			'user_message_color' => '--wpdsac-user-message',
			'user_text_color'    => '--wpdsac-user-text',
			'input_color'        => '--wpdsac-input',
			'input_text_color'   => '--wpdsac-input-text',
			'send_button_color'  => '--wpdsac-send',
			'send_text_color'    => '--wpdsac-send-text',
			'muted_text_color'   => '--wpdsac-muted',
			'border_color'       => '--wpdsac-border',
			'chat_width'         => '--wpdsac-width',
			'chat_border_radius' => '--wpdsac-radius',
			'chat_font_size'     => '--wpdsac-font-size',
			'toggle_radius'      => '--wpdsac-toggle-radius',
			'message_radius'     => '--wpdsac-message-radius',
			'input_radius'       => '--wpdsac-input-radius',
			'panel_padding'      => '--wpdsac-panel-padding',
			'messages_height'    => '--wpdsac-messages-height',
			'launcher_size'      => '--wpdsac-launcher-size',
			'shadow_opacity'     => '--wpdsac-shadow-opacity',
			'global_offset_x'    => '--wpdsac-offset-x',
			'global_offset_y'    => '--wpdsac-offset-y',
		);

		return $variables[ $key ] ?? '';
	}
}
