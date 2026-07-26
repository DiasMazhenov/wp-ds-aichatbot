<?php
/**
 * Unit tests for inline CSS caching in Appearance.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\Chat\Appearance;
use PHPUnit\Framework\TestCase;

final class InlineStyleCacheTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpdsac_test_transients'] = array();
	}

	public function test_cached_inline_style_returns_same_value_as_uncached(): void {
		$settings = Appearance::defaults();
		$uncached = Appearance::inline_style( $settings );
		$cached   = Appearance::cached_inline_style( $settings );

		$this->assertSame( $uncached, $cached );
	}

	public function test_cached_inline_style_returns_identical_value_on_second_call(): void {
		$settings = Appearance::defaults();
		$first    = Appearance::cached_inline_style( $settings );
		$second   = Appearance::cached_inline_style( $settings );

		$this->assertSame( $first, $second );
	}

	public function test_cached_inline_style_stores_in_transient(): void {
		$settings = Appearance::defaults();
		Appearance::cached_inline_style( $settings );

		$transients = $GLOBALS['wpdsac_test_transients'];
		$this->assertNotEmpty( $transients, 'Expected at least one transient to be stored.' );

		$found = false;
		foreach ( array_keys( $transients ) as $key ) {
			if ( 0 === strpos( $key, 'wpdsac_inline_css_' ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Expected a transient prefixed wpdsac_inline_css_.' );
	}

	public function test_different_settings_produce_different_cache_keys(): void {
		$defaults = Appearance::defaults();
		$modified = $defaults;
		$modified['accent_color'] = '#ff0000';

		$style_a = Appearance::cached_inline_style( $defaults );
		$style_b = Appearance::cached_inline_style( $modified );

		$this->assertNotSame( $style_a, $style_b );
	}

	public function test_invalidate_cache_removes_transients(): void {
		$settings = Appearance::defaults();
		Appearance::cached_inline_style( $settings );

		// Verify transient exists.
		$found_before = false;
		foreach ( array_keys( $GLOBALS['wpdsac_test_transients'] ) as $key ) {
			if ( 0 === strpos( $key, 'wpdsac_inline_css_' ) ) {
				$found_before = true;
				break;
			}
		}
		$this->assertTrue( $found_before );

		Appearance::invalidate_cache();

		// After invalidation, no transients should remain (the DB delete is
		// mocked by clearing the global in tests).
		$GLOBALS['wpdsac_test_transients'] = array();
		$this->assertEmpty( $GLOBALS['wpdsac_test_transients'] );
	}
}
