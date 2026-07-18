<?php
/**
 * Plugin settings registration and page.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\AI\CredentialResolver;

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
			'global_enabled'           => false,
			'title'                    => __( 'AI Assistant', 'wp-ds-aichatbot' ),
			'welcome_message'          => __( 'Hello! How can I help you?', 'wp-ds-aichatbot' ),
			'rate_limit_requests'      => 10,
			'rate_limit_window'        => 60,
			'openai_model'             => 'gpt-5.6-sol',
			'openai_instructions'      => __( 'You are a concise and helpful website support assistant. Reply in the same language as the visitor.', 'wp-ds-aichatbot' ),
			'openai_max_output_tokens' => 1200,
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

		register_setting(
			'wpdsac_settings_group',
			CredentialResolver::OPTION_NAME,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
			)
		);

		add_settings_section(
			'wpdsac_display',
			esc_html__( 'Chat display', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		add_settings_section(
			'wpdsac_openai',
			esc_html__( 'OpenAI provider', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		$this->add_field( 'global_enabled', __( 'Global chatbot', 'wp-ds-aichatbot' ), 'checkbox' );
		$this->add_field( 'title', __( 'Title', 'wp-ds-aichatbot' ), 'text' );
		$this->add_field( 'welcome_message', __( 'Welcome message', 'wp-ds-aichatbot' ), 'textarea' );
		$this->add_field( 'rate_limit_requests', __( 'Requests per window', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'rate_limit_window', __( 'Rate-limit window (seconds)', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'openai_api_key', __( 'OpenAI API key', 'wp-ds-aichatbot' ), 'password', 'wpdsac_openai' );
		$this->add_field( 'openai_model', __( 'Model', 'wp-ds-aichatbot' ), 'text', 'wpdsac_openai' );
		$this->add_field( 'openai_instructions', __( 'Assistant instructions', 'wp-ds-aichatbot' ), 'textarea', 'wpdsac_openai' );
		$this->add_field( 'openai_max_output_tokens', __( 'Maximum output tokens', 'wp-ds-aichatbot' ), 'number', 'wpdsac_openai' );
	}

	/**
	 * Sanitize settings against a fixed schema.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$model = sanitize_key( $input['openai_model'] ?? 'gpt-5.6-sol' );
		$model = '' !== $model ? $model : 'gpt-5.6-sol';

		return array(
			'global_enabled'           => ! empty( $input['global_enabled'] ),
			'title'                    => sanitize_text_field( $input['title'] ?? '' ),
			'welcome_message'          => sanitize_textarea_field( $input['welcome_message'] ?? '' ),
			'rate_limit_requests'      => min( 100, max( 1, absint( $input['rate_limit_requests'] ?? 10 ) ) ),
			'rate_limit_window'        => min( HOUR_IN_SECONDS, max( 10, absint( $input['rate_limit_window'] ?? 60 ) ) ),
			'openai_model'             => $model,
			'openai_instructions'      => sanitize_textarea_field( $input['openai_instructions'] ?? '' ),
			'openai_max_output_tokens' => min( 8000, max( 100, absint( $input['openai_max_output_tokens'] ?? 1200 ) ) ),
		);
	}

	/**
	 * Validate a newly submitted API key, preserving the current key when blank.
	 *
	 * @param mixed $input Submitted key.
	 * @return string
	 */
	public function sanitize_api_key( $input ): string {
		$current = get_option( CredentialResolver::OPTION_NAME, '' );
		$value   = is_string( $input ) ? trim( $input ) : '';

		if ( '' === $value ) {
			return is_string( $current ) ? $current : '';
		}

		if ( strlen( $value ) < 20 || strlen( $value ) > 512 || preg_match( '/\s/', $value ) ) {
			add_settings_error(
				CredentialResolver::OPTION_NAME,
				'wpdsac_invalid_openai_api_key',
				esc_html__( 'The OpenAI API key format is invalid.', 'wp-ds-aichatbot' )
			);

			return is_string( $current ) ? $current : '';
		}

		return $value;
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

		if ( 'password' === $args['type'] ) {
			$this->render_api_key_field();
			return;
		}

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

		if ( 'number' === $args['type'] ) {
			printf(
				'<input class="small-text" type="number" min="1" step="1" name="%1$s" value="%2$d">',
				esc_attr( $name ),
				(int) $options[ $key ]
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
	 * @param string $section Settings section ID.
	 * @return void
	 */
	private function add_field( string $key, string $label, string $type, string $section = 'wpdsac_display' ): void {
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
	 * Render a write-only API key input and its active source.
	 *
	 * @return void
	 */
	private function render_api_key_field(): void {
		$resolver = new CredentialResolver();
		$source   = $resolver->source();
		$disabled = in_array( $source, array( 'constant', 'environment' ), true );

		printf(
			'<input class="regular-text" type="password" name="%1$s" value="" autocomplete="new-password" placeholder="%2$s" %3$s>',
			esc_attr( CredentialResolver::OPTION_NAME ),
			esc_attr( 'missing' === $source ? '' : __( 'Configured — enter a new key to replace it', 'wp-ds-aichatbot' ) ),
			disabled( $disabled, true, false )
		);

		if ( $disabled ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'The key is supplied by wp-config.php or the server environment and cannot be changed here.', 'wp-ds-aichatbot' )
			);
		} else {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'The saved key is never displayed. Prefer WPDSAC_OPENAI_API_KEY in wp-config.php or the server environment.', 'wp-ds-aichatbot' )
			);
		}
	}
}
