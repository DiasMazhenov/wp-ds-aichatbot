<?php
/**
 * Pure security and boundary tests.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\Api\LeadController;
use DiasMazhenov\WPDsAiChatbot\Api\SessionToken;
use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\Admin\PluginList;
use DiasMazhenov\WPDsAiChatbot\AI\CredentialResolver;
use DiasMazhenov\WPDsAiChatbot\AI\DeepSeekProvider;
use DiasMazhenov\WPDsAiChatbot\Chat\Appearance;
use DiasMazhenov\WPDsAiChatbot\Knowledge\Chunker;
use DiasMazhenov\WPDsAiChatbot\Support\PluginInfo;
use PHPUnit\Framework\TestCase;

final class CoreSecurityTest extends TestCase {

	public function test_session_token_round_trip_and_tamper_rejection(): void {
		$tokens = new SessionToken();
		$issued = $tokens->issue();

		$this->assertSame( '123e4567-e89b-42d3-a456-426614174000', $tokens->validate( $issued['token'] ) );

		$tampered = $issued['token'] . 'x';
		$error    = $tokens->validate( $tampered );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'wpdsac_invalid_session', $error->get_error_code() );
	}

	public function test_chunker_strips_markup_and_bounds_fragments(): void {
		$chunks = ( new Chunker() )->split( '<b>Verified</b> [private] ' . str_repeat( 'word ', 600 ) );

		$this->assertNotEmpty( $chunks );
		$this->assertStringNotContainsString( '<b>', implode( ' ', $chunks ) );
		$this->assertStringNotContainsString( '[private]', implode( ' ', $chunks ) );

		foreach ( $chunks as $chunk ) {
			$this->assertLessThanOrEqual( 1200, strlen( $chunk ) );
		}
	}

	public function test_appearance_rejects_untrusted_values(): void {
		$values = Appearance::sanitize(
			array(
				'accent_color'       => 'url(javascript:alert(1))',
				'chat_width'         => 99999,
				'chat_border_radius' => 999,
				'chat_font_size'     => 1,
				'messages_height'    => 9999,
				'launcher_size'      => 1,
				'shadow_opacity'     => 999,
				'font_family'        => 'javascript',
				'global_position'    => 'top_center',
			)
		);

		$this->assertSame( '#2563eb', $values['accent_color'] );
		$this->assertSame( 640, $values['chat_width'] );
		$this->assertSame( 40, $values['chat_border_radius'] );
		$this->assertSame( 12, $values['chat_font_size'] );
		$this->assertSame( 640, $values['messages_height'] );
		$this->assertSame( 44, $values['launcher_size'] );
		$this->assertSame( 40, $values['shadow_opacity'] );
		$this->assertSame( 'system', $values['font_family'] );
		$this->assertSame( 'bottom_right', $values['global_position'] );
	}

	public function test_lead_field_boundaries_and_honeypot(): void {
		$reflection = new ReflectionClass( LeadController::class );
		$controller = $reflection->newInstanceWithoutConstructor();

		$this->assertTrue( $controller->validate_name( str_repeat( 'a', 100 ) ) );
		$this->assertFalse( $controller->validate_name( str_repeat( 'a', 101 ) ) );
		$this->assertTrue( $controller->validate_honeypot( '' ) );
		$this->assertFalse( $controller->validate_honeypot( 'https://spam.test' ) );
		$this->assertFalse( $controller->validate_session( str_repeat( 'a', 1025 ) . '.' ) );
	}

	public function test_deepseek_request_uses_chat_completions_without_exposing_reasoning(): void {
		$provider   = new DeepSeekProvider( new CredentialResolver() );
		$reflection = new ReflectionClass( $provider );
		$body       = $reflection->getMethod( 'request_body' );
		$output     = $reflection->getMethod( 'extract_output_text' );
		$options    = array(
			'deepseek_model'    => 'deepseek-v4-flash',
			'deepseek_thinking' => false,
			'ai_instructions'   => 'Use verified website knowledge.',
			'ai_max_output_tokens' => 1200,
		);

		if ( PHP_VERSION_ID < 80100 ) {
			$body->setAccessible( true );
			$output->setAccessible( true );
		}

		$request = $body->invoke( $provider, 'Where are you located?', 'session-id', $options );

		$this->assertSame( 'deepseek-v4-flash', $request['model'] );
		$this->assertSame( 'disabled', $request['thinking']['type'] );
		$this->assertFalse( $request['stream'] );
		$this->assertSame( 'Use verified website knowledge.', $request['messages'][0]['content'] );
		$this->assertSame( 'Where are you located?', $request['messages'][1]['content'] );
		$this->assertSame(
			'Final answer',
			$output->invoke(
				$provider,
				array(
					'choices' => array(
						array(
							'message' => array(
								'content'           => 'Final answer',
								'reasoning_content' => 'Private reasoning',
							),
						),
					),
				)
			)
		);
	}

	public function test_blank_api_key_submission_preserves_saved_key(): void {
		$saved_key = str_repeat( 'x', 32 );
		$GLOBALS['wpdsac_test_options']['wpdsac_deepseek_api_key'] = $saved_key;

		$settings = new Settings();

		$this->assertSame(
			$saved_key,
			$settings->sanitize_api_key( '', 'deepseek' )
		);
		$this->assertContains( 'deepseek', CredentialResolver::provider_ids() );
	}

	public function test_administrative_label_uses_current_plugin_version(): void {
		$this->assertSame( 'DS AI Chatbot v0.5.12', PluginInfo::versioned_label( 'DS AI Chatbot' ) );
	}

	public function test_plugins_screen_name_contains_current_version(): void {
		$plugin_file = 'wp-ds-aichatbot/wp-ds-aichatbot.php';
		$plugins     = ( new PluginList() )->append_version(
			array(
				$plugin_file => array(
					'Name'  => 'WP DS AI Chatbot',
					'Title' => 'WP DS AI Chatbot',
				),
			)
		);

		$this->assertSame( 'WP DS AI Chatbot v0.5.12', $plugins[ $plugin_file ]['Name'] );
		$this->assertSame( 'WP DS AI Chatbot v0.5.12', $plugins[ $plugin_file ]['Title'] );
	}
}
