<?php
/**
 * Main plugin coordinator.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\Admin\KnowledgePage;
use DiasMazhenov\WPDsAiChatbot\Admin\LeadsPage;
use DiasMazhenov\WPDsAiChatbot\AI\CredentialResolver;
use DiasMazhenov\WPDsAiChatbot\AI\AnthropicProvider;
use DiasMazhenov\WPDsAiChatbot\AI\DeepSeekProvider;
use DiasMazhenov\WPDsAiChatbot\AI\GeminiProvider;
use DiasMazhenov\WPDsAiChatbot\AI\OpenAIProvider;
use DiasMazhenov\WPDsAiChatbot\AI\OpenRouterProvider;
use DiasMazhenov\WPDsAiChatbot\AI\ProviderManager;
use DiasMazhenov\WPDsAiChatbot\AI\WordPressAiClientProvider;
use DiasMazhenov\WPDsAiChatbot\Api\ChatController;
use DiasMazhenov\WPDsAiChatbot\Api\LeadController;
use DiasMazhenov\WPDsAiChatbot\Api\RateLimiter;
use DiasMazhenov\WPDsAiChatbot\Api\RequestLock;
use DiasMazhenov\WPDsAiChatbot\Api\SessionController;
use DiasMazhenov\WPDsAiChatbot\Api\SessionToken;
use DiasMazhenov\WPDsAiChatbot\Chat\Assets;
use DiasMazhenov\WPDsAiChatbot\Chat\Renderer;
use DiasMazhenov\WPDsAiChatbot\Chat\Shortcode;
use DiasMazhenov\WPDsAiChatbot\Data\ConversationLogger;
use DiasMazhenov\WPDsAiChatbot\Data\ConversationRepository;
use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;
use DiasMazhenov\WPDsAiChatbot\Elementor\Integration;
use DiasMazhenov\WPDsAiChatbot\Lifecycle\Migrator;
use DiasMazhenov\WPDsAiChatbot\Knowledge\Chunker;
use DiasMazhenov\WPDsAiChatbot\Knowledge\FaqPostType;
use DiasMazhenov\WPDsAiChatbot\Knowledge\PdfIndexer;
use DiasMazhenov\WPDsAiChatbot\Knowledge\PostIndexer;
use DiasMazhenov\WPDsAiChatbot\Knowledge\Repository;
use DiasMazhenov\WPDsAiChatbot\Knowledge\Retriever;
use DiasMazhenov\WPDsAiChatbot\Knowledge\WooCommerceSource;
use DiasMazhenov\WPDsAiChatbot\Privacy\ConversationPrivacy;
use DiasMazhenov\WPDsAiChatbot\Privacy\LeadPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Compose plugin services and register their WordPress hooks.
 */
final class Plugin {

	/**
	 * Singleton coordinator instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Return the single coordinator instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register plugin services and hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		$assets        = new Assets();
		$renderer      = new Renderer( $assets );
		$tokens        = new SessionToken();
		$rate_limiter  = new RateLimiter();
		$request_lock  = new RequestLock();
		$chat_api      = new ChatController( $tokens, $rate_limiter, $request_lock );
		$session_api   = new SessionController( $tokens );
		$credentials   = new CredentialResolver();
		$knowledge     = new Repository();
		$chunker       = new Chunker();
		$post_indexer  = new PostIndexer( $knowledge, $chunker );
		$pdf_indexer   = new PdfIndexer( $knowledge, $chunker );
		$retriever     = new Retriever( $knowledge );
		$conversations = new ConversationRepository();
		$leads         = new LeadRepository();
		$logger        = new ConversationLogger( $conversations );
		$providers     = new ProviderManager(
			array(
				'openai'       => new OpenAIProvider( $credentials ),
				'anthropic'    => new AnthropicProvider( $credentials ),
				'gemini'       => new GeminiProvider( $credentials ),
				'openrouter'   => new OpenRouterProvider( $credentials ),
				'deepseek'     => new DeepSeekProvider( $credentials ),
				'wordpress_ai' => new WordPressAiClientProvider(),
			)
		);

		Migrator::maybe_migrate();

		( new Settings() )->register_hooks();
		$assets->register_hooks();
		$rate_limiter->register_hooks();
		$request_lock->register_hooks();
		$chat_api->register_hooks();
		$session_api->register_hooks();
		( new LeadController( $tokens, $rate_limiter, $leads ) )->register_hooks();
		$providers->register_hooks();
		$post_indexer->register_hooks();
		$pdf_indexer->register_hooks();
		( new WooCommerceSource() )->register_hooks();
		$retriever->register_hooks();
		$logger->register_hooks();
		( new ConversationPrivacy( $conversations ) )->register_hooks();
		( new LeadPrivacy( $leads ) )->register_hooks();
		( new FaqPostType() )->register_hooks();
		( new Shortcode( $renderer ) )->register_hooks();
		( new Integration( $renderer ) )->register_hooks();

		if ( is_admin() ) {
			( new KnowledgePage( $post_indexer, $pdf_indexer, $knowledge ) )->register_hooks();
			( new LeadsPage( $leads ) )->register_hooks();
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_footer', array( $renderer, 'render_global' ) );
	}

	/**
	 * Load translations at init.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-ds-aichatbot',
			false,
			dirname( plugin_basename( WPDSAC_FILE ) ) . '/languages'
		);
	}

	/**
	 * Prevent direct construction of the coordinator.
	 */
	private function __construct() {}
}
