<?php
/**
 * Plugin settings registration and page.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\AI\CredentialResolver;
use DiasMazhenov\WPDsAiChatbot\Chat\Appearance;
use DiasMazhenov\WPDsAiChatbot\Support\PluginInfo;

defined( 'ABSPATH' ) || exit;

/**
 * Register and sanitize plugin settings.
 */
final class Settings {

	public const OPTION_NAME = 'wpdsac_settings';

	/**
	 * Appearance settings module.
	 *
	 * @var AppearanceSettings
	 */
	private $appearance;

	/**
	 * Initialize settings modules.
	 */
	public function __construct() {
		$this->appearance = new AppearanceSettings();
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this->appearance, 'enqueue_assets' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array_merge(
			array(
				'global_enabled'       => false,
				'title'                => __( 'AI Assistant', 'wp-ds-aichatbot' ),
				'welcome_message'      => __( 'Hello! How can I help you?', 'wp-ds-aichatbot' ),
				'rate_limit_requests'  => 10,
				'rate_limit_window'    => 60,
				'daily_request_limit'  => 500,
				'knowledge_enabled'    => false,
				'knowledge_max_chunks' => 4,
				'logging_enabled'      => false,
				'log_retention_days'   => 30,
				'leads_enabled'        => false,
				'lead_prompt'          => __( 'Would you like us to contact you?', 'wp-ds-aichatbot' ),
				'lead_consent_text'    => __( 'I agree that my contact details may be stored and used to respond to my request.', 'wp-ds-aichatbot' ),
				'lead_retention_days'  => 90,
				'ai_provider'          => 'openai',
				'ai_instructions'      => __( 'You are a concise and helpful website support assistant. Reply in the same language as the visitor.', 'wp-ds-aichatbot' ),
				'ai_max_output_tokens' => 1200,
				'openai_model'         => 'gpt-5.6-sol',
				'anthropic_model'      => 'claude-sonnet-4-6',
				'gemini_model'         => 'gemini-3.5-flash',
				'openrouter_model'     => 'openai/gpt-5.6-luna',
				'deepseek_model'       => 'deepseek-v4-flash',
				'deepseek_thinking'    => false,
			),
			Appearance::defaults()
		);
	}

	/**
	 * Read normalized settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$value = get_option( self::OPTION_NAME, array() );
		$value = is_array( $value ) ? $value : array();

		if ( ! isset( $value['ai_instructions'] ) && isset( $value['openai_instructions'] ) ) {
			$value['ai_instructions'] = $value['openai_instructions'];
		}

		if ( ! isset( $value['ai_max_output_tokens'] ) && isset( $value['openai_max_output_tokens'] ) ) {
			$value['ai_max_output_tokens'] = $value['openai_max_output_tokens'];
		}

		return wp_parse_args( $value, self::defaults() );
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

		$resolver = new CredentialResolver();

		foreach ( CredentialResolver::provider_ids() as $provider_id ) {
			add_option( $resolver->option_name( $provider_id ), '', '', false );

			register_setting(
				'wpdsac_settings_group',
				$resolver->option_name( $provider_id ),
				array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => function ( $input ) use ( $provider_id ) {
						return $this->sanitize_api_key( $input, $provider_id );
					},
				)
			);
		}

		add_settings_section(
			'wpdsac_display',
			esc_html__( 'Chat display', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		add_settings_section(
			'wpdsac_ai',
			esc_html__( 'AI provider', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		add_settings_section(
			'wpdsac_knowledge',
			esc_html__( 'Knowledge base', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		add_settings_section(
			'wpdsac_privacy',
			esc_html__( 'Conversation privacy', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		add_settings_section(
			'wpdsac_leads',
			esc_html__( 'Lead collection', 'wp-ds-aichatbot' ),
			'__return_empty_string',
			'wpdsac-settings'
		);

		$this->add_field( 'global_enabled', __( 'Global chatbot', 'wp-ds-aichatbot' ), 'checkbox' );
		$this->add_field( 'title', __( 'Title', 'wp-ds-aichatbot' ), 'text' );
		$this->add_field( 'welcome_message', __( 'Welcome message', 'wp-ds-aichatbot' ), 'textarea' );
		$this->add_field( 'rate_limit_requests', __( 'Requests per window', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'rate_limit_window', __( 'Rate-limit window (seconds)', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'daily_request_limit', __( 'AI requests per 24 hours', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'knowledge_enabled', __( 'Use website knowledge', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_knowledge' );
		$this->add_field( 'knowledge_max_chunks', __( 'Knowledge fragments per answer', 'wp-ds-aichatbot' ), 'number', 'wpdsac_knowledge' );
		$this->add_field( 'logging_enabled', __( 'Conversation logging', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_privacy' );
		$this->add_field( 'log_retention_days', __( 'Log retention (days)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_privacy' );
		$this->add_field( 'leads_enabled', __( 'Contact form in chat', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_leads' );
		$this->add_field( 'lead_prompt', __( 'Contact prompt', 'wp-ds-aichatbot' ), 'text', 'wpdsac_leads' );
		$this->add_field( 'lead_consent_text', __( 'Consent text', 'wp-ds-aichatbot' ), 'textarea', 'wpdsac_leads' );
		$this->add_field( 'lead_retention_days', __( 'Lead retention (days)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_leads' );
		$this->appearance->register_fields();
		$this->add_field( 'ai_provider', __( 'Provider', 'wp-ds-aichatbot' ), 'provider_select', 'wpdsac_ai' );
		$this->add_field( 'ai_instructions', __( 'Assistant instructions', 'wp-ds-aichatbot' ), 'textarea', 'wpdsac_ai' );
		$this->add_field( 'ai_max_output_tokens', __( 'Maximum output tokens', 'wp-ds-aichatbot' ), 'number', 'wpdsac_ai' );
		$this->add_field( 'openai_api_key', __( 'OpenAI API key', 'wp-ds-aichatbot' ), 'password', 'wpdsac_ai', 'openai' );
		$this->add_field( 'openai_model', __( 'OpenAI model', 'wp-ds-aichatbot' ), 'text', 'wpdsac_ai', 'openai' );
		$this->add_field( 'anthropic_api_key', __( 'Anthropic API key', 'wp-ds-aichatbot' ), 'password', 'wpdsac_ai', 'anthropic' );
		$this->add_field( 'anthropic_model', __( 'Anthropic model', 'wp-ds-aichatbot' ), 'text', 'wpdsac_ai', 'anthropic' );
		$this->add_field( 'gemini_api_key', __( 'Gemini API key', 'wp-ds-aichatbot' ), 'password', 'wpdsac_ai', 'gemini' );
		$this->add_field( 'gemini_model', __( 'Gemini model', 'wp-ds-aichatbot' ), 'text', 'wpdsac_ai', 'gemini' );
		$this->add_field( 'openrouter_api_key', __( 'OpenRouter API key', 'wp-ds-aichatbot' ), 'password', 'wpdsac_ai', 'openrouter' );
		$this->add_field( 'openrouter_model', __( 'OpenRouter model', 'wp-ds-aichatbot' ), 'text', 'wpdsac_ai', 'openrouter' );
		$this->add_field( 'deepseek_api_key', __( 'DeepSeek API key', 'wp-ds-aichatbot' ), 'password', 'wpdsac_ai', 'deepseek' );
		$this->add_field( 'deepseek_model', __( 'DeepSeek model', 'wp-ds-aichatbot' ), 'text', 'wpdsac_ai', 'deepseek' );
		$this->add_field( 'deepseek_thinking', __( 'DeepSeek thinking mode', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_ai', 'deepseek' );
	}

	/**
	 * Sanitize settings against a fixed schema.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input        = is_array( $input ) ? $input : array();
		$providers    = array( 'openai', 'anthropic', 'gemini', 'openrouter', 'deepseek', 'wordpress_ai' );
		$provider     = sanitize_key( $input['ai_provider'] ?? 'openai' );
		$provider     = in_array( $provider, $providers, true ) ? $provider : 'openai';
		$defaults     = self::defaults();
		$lead_prompt  = sanitize_text_field( $input['lead_prompt'] ?? '' );
		$lead_prompt  = '' !== $lead_prompt ? $lead_prompt : $defaults['lead_prompt'];
		$lead_consent = sanitize_textarea_field( $input['lead_consent_text'] ?? '' );
		$lead_consent = '' !== $lead_consent ? $lead_consent : $defaults['lead_consent_text'];

		$settings = array(
			'global_enabled'       => ! empty( $input['global_enabled'] ),
			'title'                => sanitize_text_field( $input['title'] ?? '' ),
			'welcome_message'      => sanitize_textarea_field( $input['welcome_message'] ?? '' ),
			'rate_limit_requests'  => min( 100, max( 1, absint( $input['rate_limit_requests'] ?? 10 ) ) ),
			'rate_limit_window'    => min( HOUR_IN_SECONDS, max( 10, absint( $input['rate_limit_window'] ?? 60 ) ) ),
			'daily_request_limit'  => min( 100000, absint( $input['daily_request_limit'] ?? 500 ) ),
			'knowledge_enabled'    => ! empty( $input['knowledge_enabled'] ),
			'knowledge_max_chunks' => min( 8, max( 1, absint( $input['knowledge_max_chunks'] ?? 4 ) ) ),
			'logging_enabled'      => ! empty( $input['logging_enabled'] ),
			'log_retention_days'   => min( 365, max( 1, absint( $input['log_retention_days'] ?? 30 ) ) ),
			'leads_enabled'        => ! empty( $input['leads_enabled'] ),
			'lead_prompt'          => $lead_prompt,
			'lead_consent_text'    => $lead_consent,
			'lead_retention_days'  => min( 730, max( 1, absint( $input['lead_retention_days'] ?? 90 ) ) ),
			'ai_provider'          => $provider,
			'ai_instructions'      => sanitize_textarea_field( $input['ai_instructions'] ?? '' ),
			'ai_max_output_tokens' => min( 8000, max( 100, absint( $input['ai_max_output_tokens'] ?? 1200 ) ) ),
			'openai_model'         => $this->sanitize_model_id( $input['openai_model'] ?? '', 'gpt-5.6-sol' ),
			'anthropic_model'      => $this->sanitize_model_id( $input['anthropic_model'] ?? '', 'claude-sonnet-4-6' ),
			'gemini_model'         => $this->sanitize_model_id( $input['gemini_model'] ?? '', 'gemini-3.5-flash' ),
			'openrouter_model'     => $this->sanitize_model_id( $input['openrouter_model'] ?? '', 'openai/gpt-5.6-luna' ),
			'deepseek_model'       => $this->sanitize_model_id( $input['deepseek_model'] ?? '', 'deepseek-v4-flash' ),
			'deepseek_thinking'    => ! empty( $input['deepseek_thinking'] ),
		);

		return array_merge( $settings, Appearance::sanitize( $input ) );
	}

	/**
	 * Validate a newly submitted API key, preserving the current key when blank.
	 *
	 * @param mixed  $input    Submitted key.
	 * @param string $provider Provider ID.
	 * @return string
	 */
	public function sanitize_api_key( $input, string $provider = 'openai' ): string {
		$resolver = new CredentialResolver();
		$option   = $resolver->option_name( $provider );
		$current  = get_option( $option, '' );
		$value    = is_string( $input ) ? trim( $input ) : '';

		if ( '' === $value ) {
			return is_string( $current ) ? $current : '';
		}

		if ( strlen( $value ) < 20 || strlen( $value ) > 512 || preg_match( '/\s/', $value ) ) {
			add_settings_error(
				$option,
				'wpdsac_invalid_ai_api_key',
				esc_html__( 'The AI provider API key format is invalid.', 'wp-ds-aichatbot' )
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
			esc_html( PluginInfo::versioned_label( __( 'WP DS AI Chatbot', 'wp-ds-aichatbot' ) ) ),
			esc_html( PluginInfo::versioned_label( __( 'DS AI Chatbot', 'wp-ds-aichatbot' ) ) ),
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

		$tabs = array(
			'general'    => __( 'General', 'wp-ds-aichatbot' ),
			'ai'         => __( 'AI providers', 'wp-ds-aichatbot' ),
			'knowledge'  => __( 'Knowledge', 'wp-ds-aichatbot' ),
			'appearance' => __( 'Appearance', 'wp-ds-aichatbot' ),
			'privacy'    => __( 'Privacy', 'wp-ds-aichatbot' ),
			'leads'      => __( 'Leads', 'wp-ds-aichatbot' ),
		);
		?>
		<div class="wrap wpdsac-settings-wrap">
			<div class="wpdsac-settings-header">
				<div>
					<h1><?php esc_html_e( 'WP DS AI Chatbot', 'wp-ds-aichatbot' ); ?></h1>
					<p><?php esc_html_e( 'Configure the chatbot, AI providers, knowledge, and privacy from one place.', 'wp-ds-aichatbot' ); ?></p>
				</div>
				<span class="wpdsac-version"><?php echo esc_html( 'v' . WPDSAC_VERSION ); ?></span>
			</div>
			<?php settings_errors(); ?>
			<nav class="wpdsac-settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Chatbot settings', 'wp-ds-aichatbot' ); ?>">
				<?php
				foreach ( $tabs as $tab_id => $tab_label ) {
					printf(
						'<button type="button" class="wpdsac-settings-tab" id="wpdsac-tab-%1$s" role="tab" aria-controls="wpdsac-panel-%1$s" aria-selected="%2$s" tabindex="%3$d" data-wpdsac-tab="%1$s">%4$s</button>',
						esc_attr( $tab_id ),
						'general' === $tab_id ? 'true' : 'false',
						'general' === $tab_id ? 0 : -1,
						esc_html( $tab_label )
					);
				}
				?>
			</nav>
			<form action="options.php" method="post" data-wpdsac-settings-form>
				<?php settings_fields( 'wpdsac_settings_group' ); ?>
				<?php $this->render_tab_panel( 'general', $tabs['general'], array( 'wpdsac_display' ) ); ?>
				<?php $this->render_tab_panel( 'ai', $tabs['ai'], array( 'wpdsac_ai' ) ); ?>
				<?php $this->render_tab_panel( 'knowledge', $tabs['knowledge'], array( 'wpdsac_knowledge' ) ); ?>
				<?php $this->render_tab_panel( 'appearance', $tabs['appearance'], array( 'wpdsac_appearance_colors', 'wpdsac_appearance_layout', 'wpdsac_appearance_controls' ), true ); ?>
				<?php $this->render_tab_panel( 'privacy', $tabs['privacy'], array( 'wpdsac_privacy' ) ); ?>
				<?php $this->render_tab_panel( 'leads', $tabs['leads'], array( 'wpdsac_leads' ) ); ?>
				<div class="wpdsac-settings-actions">
					<?php submit_button( __( 'Save settings', 'wp-ds-aichatbot' ), 'primary', 'submit', false ); ?>
					<span class="wpdsac-save-note"><?php esc_html_e( 'Unsaved changes apply only after saving.', 'wp-ds-aichatbot' ); ?></span>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one accessible settings tab panel.
	 *
	 * @param string             $tab_id          Tab identifier.
	 * @param string             $title           Panel title.
	 * @param array<int, string> $section_ids     Settings API section identifiers.
	 * @param bool               $include_preview Whether to render the appearance preview.
	 * @return void
	 */
	private function render_tab_panel( string $tab_id, string $title, array $section_ids, bool $include_preview = false ): void {
		$descriptions   = array(
			'general'    => __( 'Control where the chatbot appears and what visitors see first.', 'wp-ds-aichatbot' ),
			'ai'         => __( 'Choose one provider. Only its connection fields are shown.', 'wp-ds-aichatbot' ),
			'knowledge'  => __( 'Use indexed website content as reference material for every provider.', 'wp-ds-aichatbot' ),
			'appearance' => __( 'Adjust the shared design used by global, shortcode, and Elementor chatbots.', 'wp-ds-aichatbot' ),
			'privacy'    => __( 'Choose whether conversations are stored and how long they are retained.', 'wp-ds-aichatbot' ),
			'leads'      => __( 'Collect contact requests only after explicit visitor consent.', 'wp-ds-aichatbot' ),
		);
		$section_titles = array(
			'wpdsac_appearance_colors'   => __( 'Colors', 'wp-ds-aichatbot' ),
			'wpdsac_appearance_layout'   => __( 'Layout and typography', 'wp-ds-aichatbot' ),
			'wpdsac_appearance_controls' => __( 'Controls and shapes', 'wp-ds-aichatbot' ),
		);
		?>
		<section class="wpdsac-settings-panel" id="wpdsac-panel-<?php echo esc_attr( $tab_id ); ?>" role="tabpanel" aria-labelledby="wpdsac-tab-<?php echo esc_attr( $tab_id ); ?>" data-wpdsac-panel="<?php echo esc_attr( $tab_id ); ?>">
			<header class="wpdsac-panel-header">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $descriptions[ $tab_id ] ?? '' ); ?></p>
			</header>
			<?php
			foreach ( $section_ids as $section_id ) {
				if ( count( $section_ids ) > 1 && isset( $section_titles[ $section_id ] ) ) {
					printf( '<h3 class="wpdsac-settings-subheading">%s</h3>', esc_html( $section_titles[ $section_id ] ) );
				}

				do_settings_fields( 'wpdsac-settings', $section_id );
			}

			if ( $include_preview ) {
				$this->appearance->render_preview();
			}
			?>
		</section>
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
			$this->render_api_key_field( $args['provider'] );
			return;
		}

		if ( 'provider_select' === $args['type'] ) {
			$this->render_provider_select( $name, (string) $options[ $key ] );
			return;
		}

		if ( 'checkbox' === $args['type'] ) {
			$descriptions = array(
				'global_enabled'    => __( 'Show the chatbot globally in the site footer.', 'wp-ds-aichatbot' ),
				'knowledge_enabled' => __( 'Add relevant indexed pages, posts, and AI FAQs to AI requests.', 'wp-ds-aichatbot' ),
				'logging_enabled'   => __( 'Store successful conversations for the configured retention period. Disabled by default.', 'wp-ds-aichatbot' ),
				'leads_enabled'     => __( 'Show a name/email form with required consent inside the chat. Disabled by default.', 'wp-ds-aichatbot' ),
				'deepseek_thinking' => __( 'Enable deeper reasoning. This can increase response time and token usage.', 'wp-ds-aichatbot' ),
			);
			$description  = $descriptions[ $key ] ?? '';

			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>',
				esc_attr( $name ),
				checked( ! empty( $options[ $key ] ), true, false ),
				esc_html( $description )
			);
			return;
		}

		if ( 'textarea' === $args['type'] ) {
			$preview_attribute = 'welcome_message' === $key
				? ' data-wpdsac-preview-text=".wpdsac-chat__message--bot"'
				: '';

			printf(
				'<textarea class="large-text" rows="4" name="%1$s"%3$s>%2$s</textarea>',
				esc_attr( $name ),
				esc_textarea( (string) $options[ $key ] ),
				$preview_attribute // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed internal attribute.
			);
			return;
		}

		if ( 'number' === $args['type'] ) {
			$minimum = 'daily_request_limit' === $key ? 0 : 1;

			printf(
				'<input class="small-text" type="number" min="%1$d" step="1" name="%2$s" value="%3$d">',
				absint( $minimum ),
				esc_attr( $name ),
				(int) $options[ $key ]
			);
			return;
		}

		$preview_attribute = 'title' === $key
			? ' data-wpdsac-preview-text=".wpdsac-chat__toggle-title"'
			: '';

		printf(
			'<input class="regular-text" type="text" name="%1$s" value="%2$s"%3$s>',
			esc_attr( $name ),
			esc_attr( (string) $options[ $key ] ),
			$preview_attribute // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed internal attribute.
		);
	}

	/**
	 * Add a field with a shared callback.
	 *
	 * @param string $key      Setting key.
	 * @param string $label    Field label.
	 * @param string $type     Field type.
	 * @param string $section  Settings section ID.
	 * @param string $provider Optional provider ID.
	 * @return void
	 */
	private function add_field( string $key, string $label, string $type, string $section = 'wpdsac_display', string $provider = '' ): void {
		$row_class = '' !== $provider ? 'wpdsac-provider-setting wpdsac-provider-setting--' . sanitize_html_class( $provider ) : '';

		add_settings_field(
			'wpdsac_' . $key,
			esc_html( $label ),
			array( $this, 'render_field' ),
			'wpdsac-settings',
			$section,
			array(
				'key'      => $key,
				'type'     => $type,
				'provider' => $provider,
				'class'    => $row_class,
			)
		);
	}

	/**
	 * Render a write-only API key input and its active source.
	 *
	 * @param string $provider Provider ID.
	 * @return void
	 */
	private function render_api_key_field( string $provider ): void {
		$resolver = new CredentialResolver();
		$source   = $resolver->source( $provider );
		$disabled = in_array( $source, array( 'constant', 'environment' ), true );

		printf(
			'<input class="regular-text" type="password" name="%1$s" value="" autocomplete="new-password" placeholder="%2$s" %3$s>',
			esc_attr( $resolver->option_name( $provider ) ),
			esc_attr( 'missing' === $source ? '' : __( 'Configured — enter a new key to replace it', 'wp-ds-aichatbot' ) ),
			disabled( $disabled, true, false )
		);

		if ( $disabled ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'The key is supplied by wp-config.php or the server environment and cannot be changed here.', 'wp-ds-aichatbot' )
			);
		} else {
			if ( 'option' === $source ) {
				printf(
					'<p class="description"><strong>%s</strong></p>',
					esc_html__( 'API key saved. Leave this field empty to keep it.', 'wp-ds-aichatbot' )
				);
			}

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'The saved key is never displayed. Prefer the provider-specific WPDSAC_*_API_KEY constant or environment variable.', 'wp-ds-aichatbot' )
			);
		}
	}

	/**
	 * Render the supported provider selector.
	 *
	 * @param string $name    Input name.
	 * @param string $current Selected provider ID.
	 * @return void
	 */
	private function render_provider_select( string $name, string $current ): void {
		$providers = array(
			'openai'       => __( 'OpenAI', 'wp-ds-aichatbot' ),
			'anthropic'    => __( 'Anthropic Claude', 'wp-ds-aichatbot' ),
			'gemini'       => __( 'Google Gemini', 'wp-ds-aichatbot' ),
			'openrouter'   => __( 'OpenRouter', 'wp-ds-aichatbot' ),
			'deepseek'     => __( 'DeepSeek', 'wp-ds-aichatbot' ),
			'wordpress_ai' => __( 'WordPress AI Client (WordPress 7.0+)', 'wp-ds-aichatbot' ),
		);

		printf( '<select name="%s" data-wpdsac-provider-select>', esc_attr( $name ) );

		foreach ( $providers as $value => $label ) {
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
	 * Sanitize a provider model identifier without stripping namespace slashes.
	 *
	 * @param mixed  $value    Raw model ID.
	 * @param string $fallback Fallback model ID.
	 * @return string
	 */
	private function sanitize_model_id( $value, string $fallback ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		return '' !== $value && preg_match( '/^[a-zA-Z0-9._:\/-]{1,160}$/', $value ) ? $value : $fallback;
	}
}
