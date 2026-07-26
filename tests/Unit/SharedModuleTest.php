<?php
/**
 * Behavioral tests for the shared JS module extraction.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verify that wpdsac-shared.js and chat.js integration is correct.
 */
final class SharedModuleTest extends TestCase {

	/**
	 * Path to the shared JS file.
	 *
	 * @var string
	 */
	private $shared_path;

	/**
	 * Path to the chat JS file.
	 *
	 * @var string
	 */
	private $chat_path;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->shared_path = dirname( __DIR__, 2 ) . '/assets/build/wpdsac-shared.js';
		$this->chat_path   = dirname( __DIR__, 2 ) . '/assets/build/chat.js';
	}

	/**
	 * wpdsac-shared.js must exist.
	 *
	 * @test
	 */
	public function shared_file_exists(): void {
		$this->assertFileExists( $this->shared_path );
	}

	/**
	 * chat.js must exist.
	 *
	 * @test
	 */
	public function chat_file_exists(): void {
		$this->assertFileExists( $this->chat_path );
	}

	/**
	 * wpdsac-shared.js exposes all expected functions on window.wpdsacShared.
	 *
	 * @test
	 */
	public function shared_module_exposes_expected_api(): void {
		$shared = file_get_contents( $this->shared_path );
		$this->assertIsString( $shared );

		$expected = array(
			'leadNavigationHash',
			'request',
			'scrollToLatest',
			'renderMarkdown',
			'safeNavigationUrl',
			'assistantPreviewText',
			'appendAssistantContent',
			'animateAssistantContent',
			'appendMessage',
		);

		foreach ( $expected as $fn ) {
			$this->assertStringContainsString(
				$fn,
				$shared,
				"wpdsac-shared.js must expose '{$fn}' on window.wpdsacShared"
			);
		}

		$this->assertStringContainsString( 'window.wpdsacShared', $shared );
	}

	/**
	 * wpdsac-shared.js must not contain module-level config/strings constants.
	 *
	 * @test
	 */
	public function shared_module_has_no_local_config_constants(): void {
		$shared = file_get_contents( $this->shared_path );

		$this->assertStringNotContainsString(
			"const config = window.wpdsacChatConfig",
			$shared,
			'shared module must use getConfig() not a module-level config constant'
		);
		$this->assertStringContainsString(
			'const getConfig = () =>',
			$shared,
			'shared module must use getConfig() helper for config access'
		);
	}

	/**
	 * chat.js must not define the extracted functions locally.
	 *
	 * @test
	 */
	public function chat_file_does_not_redefine_extracted_functions(): void {
		$chat = file_get_contents( $this->chat_path );
		$this->assertIsString( $chat );

		$extracted = array(
			'const renderMarkdown =',
			'const applyInlineMarkdown =',
			'const safeNavigationUrl =',
			'const appendAssistantContent =',
			'const assistantPreviewText =',
			'const animateAssistantContent =',
			'const scrollToLatest =',
			'const appendMessage =',
		);

		foreach ( $extracted as $pattern ) {
			$this->assertStringNotContainsString(
				$pattern,
				$chat,
				"chat.js must not locally define: {$pattern}"
			);
		}
	}

	/**
	 * chat.js must not define request() locally (it comes from shared).
	 *
	 * @test
	 */
	public function chat_file_does_not_redefine_request(): void {
		$chat = file_get_contents( $this->chat_path );

		$this->assertStringNotContainsString(
			'const request = async (path, body = {})',
			$chat,
			'chat.js must not locally define request()'
		);
	}

	/**
	 * chat.js must reference window.wpdsacShared via destructuring.
	 *
	 * @test
	 */
	public function chat_file_references_shared_module(): void {
		$chat = file_get_contents( $this->chat_path );

		$this->assertStringContainsString(
			'window.wpdsacShared',
			$chat,
			'chat.js must reference window.wpdsacShared'
		);
		$this->assertStringContainsString(
			'leadNavigationHash',
			$chat,
			'chat.js must destructure leadNavigationHash from shared module'
		);
	}

	/**
	 * chat.js must not define leadNavigationHash locally.
	 *
	 * @test
	 */
	public function chat_file_does_not_define_lead_navigation_hash(): void {
		$chat = file_get_contents( $this->chat_path );

		$this->assertStringNotContainsString(
			"const leadNavigationHash = '#wpdsac-contact-form'",
			$chat,
			'chat.js must not locally define leadNavigationHash'
		);
	}

	/**
	 * Assets.php must register wpdsac-shared as a dependency of wpdsac-chat.
	 *
	 * @test
	 */
	public function assets_registers_shared_dependency(): void {
		$assets_path = dirname( __DIR__, 2 ) . '/src/Chat/Assets.php';
		$this->assertFileExists( $assets_path );

		$assets = file_get_contents( $assets_path );
		$this->assertStringContainsString(
			"'wpdsac-shared'",
			$assets,
			'Assets.php must register wpdsac-shared script handle'
		);
		$this->assertStringContainsString(
			"array( 'wpdsac-shared' )",
			$assets,
			'Assets.php must list wpdsac-shared as dependency of wpdsac-chat'
		);
	}

	/**
	 * Both JS files must have valid IIFE structure.
	 *
	 * @test
	 */
	public function both_js_files_have_valid_syntax(): void {
		$shared = file_get_contents( $this->shared_path );
		$chat   = file_get_contents( $this->chat_path );

		$this->assertIsString( $shared );
		$this->assertIsString( $chat );

		// Verify both files contain IIFE wrapper (may be preceded by comments).
		$this->assertStringContainsString( '(() => {', $shared );
		$this->assertStringContainsString( '(() => {', $chat );

		// Verify both files end with IIFE closing.
		$this->assertStringEndsWith( "})();\n", $shared );
		$this->assertStringEndsWith( "})();\n", $chat );
	}

	/**
	 * chat.js line count must remain above 60% of original (guard limit).
	 *
	 * Original was 1898 lines. 60% = 1139 lines minimum.
	 *
	 * @test
	 */
	public function chat_file_size_remains_above_guard_limit(): void {
		$chat    = file_get_contents( $this->chat_path );
		$lines   = explode( "\n", $chat );
		$count   = count( $lines );
		$minimum = 1139;

		$this->assertGreaterThanOrEqual(
			$minimum,
			$count,
			"chat.js has {$count} lines, must be >= {$minimum} (60% of 1898)"
		);
	}
}
