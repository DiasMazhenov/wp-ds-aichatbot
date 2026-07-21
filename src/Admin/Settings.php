<?php
/**
 * Plugin settings registration and page.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\AI\CredentialResolver;
use DiasMazhenov\WPDsAiChatbot\Chat\Appearance;
use DiasMazhenov\WPDsAiChatbot\Chat\QuickActions;
use DiasMazhenov\WPDsAiChatbot\Support\PluginInfo;

defined( 'ABSPATH' ) || exit;

/**
 * Register and sanitize plugin settings.
 */
final class Settings {

	public const OPTION_NAME = 'wpdsac_settings';
	public const PAGE_SLUG   = 'wpdsac-settings';

	/**
	 * Sanitized variables available only during the current AI request.
	 *
	 * @var array<string, string>
	 */
	private static $runtime_variables = array();

	/**
	 * Communication style presets.
	 *
	 * @var array<string, array{label: string, instructions: string}>
	 */
	public static $communication_styles = array(
		'concierge'   => array(
			'label'        => 'Заботливый консьерж',
			'instructions' => 'Ты заботливый консьерж. Фокусируйся на пользе, решении проблем и комфорте клиента. Предлагай помощь проактивно, уточняй детали, чтобы дать максимально точный ответ. Забота о клиенте — твой главный приоритет.',
		),
		'guide'       => array(
			'label'        => 'Профессиональный гид',
			'instructions' => 'Ты профессиональный гид. Общайся языком цифр, фактов и четких преимуществ. Приводи конкретные данные, сравнивай варианты, помогай принимать обоснованные решения.',
		),
		'partner'     => array(
			'label'        => 'Бизнес-партнер',
			'instructions' => 'Ты бизнес-партнер. Строгий тон на равных, экономия времени собеседника, готовые решения без лишних вопросов. Будь лаконичен, профессионален и ориентирован на результат.',
		),
		'insider'     => array(
			'label'        => 'Стильный инсайдер',
			'instructions' => 'Ты стильный инсайдер. Легкий тон, акцент на трендах и «секретных» предложениях. Делись инсайдами, создавай ощущение эксклюзивности и оперативности.',
		),
		'buddy'       => array(
			'label'        => 'Свой парень',
			'instructions' => 'Ты свой парень. Дружеская атмосфера, разговорные обороты и простые рекомендации. Общайся как друг — тепло, без формальностей, но по делу.',
		),
		'mentor'      => array(
			'label'        => 'Эмпатичный ментор',
			'instructions' => 'Ты эмпатичный ментор. Мягко поддерживай, снимай страхи и сомнения перед покупкой. Объясняй просто, терпеливо, помогай пережить сомнения и принять решение.',
		),
		'promoter'    => array(
			'label'        => 'Зажигательный промоутер',
			'instructions' => 'Ты зажигательный промоутер. Создавай ажиотаж, динамику и акцент на упущенной выгоде. Используй энергичные формулировки, подчеркивай срочность и выгоду.',
		),
		'coach'       => array(
			'label'        => 'Спортивный коуч',
			'instructions' => 'Ты спортивный коуч. Энергичный призыв к действию, мотивация на быстрый результат. Будь динамичен, вдохновляй на行动у, ставь цели и помогай их достичь.',
		),
		'scholar'     => array(
			'label'        => 'Ироничный эрудит',
			'instructions' => 'Ты ироничный эрудит. Используй юмор, мемы и культурные отсылки. Будь остроумен, но полезен — за каждым шуткой стоят точные рекомендации.',
		),
		'storyteller' => array(
			'label'        => 'Бот-сторителлер',
			'instructions' => 'Ты бот-сторителлер. Продавай через яркие истории, отзывы и эмоции. Рассказывай кейсы, приводи примеры реальных клиентов, создавай образ через повествование.',
		),
	);

	/**
	 * Request-only protected instruction suffix.
	 *
	 * @var string
	 */
	private static $runtime_instruction_suffix = '';

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
		add_action( 'admin_menu', array( $this, 'ensure_settings_first' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_menu_icon_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this->appearance, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpdsac_save_settings', array( $this, 'save_ajax' ) );
	}

	/**
	 * Constrain the custom menu icon on every administration screen.
	 *
	 * @return void
	 */
	public function enqueue_menu_icon_style(): void {
		wp_add_inline_style(
			'common',
			'#toplevel_page_wpdsac-settings .wp-menu-image img{width:20px!important;height:20px!important;max-width:20px!important;object-fit:contain}'
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array_merge(
			array(
				'global_enabled'             => false,
				'title'                      => __( 'AI Assistant', 'wp-ds-aichatbot' ),
				'welcome_message'            => __( 'Hello! How can I help you?', 'wp-ds-aichatbot' ),
				'message_placeholder'        => __( 'Type your message…', 'wp-ds-aichatbot' ),
				'reply_sound'                => 'soft',
				'intro_trigger'              => 'delay',
				'intro_delay_seconds'        => 10,
				'bot_avatar_id'              => 0,
				'rate_limit_requests'        => 10,
				'rate_limit_window'          => 60,
				'daily_request_limit'        => 500,
				'knowledge_enabled'          => false,
				'knowledge_max_chunks'       => 4,
				'knowledge_semantic_enabled' => false,
				'embeddings_model'           => 'text-embedding-3-small',
				'logging_enabled'            => false,
				'log_retention_days'         => 30,
				'quick_call_label'           => __( 'Call', 'wp-ds-aichatbot' ),
				'quick_call_url'             => '',
				'quick_lead_label'           => __( 'Leave a request', 'wp-ds-aichatbot' ),
				'quick_custom_actions'       => array(),
				'lead_prompt'                => __( 'Please leave your name and phone number, and we will contact you.', 'wp-ds-aichatbot' ),
				'lead_submit_label'          => __( 'Send request', 'wp-ds-aichatbot' ),
				'lead_notification_email'    => get_option( 'admin_email', '' ),
				'lead_consent_text'          => __( 'I agree that my contact details may be stored and used to respond to my request.', 'wp-ds-aichatbot' ),
				'lead_retention_days'        => 90,
				'ai_provider'                => 'openai',
				'communication_style'        => 'concierge',
				'ai_instructions'            => __( 'You are a proactive sales assistant for this website. Your goal is to convert visitors into customers. First, understand the visitor’s needs by asking 1–2 qualifying questions before presenting solutions. Highlight specific products, services, or benefits that match their situation. Use confident, benefit-focused language. If the visitor goes silent for more than a minute, gently re-engage with a follow-up question. Always end your response with a question or a call to action. Reply in the same language as the visitor.', 'wp-ds-aichatbot' ),
				'ai_max_output_tokens'       => 1200,
				'prompt_guard_enabled'       => true,
				'topic_scope'                => '',
				'guard_refusal_message'      => __( 'I can only help with questions related to this website.', 'wp-ds-aichatbot' ),
				'openai_model'               => 'gpt-5.6-sol',
				'anthropic_model'            => 'claude-sonnet-4-6',
				'gemini_model'               => 'gemini-3.5-flash',
				'openrouter_model'           => 'openai/gpt-5.6-luna',
				'deepseek_model'             => 'deepseek-v4-flash',
				'deepseek_thinking'          => false,
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

		$old_default = __( 'You are a concise and helpful website support assistant. Reply in the same language as the visitor.', 'wp-ds-aichatbot' );
		$new_default = __( 'You are a proactive sales assistant for this website. Your goal is to convert visitors into customers. First, understand the visitor\'s needs by asking 1–2 qualifying questions before presenting solutions. Highlight specific products, services, or benefits that match their situation. Use confident, benefit-focused language. If the visitor goes silent for more than a minute, gently re-engage with a follow-up question. Always end your response with a question or a call to action. Reply in the same language as the visitor.', 'wp-ds-aichatbot' );

		if ( isset( $value['ai_instructions'] ) && trim( (string) $value['ai_instructions'] ) === trim( $old_default ) ) {
			$value['ai_instructions'] = $new_default;
			update_option( self::OPTION_NAME, $value, false );
		}

		if ( ! isset( $value['ai_max_output_tokens'] ) && isset( $value['openai_max_output_tokens'] ) ) {
			$value['ai_max_output_tokens'] = $value['openai_max_output_tokens'];
		}

		$options = wp_parse_args( $value, self::defaults() );

		foreach ( self::$runtime_variables as $name => $replacement ) {
			$options['ai_instructions'] = str_replace(
				array( '{' . $name . '}', '(' . $name . ')' ),
				$replacement,
				(string) $options['ai_instructions']
			);
		}

		if ( '' !== self::$runtime_instruction_suffix ) {
			$options['ai_instructions'] .= "\n\n" . self::$runtime_instruction_suffix;
		}

		return $options;
	}

	/**
	 * Set bounded request-only template variables.
	 *
	 * @param array<string, string> $variables Runtime variables.
	 * @return void
	 */
	public static function set_runtime_variables( array $variables ): void {
		self::$runtime_variables = array();

		foreach ( $variables as $name => $value ) {
			$name  = sanitize_key( $name );
			$value = sanitize_text_field( $value );

			if ( '' !== $name && '' !== $value ) {
				self::$runtime_variables[ $name ] = self::limit_runtime_value( $value );
			}
		}
	}

	/** Clear request-only template variables after provider execution. */
	public static function clear_runtime_variables(): void {
		self::$runtime_variables          = array();
		self::$runtime_instruction_suffix = '';
	}

	/**
	 * Append bounded server-built instructions for the current provider call.
	 *
	 * @param string $suffix Trusted server-generated policy.
	 * @return void
	 */
	public static function set_runtime_instruction_suffix( string $suffix ): void {
		self::$runtime_instruction_suffix = self::limit_runtime_text( $suffix, 6000 );
	}

	/**
	 * Limit one runtime value to a safe length.
	 *
	 * @param string $value Sanitized value.
	 * @return string
	 */
	private static function limit_runtime_value( string $value ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 100 ) : substr( $value, 0, 100 );
	}

	/**
	 * Limit request-only policy text without altering its line structure.
	 *
	 * @param string $value Input policy.
	 * @param int    $limit Maximum characters.
	 * @return string
	 */
	private static function limit_runtime_text( string $value, int $limit ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	/**
	 * Return safe diagnostics for one provider without exposing its credential.
	 *
	 * @param string                    $provider Provider ID. Defaults to the selected provider.
	 * @param array<string, mixed>|null $options  Already sanitized settings when available.
	 * @return array<string, mixed>
	 */
	public static function provider_diagnostics( string $provider = '', ?array $options = null ): array {
		$options   = is_array( $options ) ? array_merge( self::defaults(), $options ) : self::get();
		$provider  = '' !== $provider ? sanitize_key( $provider ) : (string) $options['ai_provider'];
		$models    = array(
			'openai'       => 'openai_model',
			'anthropic'    => 'anthropic_model',
			'gemini'       => 'gemini_model',
			'openrouter'   => 'openrouter_model',
			'deepseek'     => 'deepseek_model',
			'wordpress_ai' => '',
		);
		$resolver  = new CredentialResolver();
		$source    = in_array( $provider, CredentialResolver::provider_ids(), true )
			? $resolver->source( $provider )
			: ( function_exists( 'wp_ai_client_prompt' ) ? 'wordpress_ai_client' : 'missing' );
		$model_key = $models[ $provider ] ?? '';

		return array(
			'provider'         => $provider,
			'model'            => '' !== $model_key ? (string) ( $options[ $model_key ] ?? '' ) : '',
			'credentialSource' => $source,
			'configured'       => 'missing' !== $source,
		);
	}

	/**
	 * Return safe diagnostics for every selectable provider.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all_provider_diagnostics(): array {
		$options     = self::get();
		$diagnostics = array();
		$providers   = array_merge( CredentialResolver::provider_ids(), array( 'wordpress_ai' ) );

		foreach ( $providers as $provider ) {
			$diagnostics[ $provider ] = self::provider_diagnostics( $provider, $options );
		}

		return $diagnostics;
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
		add_option( CredentialResolver::CREDENTIALS_OPTION, array(), '', false );

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
		$this->add_field( 'message_placeholder', __( 'Message input placeholder', 'wp-ds-aichatbot' ), 'text' );
		$this->add_field( 'reply_sound', __( 'Reply notification sound', 'wp-ds-aichatbot' ), 'sound_select' );
		$this->add_field( 'intro_trigger', __( 'Intro bubble trigger', 'wp-ds-aichatbot' ), 'intro_trigger_select' );
		$this->add_field( 'intro_delay_seconds', __( 'Intro bubble delay (seconds)', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'bot_avatar_id', __( 'Chatbot avatar', 'wp-ds-aichatbot' ), 'media' );
		$this->add_field( 'rate_limit_requests', __( 'Requests per window', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'rate_limit_window', __( 'Rate-limit window (seconds)', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'daily_request_limit', __( 'AI requests per 24 hours', 'wp-ds-aichatbot' ), 'number' );
		$this->add_field( 'knowledge_enabled', __( 'Use website knowledge', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_knowledge' );
		$this->add_field( 'knowledge_max_chunks', __( 'Knowledge fragments per answer', 'wp-ds-aichatbot' ), 'number', 'wpdsac_knowledge' );
		$this->add_field( 'knowledge_semantic_enabled', __( 'Semantic (vector) search', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_knowledge' );
		$this->add_field( 'embeddings_model', __( 'Embeddings model', 'wp-ds-aichatbot' ), 'text', 'wpdsac_knowledge' );
		$this->add_field( 'logging_enabled', __( 'Conversation logging', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_privacy' );
		$this->add_field( 'log_retention_days', __( 'Log retention (days)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_privacy' );
		$this->add_field( 'quick_call_label', __( 'Call button text', 'wp-ds-aichatbot' ), 'text', 'wpdsac_leads' );
		$this->add_field( 'quick_call_url', __( 'Call button URL', 'wp-ds-aichatbot' ), 'url', 'wpdsac_leads' );
		$this->add_field( 'quick_lead_label', __( 'Request button text', 'wp-ds-aichatbot' ), 'text', 'wpdsac_leads' );
		$this->add_field( 'quick_custom_actions', __( 'Custom quick buttons', 'wp-ds-aichatbot' ), 'quick_actions', 'wpdsac_leads' );
		$this->add_field( 'lead_prompt', __( 'Contact prompt', 'wp-ds-aichatbot' ), 'text', 'wpdsac_leads' );
		$this->add_field( 'lead_submit_label', __( 'Submit request button', 'wp-ds-aichatbot' ), 'text', 'wpdsac_leads' );
		$this->add_field( 'lead_notification_email', __( 'Notification email', 'wp-ds-aichatbot' ), 'email', 'wpdsac_leads' );
		$this->add_field( 'lead_consent_text', __( 'Consent text', 'wp-ds-aichatbot' ), 'textarea', 'wpdsac_leads' );
		$this->add_field( 'lead_retention_days', __( 'Lead retention (days)', 'wp-ds-aichatbot' ), 'number', 'wpdsac_leads' );
		$this->appearance->register_fields();
		$this->add_field( 'ai_provider', __( 'Provider', 'wp-ds-aichatbot' ), 'provider_select', 'wpdsac_ai' );
		$this->add_field( 'communication_style', __( 'Communication style', 'wp-ds-aichatbot' ), 'communication_style_select', 'wpdsac_ai' );
		$this->add_field( 'prompt_guard_enabled', __( 'Prompt injection protection', 'wp-ds-aichatbot' ), 'checkbox', 'wpdsac_ai' );
		$this->add_field( 'topic_scope', __( 'Allowed topics and keywords', 'wp-ds-aichatbot' ), 'textarea', 'wpdsac_ai' );
		$this->add_field( 'guard_refusal_message', __( 'Blocked request response', 'wp-ds-aichatbot' ), 'text', 'wpdsac_ai' );
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
		$refusal      = sanitize_text_field( $input['guard_refusal_message'] ?? '' );
		$refusal      = '' !== $refusal ? $refusal : $defaults['guard_refusal_message'];

		$settings = array(
			'global_enabled'             => ! empty( $input['global_enabled'] ),
			'title'                      => sanitize_text_field( $input['title'] ?? '' ),
			'welcome_message'            => sanitize_textarea_field( $input['welcome_message'] ?? '' ),
			'message_placeholder'        => sanitize_text_field( $input['message_placeholder'] ?? '' ),
			'reply_sound'                => $this->sanitize_reply_sound( $input['reply_sound'] ?? 'soft' ),
			'intro_trigger'              => $this->sanitize_intro_trigger( $input['intro_trigger'] ?? 'delay' ),
			'intro_delay_seconds'        => min( 300, max( 0, absint( $input['intro_delay_seconds'] ?? 10 ) ) ),
			'bot_avatar_id'              => absint( $input['bot_avatar_id'] ?? 0 ),
			'rate_limit_requests'        => min( 100, max( 1, absint( $input['rate_limit_requests'] ?? 10 ) ) ),
			'rate_limit_window'          => min( HOUR_IN_SECONDS, max( 10, absint( $input['rate_limit_window'] ?? 60 ) ) ),
			'daily_request_limit'        => min( 100000, absint( $input['daily_request_limit'] ?? 500 ) ),
			'knowledge_enabled'          => ! empty( $input['knowledge_enabled'] ),
			'knowledge_max_chunks'       => min( 8, max( 1, absint( $input['knowledge_max_chunks'] ?? 4 ) ) ),
			'knowledge_semantic_enabled' => ! empty( $input['knowledge_semantic_enabled'] ),
			'embeddings_model'           => sanitize_text_field( (string) ( $input['embeddings_model'] ?? 'text-embedding-3-small' ) ),
			'logging_enabled'            => ! empty( $input['logging_enabled'] ),
			'log_retention_days'         => min( 365, max( 1, absint( $input['log_retention_days'] ?? 30 ) ) ),
			'quick_call_label'           => $this->label_or_default( $input['quick_call_label'] ?? '', $defaults['quick_call_label'] ),
			'quick_call_url'             => esc_url_raw( (string) ( $input['quick_call_url'] ?? '' ), array( 'http', 'https', 'tel', 'sms' ) ),
			'quick_lead_label'           => $this->label_or_default( $input['quick_lead_label'] ?? '', $defaults['quick_lead_label'] ),
			'quick_custom_actions'       => QuickActions::sanitize( $input['quick_custom_actions'] ?? array() ),
			'lead_prompt'                => $lead_prompt,
			'lead_submit_label'          => $this->label_or_default( $input['lead_submit_label'] ?? '', $defaults['lead_submit_label'] ),
			'lead_notification_email'    => sanitize_email( $input['lead_notification_email'] ?? '' ),
			'lead_consent_text'          => $lead_consent,
			'lead_retention_days'        => min( 730, max( 1, absint( $input['lead_retention_days'] ?? 90 ) ) ),
			'ai_provider'                => $provider,
			'communication_style'        => sanitize_text_field( $input['communication_style'] ?? 'concierge' ),
			'ai_instructions'            => sanitize_textarea_field( $input['ai_instructions'] ?? '' ),
			'ai_max_output_tokens'       => min( 8000, max( 100, absint( $input['ai_max_output_tokens'] ?? 1200 ) ) ),
			'prompt_guard_enabled'       => ! empty( $input['prompt_guard_enabled'] ),
			'topic_scope'                => $this->limit_text( sanitize_textarea_field( $input['topic_scope'] ?? '' ), 2000 ),
			'guard_refusal_message'      => $this->limit_text( $refusal, 300 ),
			'openai_model'               => $this->sanitize_model_id( $input['openai_model'] ?? '', 'gpt-5.6-sol' ),
			'anthropic_model'            => $this->sanitize_model_id( $input['anthropic_model'] ?? '', 'claude-sonnet-4-6' ),
			'gemini_model'               => $this->sanitize_model_id( $input['gemini_model'] ?? '', 'gemini-3.5-flash' ),
			'openrouter_model'           => $this->sanitize_model_id( $input['openrouter_model'] ?? '', 'openai/gpt-5.6-luna' ),
			'deepseek_model'             => $this->sanitize_model_id( $input['deepseek_model'] ?? '', 'deepseek-v4-flash' ),
			'deepseek_thinking'          => ! empty( $input['deepseek_thinking'] ),
		);

		return array_merge( $settings, Appearance::sanitize( $input ) );
	}

	/**
	 * Sanitize a short interface label and preserve a useful default.
	 *
	 * @param mixed  $value   Submitted label.
	 * @param string $fallback Default label.
	 * @return string
	 */
	private function label_or_default( $value, string $fallback ): string {
		$value = $this->limit_text( sanitize_text_field( is_string( $value ) ? $value : '' ), 60 );

		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Restrict the reply sound to built-in quiet notification tones.
	 *
	 * @param mixed $value Submitted sound ID.
	 * @return string
	 */
	private function sanitize_reply_sound( $value ): string {
		$value   = sanitize_key( is_string( $value ) ? $value : '' );
		$allowed = array( 'off', 'soft', 'chime', 'pop' );

		return in_array( $value, $allowed, true ) ? $value : 'soft';
	}

	/**
	 * Restrict the intro bubble to supported browser triggers.
	 *
	 * @param mixed $value Submitted trigger ID.
	 * @return string
	 */
	private function sanitize_intro_trigger( $value ): string {
		$value   = sanitize_key( is_string( $value ) ? $value : '' );
		$allowed = array( 'delay', 'scroll', 'exit', 'immediate', 'disabled' );

		return in_array( $value, $allowed, true ) ? $value : 'delay';
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
	 * Save plugin settings without reloading the administration page.
	 *
	 * @return void
	 */
	public function save_ajax(): void {
		check_ajax_referer( 'wpdsac_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to change these settings.', 'wp-ds-aichatbot' ) ),
				403
			);
		}

		$raw_settings = isset( $_POST[ self::OPTION_NAME ] ) ? wp_unslash( $_POST[ self::OPTION_NAME ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the fixed settings schema below.
		$settings     = $this->sanitize( $raw_settings );
		$resolver     = new CredentialResolver();
		$api_keys     = array();
		$submitted    = array();
		$credentials  = isset( $_POST['wpdsac_credentials'] ) ? wp_unslash( $_POST['wpdsac_credentials'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is validated by sanitize_api_key().
		$credentials  = is_array( $credentials ) ? $credentials : array();
		$payload_json = isset( $_POST['wpdsac_credential_payload'] ) ? wp_unslash( $_POST['wpdsac_credential_payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded and validated against the provider/key schema below.
		$payload      = is_string( $payload_json ) ? json_decode( $payload_json, true ) : null;

		if ( is_array( $payload ) ) {
			$payload_provider = sanitize_key( $payload['provider'] ?? '' );
			$payload_key      = $payload['credential'] ?? '';

			if ( in_array( $payload_provider, CredentialResolver::provider_ids(), true ) && is_string( $payload_key ) ) {
				$credentials[ $payload_provider ] = $payload_key;
			}
		}

		foreach ( CredentialResolver::provider_ids() as $provider_id ) {
			$option             = $resolver->option_name( $provider_id );
			$has_structured_key = array_key_exists( $provider_id, $credentials );
			$has_legacy_key     = isset( $_POST[ $option ] );

			if ( ! $has_structured_key && ! $has_legacy_key ) {
				continue;
			}

			$raw_key                   = $has_structured_key ? $credentials[ $provider_id ] : wp_unslash( $_POST[ $option ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by sanitize_api_key() against length and whitespace constraints.
			$api_keys[ $provider_id ]  = $this->sanitize_api_key( $raw_key, $provider_id );
			$submitted[ $provider_id ] = is_string( $raw_key ) && '' !== trim( $raw_key );
		}

		$errors = get_settings_errors();

		if ( array() !== $errors ) {
			wp_send_json_error(
				array( 'message' => wp_strip_all_tags( (string) $errors[0]['message'] ) ),
				400
			);
		}

		update_option( self::OPTION_NAME, $settings, false );

		$credential_store = get_option( CredentialResolver::CREDENTIALS_OPTION, array() );
		$credential_store = is_array( $credential_store ) ? $credential_store : array();

		foreach ( $api_keys as $provider_id => $api_key ) {
			$credential_store[ $provider_id ] = $api_key;
			update_option( $resolver->option_name( $provider_id ), $api_key, false );
		}

		update_option( CredentialResolver::CREDENTIALS_OPTION, $credential_store, false );

		$diagnostics                        = self::provider_diagnostics( (string) $settings['ai_provider'], $settings );
		$diagnostics['credentialSubmitted'] = ! empty( $submitted[ $settings['ai_provider'] ] );
		$diagnostics['storageVerified']     = ! empty( $diagnostics['configured'] );

		if ( in_array( $settings['ai_provider'], CredentialResolver::provider_ids(), true ) && empty( $diagnostics['configured'] ) ) {
			wp_send_json_error(
				array(
					'message'     => __( 'Settings were saved, but the selected provider still has no API key. Open the browser console and run wpdsacDebugProvider().', 'wp-ds-aichatbot' ),
					'diagnostics' => $diagnostics,
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Settings saved.', 'wp-ds-aichatbot' ),
				'diagnostics' => $diagnostics,
			)
		);
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_menu_page(
			esc_html( PluginInfo::versioned_label( __( 'WP DS AI Chatbot', 'wp-ds-aichatbot' ) ) ),
			esc_html( PluginInfo::versioned_label( __( 'DS AI Chatbot', 'wp-ds-aichatbot' ) ) ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			add_query_arg( 'ver', WPDSAC_VERSION, WPDSAC_URL . 'wp-chatbot.svg' ),
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			esc_html( PluginInfo::versioned_label( __( 'WP DS AI Chatbot', 'wp-ds-aichatbot' ) ) ),
			esc_html__( 'Settings', 'wp-ds-aichatbot' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Keep the settings screen as the first/default plugin submenu.
	 *
	 * @return void
	 */
	public function ensure_settings_first(): void {
		global $submenu;

		if ( empty( $submenu[ self::PAGE_SLUG ] ) || ! is_array( $submenu[ self::PAGE_SLUG ] ) ) {
			return;
		}

		$settings_item = null;

		foreach ( $submenu[ self::PAGE_SLUG ] as $index => $item ) {
			if ( isset( $item[2] ) && self::PAGE_SLUG === $item[2] ) {
				$settings_item = $item;
				unset( $submenu[ self::PAGE_SLUG ][ $index ] );
				break;
			}
		}

		if ( is_array( $settings_item ) ) {
			$submenu[ self::PAGE_SLUG ] = array_values( $submenu[ self::PAGE_SLUG ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering this plugin's registered submenu entries only.
			array_unshift( $submenu[ self::PAGE_SLUG ], $settings_item );
		}
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
				<div class="wpdsac-settings-brand">
					<img src="<?php echo esc_url( WPDSAC_URL . 'wp-chatbot.svg' ); ?>" width="46" height="46" alt="">
					<div>
					<h1><?php esc_html_e( 'WP DS AI Chatbot', 'wp-ds-aichatbot' ); ?></h1>
					<p><?php esc_html_e( 'Configure the chatbot, AI providers, knowledge, and privacy from one place.', 'wp-ds-aichatbot' ); ?></p>
					</div>
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
					<span class="wpdsac-save-note" data-wpdsac-save-status aria-live="polite"><?php esc_html_e( 'Unsaved changes apply only after saving.', 'wp-ds-aichatbot' ); ?></span>
				</div>
			</form>
			<script>
			(function(){
				var style=document.getElementById('wpdsac-communication-style'),
					ta=document.querySelector('textarea[name="wpdsac_settings[ai_instructions]"]');
				if(!style||!ta)return;
				var presets=<?php echo wp_json_encode( wp_list_pluck( self::$communication_styles, 'instructions' ) ); ?>;
				var saved=ta.value;
				var isPreset=Object.values(presets).some(function(v){return v===saved;});
				if(isPreset){
					var k=Object.keys(presets).find(function(k){return presets[k]===saved;});
					if(k)style.value=k;
				}
				style.addEventListener('change',function(){
					if(this.value==='custom')return;
					if(presets[this.value]){
						ta.value=presets[this.value];
						ta.dispatchEvent(new Event('input',{bubbles:true}));
					}
				});
				ta.addEventListener('input',function(){
					var match=Object.keys(presets).find(function(k){return presets[k]===ta.value;});
					style.value=match||'custom';
				});
			})();
			</script>
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
			if ( $include_preview ) {
				$this->render_appearance_workspace( $section_ids, $section_titles );
			} else {
				foreach ( $section_ids as $section_id ) {
					$this->render_settings_fields_table( $section_id );
				}

				if ( 'ai' === $tab_id ) {
					$this->render_provider_diagnostics();
				}
			}
			?>
		</section>
		<?php
	}

	/**
	 * Render compact appearance controls beside a persistent live preview.
	 *
	 * @param array<int, string>    $section_ids    Appearance section IDs.
	 * @param array<string, string> $section_titles Appearance section titles.
	 * @return void
	 */
	private function render_appearance_workspace( array $section_ids, array $section_titles ): void {
		?>
		<div class="wpdsac-appearance-workspace">
			<div class="wpdsac-appearance-controls">
				<?php foreach ( $section_ids as $index => $section_id ) : ?>
					<details class="wpdsac-control-group" <?php echo 0 === $index ? 'open' : ''; ?>>
						<summary><?php echo esc_html( $section_titles[ $section_id ] ?? '' ); ?></summary>
						<div class="wpdsac-control-group__body">
							<?php $this->render_settings_fields_table( $section_id ); ?>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
			<div class="wpdsac-appearance-preview">
				<?php $this->appearance->render_preview(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Settings API rows inside their required semantic table wrapper.
	 *
	 * @param string $section_id Settings section identifier.
	 * @return void
	 */
	private function render_settings_fields_table( string $section_id ): void {
		?>
		<table class="form-table wpdsac-fields-table" role="presentation">
			<tbody>
				<?php do_settings_fields( 'wpdsac-settings', $section_id ); ?>
			</tbody>
		</table>
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

		if ( 'sound_select' === $args['type'] ) {
			$this->render_sound_select( $name, (string) $options[ $key ] );
			return;
		}

		if ( 'intro_trigger_select' === $args['type'] ) {
			$this->render_intro_trigger_select( $name, (string) $options[ $key ] );
			return;
		}

		if ( 'communication_style_select' === $args['type'] ) {
			$this->render_communication_style_select( $name, (string) $options[ $key ] );
			return;
		}

		if ( 'media' === $args['type'] ) {
			$this->render_avatar_field( $name, absint( $options[ $key ] ) );
			return;
		}

		if ( 'quick_actions' === $args['type'] ) {
			$this->render_quick_actions_repeater( QuickActions::sanitize( $options[ $key ] ?? array() ) );
			return;
		}

		if ( 'checkbox' === $args['type'] ) {
			$descriptions = array(
				'global_enabled'       => __( 'Show the chatbot globally in the site footer.', 'wp-ds-aichatbot' ),
				'knowledge_enabled'    => __( 'Add relevant indexed pages, posts, knowledge entries, and administrator text to AI requests.', 'wp-ds-aichatbot' ),
				'logging_enabled'      => __( 'Store successful conversations for the configured retention period. Disabled by default.', 'wp-ds-aichatbot' ),
				'deepseek_thinking'    => __( 'Enable deeper reasoning. This can increase response time and token usage.', 'wp-ds-aichatbot' ),
				'prompt_guard_enabled' => __( 'Block obvious instruction overrides, hidden-prompt requests, model probing, and configured off-topic requests before contacting the AI provider.', 'wp-ds-aichatbot' ),
			);
			$description  = $descriptions[ $key ] ?? '';

			printf(
				'<label class="wpdsac-checkbox-field"><input type="checkbox" name="%1$s" value="1" %2$s> <span>%3$s</span></label>',
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

			if ( 'topic_scope' === $key ) {
				printf(
					'<p class="description">%s</p>',
					esc_html__( 'Describe this website and list allowed topics or keywords. When filled, unrelated questions are blocked before the AI request.', 'wp-ds-aichatbot' )
				);
			}

			if ( 'ai_instructions' === $key ) {
				printf(
					'<p class="description">%s</p>',
					esc_html__( 'Use {username} to insert the visitor name. The (username) alias is also supported.', 'wp-ds-aichatbot' )
				);
			}
			return;
		}

		if ( 'number' === $args['type'] ) {
			$minimum = in_array( $key, array( 'daily_request_limit', 'intro_delay_seconds' ), true ) ? 0 : 1;

			printf(
				'<input class="small-text" type="number" min="%1$d" step="1" name="%2$s" value="%3$d">',
				absint( $minimum ),
				esc_attr( $name ),
				(int) $options[ $key ]
			);
			return;
		}

		if ( 'email' === $args['type'] ) {
			printf(
				'<input class="regular-text" type="email" name="%1$s" value="%2$s" autocomplete="email"><p class="description">%3$s</p>',
				esc_attr( $name ),
				esc_attr( (string) $options[ $key ] ),
				esc_html__( 'A new request and its chat transcript will be sent to this address.', 'wp-ds-aichatbot' )
			);
			return;
		}

		if ( 'url' === $args['type'] ) {
			printf(
				'<input class="regular-text" type="url" name="%1$s" value="%2$s" placeholder="https://…"><p class="description">%3$s</p>',
				esc_attr( $name ),
				esc_attr( (string) $options[ $key ] ),
				esc_html__( 'Leave empty to use the built-in action. The Call button also accepts a tel: URL.', 'wp-ds-aichatbot' )
			);
			return;
		}

		$preview_attributes = array(
			'title'               => ' data-wpdsac-preview-text=".wpdsac-chat__toggle-title"',
			'message_placeholder' => ' data-wpdsac-preview-placeholder=".wpdsac-chat__form input"',
		);
		$preview_attribute  = $preview_attributes[ $key ] ?? '';

		printf(
			'<input class="regular-text" type="text" name="%1$s" value="%2$s"%3$s>',
			esc_attr( $name ),
			esc_attr( (string) $options[ $key ] ),
			$preview_attribute // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed internal attribute.
		);
	}

	/**
	 * Render a bounded repeater for custom message and URL actions.
	 *
	 * @param array<int, array{id: string, label: string, type: string, value: string}> $actions Saved actions.
	 * @return void
	 */
	private function render_quick_actions_repeater( array $actions ): void {
		?>
		<div class="wpdsac-action-repeater" data-wpdsac-action-repeater>
			<div class="wpdsac-action-repeater__rows" data-wpdsac-action-rows>
				<?php foreach ( $actions as $index => $action ) : ?>
					<?php $this->render_quick_action_row( (int) $index, $action ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" data-wpdsac-add-action><?php esc_html_e( 'Add quick button', 'wp-ds-aichatbot' ); ?></button>
			<p class="description"><?php esc_html_e( 'Up to 8 buttons. A message button sends a prepared phrase; a link button opens its URL.', 'wp-ds-aichatbot' ); ?></p>
			<template data-wpdsac-action-template>
				<?php
				$this->render_quick_action_row(
					'__INDEX__',
					array(
						'label' => '',
						'type'  => 'message',
						'value' => '',
					)
				);
				?>
			</template>
		</div>
		<?php
	}

	/**
	 * Render one custom quick-action row.
	 *
	 * @param int|string            $index  Numeric index or template placeholder.
	 * @param array<string, string> $action Action values.
	 * @return void
	 */
	private function render_quick_action_row( $index, array $action ): void {
		$base = self::OPTION_NAME . '[quick_custom_actions][' . $index . ']';
		?>
		<div class="wpdsac-action-row" data-wpdsac-action-row>
			<label>
				<span><?php esc_html_e( 'Button text', 'wp-ds-aichatbot' ); ?></span>
				<input type="text" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $action['label'] ?? '' ); ?>" maxlength="60">
			</label>
			<label>
				<span><?php esc_html_e( 'Action', 'wp-ds-aichatbot' ); ?></span>
				<select name="<?php echo esc_attr( $base . '[type]' ); ?>" data-wpdsac-action-type>
					<option value="message" <?php selected( $action['type'] ?? '', 'message' ); ?>><?php esc_html_e( 'Send message', 'wp-ds-aichatbot' ); ?></option>
					<option value="url" <?php selected( $action['type'] ?? '', 'url' ); ?>><?php esc_html_e( 'Open URL', 'wp-ds-aichatbot' ); ?></option>
				</select>
			</label>
			<label class="wpdsac-action-row__value">
				<span><?php esc_html_e( 'Message or URL', 'wp-ds-aichatbot' ); ?></span>
				<input type="text" name="<?php echo esc_attr( $base . '[value]' ); ?>" value="<?php echo esc_attr( $action['value'] ?? '' ); ?>" maxlength="500">
			</label>
			<button type="button" class="button-link-delete" data-wpdsac-remove-action><?php esc_html_e( 'Remove', 'wp-ds-aichatbot' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Wrap a provider-specific field in a reliable DOM marker.
	 *
	 * @param array<string, string> $args Field arguments.
	 * @return void
	 */
	public function render_provider_field( array $args ): void {
		printf( '<div data-wpdsac-provider-field="%s">', esc_attr( $args['provider'] ) );
		$this->render_field( $args );
		echo '</div>';
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
			array( $this, '' !== $provider ? 'render_provider_field' : 'render_field' ),
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
			'<input class="regular-text" type="password" name="%1$s" value="" autocomplete="new-password" placeholder="%2$s" %3$s data-wpdsac-api-key data-wpdsac-provider="%4$s">',
			esc_attr( $resolver->option_name( $provider ) ),
			esc_attr( 'missing' === $source ? '' : '••••••••••••' ),
			disabled( $disabled, true, false ),
			esc_attr( $provider )
		);

		if ( $disabled ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'The key is supplied by wp-config.php or the server environment and cannot be changed here.', 'wp-ds-aichatbot' )
			);
		} else {
			printf(
				'<p class="description" data-wpdsac-key-status %1$s><strong>%2$s</strong></p>',
				'option' === $source ? '' : 'hidden',
				esc_html__( 'API key saved. Leave this field empty to keep it.', 'wp-ds-aichatbot' )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'The saved key is never displayed. Prefer the provider-specific WPDSAC_*_API_KEY constant or environment variable.', 'wp-ds-aichatbot' )
			);
		}
	}

	/**
	 * Render safe active-provider diagnostics.
	 *
	 * @return void
	 */
	private function render_provider_diagnostics(): void {
		$diagnostics = self::provider_diagnostics();
		?>
		<div class="wpdsac-provider-diagnostics" data-wpdsac-provider-diagnostics>
			<h3><?php esc_html_e( 'Provider diagnostics', 'wp-ds-aichatbot' ); ?></h3>
			<p><?php esc_html_e( 'No API key value is exposed. For console diagnostics run wpdsacDebugProvider().', 'wp-ds-aichatbot' ); ?></p>
			<dl>
				<div><dt><?php esc_html_e( 'Provider', 'wp-ds-aichatbot' ); ?></dt><dd data-wpdsac-debug-provider><?php echo esc_html( (string) $diagnostics['provider'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Model', 'wp-ds-aichatbot' ); ?></dt><dd data-wpdsac-debug-model><?php echo esc_html( (string) $diagnostics['model'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Credential source', 'wp-ds-aichatbot' ); ?></dt><dd data-wpdsac-debug-source><?php echo esc_html( (string) $diagnostics['credentialSource'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Configured', 'wp-ds-aichatbot' ); ?></dt><dd data-wpdsac-debug-configured><?php echo ! empty( $diagnostics['configured'] ) ? esc_html__( 'Yes', 'wp-ds-aichatbot' ) : esc_html__( 'No', 'wp-ds-aichatbot' ); ?></dd></div>
			</dl>
		</div>
		<?php
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
	 * Render the built-in reply notification sound selector.
	 *
	 * @param string $name    Input name.
	 * @param string $current Selected sound ID.
	 * @return void
	 */
	private function render_sound_select( string $name, string $current ): void {
		$options = array(
			'off'   => __( 'No sound', 'wp-ds-aichatbot' ),
			'soft'  => __( 'Soft tone', 'wp-ds-aichatbot' ),
			'chime' => __( 'Short chime', 'wp-ds-aichatbot' ),
			'pop'   => __( 'Soft pop', 'wp-ds-aichatbot' ),
		);

		printf( '<select name="%1$s" data-wpdsac-sound-select>', esc_attr( $name ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		printf(
			'<button type="button" class="button wpdsac-sound-preview" data-wpdsac-sound-preview>%s</button><p class="description">%s</p>',
			esc_html__( 'Play', 'wp-ds-aichatbot' ),
			esc_html__( 'A short quiet sound plays only after the assistant replies.', 'wp-ds-aichatbot' )
		);
	}

	/**
	 * Render the automatic intro bubble trigger selector.
	 *
	 * @param string $name    Input name.
	 * @param string $current Selected trigger ID.
	 * @return void
	 */
	private function render_intro_trigger_select( string $name, string $current ): void {
		$options = array(
			'delay'     => __( 'After a delay', 'wp-ds-aichatbot' ),
			'scroll'    => __( 'After 50% page scroll', 'wp-ds-aichatbot' ),
			'exit'      => __( 'On exit intent', 'wp-ds-aichatbot' ),
			'immediate' => __( 'Immediately', 'wp-ds-aichatbot' ),
			'disabled'  => __( 'Do not show automatically', 'wp-ds-aichatbot' ),
		);

		printf( '<select name="%1$s">', esc_attr( $name ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'The name request remains inside the chat when a visitor opens it before the bubble appears.', 'wp-ds-aichatbot' )
		);
	}

	/**
	 * Render the communication style select with preset instructions.
	 *
	 * @param string $name    Input name.
	 * @param string $current Currently saved style key.
	 * @return void
	 */
	private function render_communication_style_select( string $name, string $current ): void {
		if ( ! in_array( $current, array_keys( self::$communication_styles ), true ) ) {
			$current = 'custom';
		}

		printf( '<select name="%1$s" id="wpdsac-communication-style">', esc_attr( $name ) );
		foreach ( self::$communication_styles as $key => $style ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $current, $key, false ),
				esc_html( $style['label'] )
			);
		}
		printf(
			'<option value="custom"%1$s>%2$s</option>',
			selected( $current, 'custom', false ),
			esc_html__( 'Custom…', 'wp-ds-aichatbot' )
		);
		echo '</select>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Choose a ready-made communication style or select "Custom…" and write your own instructions below.', 'wp-ds-aichatbot' )
		);
	}

	/**
	 * Render the WordPress media-library avatar picker.
	 *
	 * @param string $name          Input name.
	 * @param int    $attachment_id Selected attachment ID.
	 * @return void
	 */
	private function render_avatar_field( string $name, int $attachment_id ): void {
		$default_url = WPDSAC_URL . 'wp-chatbot.svg';
		$avatar_url  = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		$avatar_url  = $avatar_url ? $avatar_url : $default_url;
		?>
		<div class="wpdsac-avatar-control" data-wpdsac-avatar-control data-wpdsac-default-avatar="<?php echo esc_url( $default_url ); ?>">
			<img src="<?php echo esc_url( $avatar_url ); ?>" width="64" height="64" alt="" data-wpdsac-avatar-preview>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo absint( $attachment_id ); ?>" data-wpdsac-avatar-id>
			<div>
				<button type="button" class="button" data-wpdsac-avatar-select><?php esc_html_e( 'Choose avatar', 'wp-ds-aichatbot' ); ?></button>
				<button type="button" class="button-link-delete" data-wpdsac-avatar-remove<?php echo $attachment_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove avatar', 'wp-ds-aichatbot' ); ?></button>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'The avatar appears beside every assistant message, like in WhatsApp or Telegram.', 'wp-ds-aichatbot' ); ?></p>
		<?php
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

	/**
	 * Limit sanitized administrator text without breaking UTF-8 strings.
	 *
	 * @param string $value  Sanitized text.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private function limit_text( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length, 'UTF-8' ) : substr( $value, 0, $length );
	}
}
