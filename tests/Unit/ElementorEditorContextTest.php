<?php
/**
 * Elementor editor context regression tests.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\Chat\Renderer;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/src/Chat/Renderer.php';

final class ElementorEditorContextTest extends TestCase {

	protected function tearDown(): void {
		unset( $_GET['action'], $_GET['post'], $_GET['elementor-preview'] );
		parent::tearDown();
	}

	public function test_admin_elementor_url_is_detected(): void {
		$_GET['action'] = 'elementor';
		$_GET['post']   = '123';

		self::assertTrue( Renderer::is_elementor_editor_context() );
	}

	public function test_elementor_preview_url_is_detected(): void {
		$_GET['elementor-preview'] = '123';

		self::assertTrue( Renderer::is_elementor_editor_context() );
	}

	public function test_regular_frontend_url_is_not_detected(): void {
		$_GET['post'] = '123';

		self::assertFalse( Renderer::is_elementor_editor_context() );
	}
}
