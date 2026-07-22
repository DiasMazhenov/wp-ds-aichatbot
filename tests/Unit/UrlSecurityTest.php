<?php
/**
 * Security-focused URL navigation tests.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Tests\Unit;

use DiasMazhenov\WPDsAiChatbot\Security\UrlDenylist;
use PHPUnit\Framework\TestCase;

class UrlSecurityTest extends TestCase {

	public function test_admin_urls_blocked(): void {
		$blocked = array(
			'https://example.com/wp-admin/',
			'https://example.com/wp-admin/plugins.php',
			'https://example.com/wp-admin/admin.php?page=test',
			'https://example.com/wp-login.php',
			'https://example.com/wp-admin/admin-ajax.php',
			'https://example.com/wp-admin/admin-post.php',
		);

		foreach ( $blocked as $url ) {
			$this->assertTrue(
				UrlDenylist::is_blocked( $url ),
				"URL must be blocked: {$url}"
			);
		}
	}

	public function test_public_urls_allowed(): void {
		$allowed = array(
			'https://example.com/services/',
			'https://example.com/prices/',
			'https://example.com/#contacts',
			'https://example.com/about/',
			'https://example.com/shop/product-name/',
		);

		foreach ( $allowed as $url ) {
			$this->assertFalse(
				UrlDenylist::is_blocked( $url ),
				"URL must be allowed: {$url}"
			);
		}
	}

	public function test_encoded_admin_urls_blocked(): void {
		$blocked = array(
			'https://example.com/wp-admin%2Fplugins.php',
			'https://example.com/wp-admin%252Fplugins.php',
			'https://example.com/wp-admin%25252Fplugins.php',
		);

		foreach ( $blocked as $url ) {
			$this->assertTrue(
				UrlDenylist::is_blocked( $url ),
				"Encoded admin URL must be blocked: {$url}"
			);
		}
	}

	public function test_dot_segments_blocked(): void {
		$this->assertTrue(
			UrlDenylist::is_blocked( 'https://example.com/public/../wp-admin/' ),
			'Dot-segment to admin must be blocked.'
		);
	}

	public function test_backslash_blocked(): void {
		$this->assertTrue(
			UrlDenylist::is_blocked( 'https://example.com/wp-admin\\plugins.php' ),
			'Backslash in admin path must be blocked.'
		);
	}

	public function test_wp_json_blocked(): void {
		$this->assertTrue(
			UrlDenylist::is_blocked( 'https://example.com/wp-json/wp/v2/posts' ),
			'WP JSON endpoint must be blocked.'
		);
	}

	public function test_cron_and_xmlrpc_blocked(): void {
		$this->assertTrue( UrlDenylist::is_blocked( 'https://example.com/wp-cron.php' ) );
		$this->assertTrue( UrlDenylist::is_blocked( 'https://example.com/xmlrpc.php' ) );
	}

	public function test_case_insensitive(): void {
		$this->assertTrue( UrlDenylist::is_blocked( 'https://example.com/WP-ADMIN/' ) );
		$this->assertTrue( UrlDenylist::is_blocked( 'https://example.com/Wp-Admin/plugins.php' ) );
	}

	public function test_repeated_slashes(): void {
		$this->assertTrue( UrlDenylist::is_blocked( 'https://example.com//wp-admin//plugins.php' ) );
	}

	public function test_public_elementor_url(): void {
		$this->assertFalse(
			UrlDenylist::is_blocked( 'https://example.com/elementor-page/#section-id' ),
			'Elementor public block must be allowed.'
		);
	}

	public function test_lead_action_hash(): void {
		$this->assertFalse(
			UrlDenylist::is_blocked( 'https://example.com/#wpdsac-contact-form' ),
			'Lead form hash must not be blocked (hash, not path).'
		);
	}

	public function test_query_params_on_admin_blocked(): void {
		$this->assertTrue(
			UrlDenylist::is_blocked( 'https://example.com/wp-admin/?page=test&action=edit' ),
			'Admin URL with query params must be blocked.'
		);
	}

	public function test_public_with_query_allowed(): void {
		$this->assertFalse(
			UrlDenylist::is_blocked( 'https://example.com/services/?utm_source=google' ),
			'Public URL with query must be allowed.'
		);
	}
}
