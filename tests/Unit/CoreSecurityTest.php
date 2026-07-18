<?php
/**
 * Pure security and boundary tests.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\Api\LeadController;
use DiasMazhenov\WPDsAiChatbot\Api\SessionToken;
use DiasMazhenov\WPDsAiChatbot\Chat\Appearance;
use DiasMazhenov\WPDsAiChatbot\Knowledge\Chunker;
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
				'global_position'    => 'top_center',
			)
		);

		$this->assertSame( '#2563eb', $values['accent_color'] );
		$this->assertSame( 640, $values['chat_width'] );
		$this->assertSame( 40, $values['chat_border_radius'] );
		$this->assertSame( 12, $values['chat_font_size'] );
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
}
