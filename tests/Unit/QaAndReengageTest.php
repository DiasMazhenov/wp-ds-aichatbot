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
		$this->assertSame( 'Shop', $result['quick_replies'][1]['label'] );
		$this->assertSame( 'I need an online store', $result['quick_replies'][1]['message'] );
	}

	public function test_parser_rejects_single_qa_variant(): void {
		$parser = new QuickReplyParser();
		$result = $parser->parse( "Choose:\n\n[[WPDSAC_QA|Yes|message|Yes, please]]" );

		$this->assertNotEmpty( $result['reply'] );
		$this->assertEmpty( $result['quick_replies'] );
	}

	public function test_parser_rejects_more_than_5_variants(): void {
		$parser = new QuickReplyParser();
		$markers = '';
		for ( $i = 1; $i <= 7; $i++ ) {
			$markers .= "[[WPDSAC_QA|Opt{$i}|message|Option {$i} text]]\n";
		}
		$result = $parser->parse( "Pick:\n\n{$markers}" );

		$this->assertEmpty( $result['quick_replies'] );
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
		$this->assertSame( 'Label', $result['quick_replies'][0]['label'] );
		$this->assertSame( 'B', $result['quick_replies'][1]['label'] );
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
			"Intro text\n\n[[WPDSAC_QA|A|message|Option A]]\n[[WPDSAC_QA|B|message|Option B]]\n\nEnd text"
		);

		$this->assertCount( 2, $result['quick_replies'] );
		$this->assertStringContainsString( 'Intro text', $result['reply'] );
		$this->assertStringContainsString( 'End text', $result['reply'] );
		$this->assertStringNotContainsString( '[[WPDSAC_QA', $result['reply'] );
	}

	public function test_reengage_guard_disabled_when_setting_off(): void {
		$service = new ReengageService();
		$history = array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		);
		$result = $service->guard(
			wp_generate_uuid4(),
			$history,
			array( 'reengage_enabled' => false, 'reengage_max_count' => 3 )
		);

		$this->assertFalse( $result['allowed'] );
	}

	public function test_reengage_guard_requires_user_message(): void {
		$service = new ReengageService();
		$result = $service->guard(
			wp_generate_uuid4(),
			array(
				array( 'role' => 'assistant', 'content' => 'Hi' ),
			),
			array( 'reengage_enabled' => true, 'reengage_max_count' => 3 )
		);

		$this->assertFalse( $result['allowed'] );
	}

	public function test_reengage_guard_allows_valid_first_attempt(): void {
		$service = new ReengageService();
		$history = array(
			array( 'role' => 'user', 'content' => 'Hello' ),
			array( 'role' => 'assistant', 'content' => 'Hi there' ),
		);
		$result = $service->guard(
			wp_generate_uuid4(),
			$history,
			array( 'reengage_enabled' => true, 'reengage_max_count' => 3 )
		);

		$this->assertTrue( $result['allowed'] );
		$this->assertSame( 0, $result['count'] );
		$this->assertSame( 3, $result['max_count'] );
	}

	public function test_reengage_guard_blocked_by_cooldown(): void {
		$service  = new ReengageService();
		$session  = 'cooldown-test-' . uniqid();
		$history  = array(
			array( 'role' => 'user', 'content' => 'Test' ),
		);
		$options  = array( 'reengage_enabled' => true, 'reengage_max_count' => 2 );

		$first = $service->guard( $session, $history, $options );
		$this->assertTrue( $first['allowed'] );
		$service->record_attempt( $session, $first );

		$second = $service->guard( $session, $history, $options );
		$this->assertFalse( $second['allowed'] );
	}

	public function test_reengage_guard_respects_max_count(): void {
		$service = new ReengageService();
		$session = 'max-count-test-' . uniqid();
		$history = array(
			array( 'role' => 'user', 'content' => 'Test' ),
		);
		$options = array( 'reengage_enabled' => true, 'reengage_max_count' => 1 );

		$first = $service->guard( $session, $history, $options );
		$this->assertTrue( $first['allowed'] );
		$this->assertSame( 0, $first['count'] );
		$service->record_attempt( $session, $first );

		$second = $service->guard( $session, $history, $options );
		$this->assertFalse( $second['allowed'] );
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
			array( array( 'role' => 'user', 'content' => 'Hi' ) ),
			array( 'reengage_enabled' => true, 'reengage_max_count' => 0 )
		);

		$this->assertFalse( $result['allowed'] );
	}
}
