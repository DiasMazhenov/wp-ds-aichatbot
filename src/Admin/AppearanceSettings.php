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
			'wpdsac_appearance',
			esc_html__( 'Chat appearance', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		$this->add_field( 'accent_color', __( 'Accent color', 'wp-ds-aichatbot' ), 'color' );
		$this->add_field( 'accent_text_color', __( 'Accent text color', 'wp-ds-aichatbot' ), 'color' );
		$this->add_field( 'surface_color', __( 'Panel color', 'wp-ds-aichatbot' ), 'color' );
		$this->add_field( 'text_color', __( 'Text color', 'wp-ds-aichatbot' ), 'color' );
		$this->add_field( 'bot_message_color', __( 'Assistant message color', 'wp-ds-aichatbot' ), 'color' );
		$this->add_field( 'muted_text_color', __( 'Muted text color', 'wp-ds-aichatbot' ), 'color' );
		$this->add_field( 'border_color', __( 'Border color', 'wp-ds-aichatbot' ), 'color' );
		$this->add_field( 'chat_width', __( 'Chat width (px)', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'chat_border_radius', __( 'Corner radius (px)', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'chat_font_size', __( 'Font size (px)', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'global_position', __( 'Global position', 'wp-ds-aichatbot' ), 'position' );
		$this->add_field( 'global_offset_x', __( 'Horizontal offset (px)', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'global_offset_y', __( 'Bottom offset (px)', 'wp-ds-aichatbot' ), 'number' );
	}

	/**
	 * Enqueue the lightweight live preview only on this plugin's settings page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_wpdsac-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wpdsac-admin-chat-preview',
			WPDSAC_URL . 'assets/build/chat.css',
			array(),
			WPDSAC_VERSION
		);

		wp_enqueue_style(
			'wpdsac-admin',
			WPDSAC_URL . 'assets/build/admin.css',
			array( 'wpdsac-admin-chat-preview' ),
			WPDSAC_VERSION
		);

		wp_enqueue_script(
			'wpdsac-admin',
			WPDSAC_URL . 'assets/build/admin.js',
			array(),
			WPDSAC_VERSION,
			true
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
			<div class="wpdsac-admin-preview-stage">
				<section
					class="wpdsac-chat is-expanded"
					style="<?php echo esc_attr( Appearance::inline_style( $options ) ); ?>"
					data-wpdsac-preview
				>
					<button type="button" class="wpdsac-chat__toggle" aria-expanded="true">
						<span class="wpdsac-chat__toggle-title"><?php echo esc_html( (string) $options['title'] ); ?></span>
						<svg class="wpdsac-chat__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.75c.47 4.88 4.37 8.78 9.25 9.25-4.88.47-8.78 4.37-9.25 9.25C11.53 16.37 7.63 12.47 2.75 12 7.63 11.53 11.53 7.63 12 2.75Z"/></svg>
					</button>
					<div class="wpdsac-chat__panel">
						<p class="wpdsac-chat__message wpdsac-chat__message--bot">
							<?php echo nl2br( esc_html( (string) $options['welcome_message'] ) ); ?>
						</p>
						<div class="wpdsac-chat__form">
							<input type="text" placeholder="<?php esc_attr_e( 'Type your message…', 'wp-ds-aichatbot' ); ?>" disabled>
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
	 * @return void
	 */
	private function add_field( string $key, string $label, string $type ): void {
		add_settings_field(
			'wpdsac_' . $key,
			esc_html( $label ),
			array( $this, 'render_field' ),
			'wpdsac-settings',
			'wpdsac_appearance',
			array(
				'key'  => $key,
				'type' => $type,
			)
		);
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
			'muted_text_color'   => '--wpdsac-muted',
			'border_color'       => '--wpdsac-border',
			'chat_width'         => '--wpdsac-width',
			'chat_border_radius' => '--wpdsac-radius',
			'chat_font_size'     => '--wpdsac-font-size',
			'global_offset_x'    => '--wpdsac-offset-x',
			'global_offset_y'    => '--wpdsac-offset-y',
		);

		return $variables[ $key ] ?? '';
	}
}
