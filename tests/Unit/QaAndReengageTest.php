<?php
/**
 * Unit tests for QuickReplyParser and ReengageService.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\AI\QuickReplyParser;
use DiasMazhenov\WPDsAiChatbot\AI\ReengageService;
use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;
use PHPUnit\Framework\TestCase;

final class QaAndReengageTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpdsac_test_transients'] = array();
	}

	public function test_parser_returns_clean_reply_without_markers(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse( 'Hello, how can I help?' );

		$this->assertSame( 'Hello, how can I help?', $result['reply'] );
		$this->assertEmpty( $result['quick_replies'] );
	}

	public function test_parser_extracts_valid_qa_markers(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse(
			"Which service?\n\n[[WPDSAC_QA|Landing|message|I need a landing page]]\n[[WPDSAC_QA|Shop|message|I need an online store]]"
		);

		$this->assertSame( 'Which service?', trim( $result['reply'] ) );
		$this->assertCount( 2, $result['quick_replies'] );
		$this->assertSame( 'Landing', $result['quick_replies'][0]['label'] );
		$this->assertSame( 'I need a landing page', $result['quick_replies'][0]['message'] );
	}

	public function test_parser_fallback_when_reply_empty_after_markers(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse(
			"[[WPDSAC_QA|Yes|message|Yes, please]]\n[[WPDSAC_QA|No|message|No, thanks]]"
		);

		$this->assertSame( 'Choose an option:', $result['reply'] );
		$this->assertCount( 2, $result['quick_replies'] );
	}

	public function test_parser_rejects_single_qa_variant(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse( "Choose:\n\n[[WPDSAC_QA|Yes|message|Yes, please]]" );

		$this->assertNotEmpty( $result['reply'] );
		$this->assertEmpty( $result['quick_replies'] );
	}

	public function test_parser_caps_generated_variants_at_four(): void {
		$parser = new QuickReplyParser();
		$markers = '';
		for ( $i = 1; $i <= 7; $i++ ) {
			$markers .= "[[WPDSAC_QA|Opt{$i}|message|Option {$i} text]]\n";
		}
		$result = $parser->parse( "Pick:\n\n{$markers}" );

		$this->assertCount( 4, $result['quick_replies'] );
		$this->assertSame( 'Opt4', $result['quick_replies'][3]['label'] );
	}

	public function test_parser_strips_html_from_markers(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse(
			"[[WPDSAC_QA|<b>Title</b>|message|Visit <a href='#'>us</a> now]]\n[[WPDSAC_QA|Normal|message|Normal text]]"
		);

		$this->assertCount( 2, $result['quick_replies'] );
		$this->assertSame( 'Title', $result['quick_replies'][0]['label'] );
		$this->assertSame( 'Visit us now', $result['quick_replies'][0]['message'] );
	}

	public function test_parser_drops_malformed_markers(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse(
			"[[WPDSAC_QA]]\n[[WPDSAC_QA|Only Label]]\n[[WPDSAC_QA|Label|message|Good]]\n[[WPDSAC_QA|B|message|Better]]"
		);

		$this->assertCount( 2, $result['quick_replies'] );
		$this->assertStringNotContainsString( 'WPDSAC_QA', $result['reply'] );
	}

	public function test_parser_deduplicates_identical_choices(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse(
			"Choose:\n\n[[WPDSAC_QA|Site|message|I need a website.]]\n[[WPDSAC_QA|Site|message|I need a website.]]\n[[WPDSAC_QA|Support|message|I need support.]]"
		);

		$this->assertCount( 2, $result['quick_replies'] );
		$this->assertSame( 'Support', $result['quick_replies'][1]['label'] );
	}

	public function test_parser_removes_malformed_marker_without_valid_choices(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse( "Please choose.\n[[WPDSAC_QA|Broken]]" );

		$this->assertSame( 'Please choose.', $result['reply'] );
		$this->assertEmpty( $result['quick_replies'] );
	}

	public function test_parser_bounds_label_and_message_length(): void {
		$parser = new QuickReplyParser();
		$long_label   = str_repeat( 'A', 200 );
		$long_message = str_repeat( 'B', 600 );

		$result = $parser->parse(
			"[[WPDSAC_QA|{$long_label}|message|{$long_message}]]\n[[WPDSAC_QA|Short|message|Text]]"
		);

		$this->assertCount( 2, $result['quick_replies'] );
		$this->assertLessThanOrEqual( 80, strlen( $result['quick_replies'][0]['label'] ) );
		$this->assertLessThanOrEqual( 500, strlen( $result['quick_replies'][0]['message'] ) );
	}

	public function test_parser_rejects_non_message_action_type(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse(
			"[[WPDSAC_QA|Link|url|https://example.com]]\n[[WPDSAC_QA|Ok|message|Ok, continue]]"
		);

		$this->assertEmpty( $result['quick_replies'] );
	}

	public function test_parser_cleans_empty_lines_after_marker_removal(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse(
			"Intro text\n\n[[WPDSAC_QA|A|message|Option A]]\n[[WPDSAC_QA|B|message|Option B]]"
		);

		$this->assertCount( 2, $result['quick_replies'] );
		$this->assertStringContainsString( 'Intro text', $result['reply'] );
		$this->assertStringNotContainsString( '[[WPDSAC_QA', $result['reply'] );
	}

	public function test_parser_does_not_return_raw_wpdsac_markers(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse(
			"[[WPDSAC_QA|A|message|Opt A]]\n[[WPDSAC_QA|B|message|Opt B]]"
		);

		$this->assertStringNotContainsString( 'WPDSAC_QA', $result['reply'] );
		$this->assertCount( 2, $result['quick_replies'] );
	}

	public function test_reengage_guard_disabled_when_setting_off(): void {
		$service = new ReengageService();
		$result  = $service->guard(
			wp_generate_uuid4(),
			array( 'reengage_enabled' => false, 'reengage_max_count' => 3 )
		);

		$this->assertFalse( $result['allowed'] );
		$this->assertSame( 'disabled', $result['reason'] );
	}

	public function test_reengage_guard_requires_server_activity(): void {
		$service = new ReengageService();
		$result  = $service->guard(
			wp_generate_uuid4(),
			array( 'reengage_enabled' => true, 'reengage_max_count' => 3 )
		);

		$this->assertFalse( $result['allowed'] );
		$this->assertSame( 'no_conversation', $result['reason'] );
	}

	public function test_reengage_guard_allows_after_activity(): void {
		$service = new ReengageService();
		$session = wp_generate_uuid4();

		$service->mark_activity( $session );
		$result = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 3 )
		);

		$this->assertTrue( $result['allowed'] );
		$this->assertSame( 0, $result['count'] );
		$this->assertSame( 3, $result['max_count'] );
	}

	public function test_reengage_count_increments_only_after_success(): void {
		$service = new ReengageService();
		$session = 'count-test-' . uniqid();
		$session_activity = hash_hmac('sha256', 'reengage:' . $session, wp_salt('auth'));

		$service->mark_activity( $session );
		$GLOBALS['wpdsac_test_transients']['wpdsac_reengage_active_' . $session_activity] = time();

		$before = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 3 )
		);
		$this->assertSame( 0, $before['count'] );

		$service->start_cooldown( $session );
		$after_cooldown = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 3 )
		);
		$this->assertSame( 0, $after_cooldown['count'] );

		$service->increment_count( $session );
		$after_increment = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 3 )
		);
		$this->assertSame( 1, $after_increment['count'] );
	}

	public function test_reengage_max_count_1_allows_exactly_one_success(): void {
		$service = new ReengageService();
		$session = 'max1-test-' . uniqid();
		$session_activity = hash_hmac('sha256', 'reengage:' . $session, wp_salt('auth'));

		$service->mark_activity( $session );
		$GLOBALS['wpdsac_test_transients']['wpdsac_reengage_active_' . $session_activity] = time();

		$first = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 1 )
		);
		$this->assertTrue( $first['allowed'] );
		$this->assertSame( 0, $first['count'] );

		$service->increment_count( $session );

		$second = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 1 )
		);
		$this->assertFalse( $second['allowed'] );
		$this->assertSame( 'max_reached', $second['reason'] );
	}

	public function test_reengage_guard_blocked_by_cooldown(): void {
		$service = new ReengageService();
		$session = 'cooldown-test-' . uniqid();
		$session_activity = hash_hmac('sha256', 'reengage:' . $session, wp_salt('auth'));

		$service->mark_activity( $session );
		$GLOBALS['wpdsac_test_transients']['wpdsac_reengage_active_' . $session_activity] = time();

		$service->start_cooldown( $session );
		$result = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 2 )
		);

		$this->assertFalse( $result['allowed'] );
		$this->assertSame( 'cooldown', $result['reason'] );
		$this->assertGreaterThan( 0, $result['retry_after'] );
	}

	public function test_reengage_guard_respects_max_count(): void {
		$service = new ReengageService();
		$session = 'max-count-test-' . uniqid();
		$session_activity = hash_hmac('sha256', 'reengage:' . $session, wp_salt('auth'));

		$service->mark_activity( $session );
		$GLOBALS['wpdsac_test_transients']['wpdsac_reengage_active_' . $session_activity] = time();

		$first = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 2 )
		);
		$this->assertTrue( $first['allowed'] );
		$this->assertSame( 0, $first['count'] );

		$service->increment_count( $session );

		$second = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 2 )
		);
		$this->assertTrue( $second['allowed'] );
		$this->assertSame( 1, $second['count'] );

		$service->increment_count( $session );

		$third = $service->guard(
			$session,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 2 )
		);
		$this->assertFalse( $third['allowed'] );
		$this->assertSame( 'max_reached', $third['reason'] );
	}

	public function test_reengage_build_prompt_with_custom_instructions(): void {
		$service = new ReengageService();
		$prompt  = $service->build_prompt(
			array(),
			array( 'reengage_instructions' => 'Custom follow-up prompt text.' )
		);

		$this->assertSame( 'Custom follow-up prompt text.', $prompt );
	}

	public function test_reengage_build_prompt_default(): void {
		$service = new ReengageService();
		$prompt  = $service->build_prompt( array(), array( 'reengage_instructions' => '' ) );

		$this->assertNotEmpty( $prompt );
		$this->assertStringContainsString( 'silent', $prompt );
	}

	public function test_reengage_max_count_zero_disabled(): void {
		$service = new ReengageService();
		$result  = $service->guard(
			wp_generate_uuid4(),
			array( 'reengage_enabled' => true, 'reengage_max_count' => 0 )
		);

		$this->assertFalse( $result['allowed'] );
		$this->assertSame( 'disabled', $result['reason'] );
	}
}
