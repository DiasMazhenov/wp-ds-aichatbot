<?php
/**
 * Plugin settings registration and page.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION_NAME = 'wpdsac_settings';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'global_enabled' => false,
			'title'          => __( 'AI Assistant', 'wp-ds-aichatbot' ),
			'welcome_message' => __( 'Hello! How can I help you?', 'wp-ds-aichatbot' ),
		);
	}

	/**
	 * Read normalized settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$value = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
	}

	/**
	 * Register settings, section and fields.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			'wpdsac_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'object',
				'default'           => self::defaults(),
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);

		add_settings_section(
			'wpdsac_display',
			esc_html__( 'Chat display', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		$this->add_field( 'global_enabled', __( 'Global chatbot', 'wp-ds-aichatbot' ), 'checkbox' );
		$this->add_field( 'title', __( 'Title', 'wp-ds-aichatbot' ), 'text' );
		$this->add_field( 'welcome_message', __( 'Welcome message', 'wp-ds-aichatbot' ), 'textarea' );
	}

	/**
	 * Sanitize settings against a fixed schema.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'global_enabled' => ! empty( $input['global_enabled'] ),
			'title'          => sanitize_text_field( $input['title'] ?? '' ),
			'welcome_message' => sanitize_textarea_field( $input['welcome_message'] ?? '' ),
		);
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_options_page(
			esc_html__( 'WP DS AI Chatbot', 'wp-ds-aichatbot' ),
			esc_html__( 'DS AI Chatbot', 'wp-ds-aichatbot' ),
			'manage_options',
			'wpdsac-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'wpdsac_settings_group' );
				do_settings_sections( 'wpdsac-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one setting field.
	 *
	 * @param array<string, string> $args Field arguments.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$options = self::get();
		$key     = $args['key'];
		$name    = self::OPTION_NAME . '[' . $key . ']';

		if ( 'checkbox' === $args['type'] ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>',
				esc_attr( $name ),
				checked( ! empty( $options[ $key ] ), true, false ),
				esc_html__( 'Show the chatbot globally in the site footer.', 'wp-ds-aichatbot' )
			);
			return;
		}

		if ( 'textarea' === $args['type'] ) {
			printf(
				'<textarea class="large-text" rows="4" name="%1$s">%2$s</textarea>',
				esc_attr( $name ),
				esc_textarea( (string) $options[ $key ] )
			);
			return;
		}

		printf(
			'<input class="regular-text" type="text" name="%1$s" value="%2$s">',
			esc_attr( $name ),
			esc_attr( (string) $options[ $key ] )
		);
	}

	/**
	 * Add a field with a shared callback.
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
			'wpdsac_display',
			array(
				'key'  => $key,
				'type' => $type,
			)
		);
	}
}

