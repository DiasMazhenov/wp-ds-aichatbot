<?php
/**
 * Unit tests for the incremental SSE frame parser.
 *
 * @package WPDsAiChatbotTests
 */

use DiasMazhenov\WPDsAiChatbot\AI\SseFrameParser;
use DiasMazhenov\WPDsAiChatbot\AI\DeepSeekProvider;
use PHPUnit\Framework\TestCase;

final class SseFrameParserTest extends TestCase {

	public function test_preserves_frame_split_across_transport_chunks(): void {
		$parser = new SseFrameParser();

		$this->assertSame( array(), $parser->push( "event: delta\n" ) );
		$this->assertSame(
			array( "event: delta\ndata: {\"content\":\"Hello\"}" ),
			$parser->push( "data: {\"content\":\"Hello\"}\n\n" )
		);
	}

	public function test_extracts_multiple_crlf_frames_from_one_chunk(): void {
		$parser = new SseFrameParser();
		$frames = $parser->push(
			"event: delta\r\ndata: {\"content\":\"One\"}\r\n\r\nevent: done\r\ndata: {\"reply\":\"One\"}\r\n\r\n"
		);

		$this->assertCount( 2, $frames );
		$this->assertStringContainsString( 'event: delta', $frames[0] );
		$this->assertStringContainsString( 'event: done', $frames[1] );
	}

	public function test_finish_returns_unterminated_tail_once(): void {
		$parser = new SseFrameParser();

		$parser->push( "data: [DONE]\n" );

		$this->assertSame( array( 'data: [DONE]' ), $parser->finish() );
		$this->assertSame( array(), $parser->finish() );
	}

	public function test_deepseek_stream_exposes_answer_but_not_internal_reasoning(): void {
		$content_frame = 'data: {"choices":[{"delta":{"content":"Final answer"}}]}';
		$reasoning_frame = 'data: {"choices":[{"delta":{"reasoning_content":"Private reasoning"}}]}';

		$this->assertSame( 'Final answer', $this->extract_delta( DeepSeekProvider::class, $content_frame ) );
		$this->assertSame( '', $this->extract_delta( DeepSeekProvider::class, $reasoning_frame ) );
	}

	/**
	 * Invoke a protected provider frame extractor for a focused parser test.
	 *
	 * @param class-string $provider_class Provider class.
	 * @param string       $frame          Complete SSE frame.
	 * @return string
	 */
	private function extract_delta( string $provider_class, string $frame ): string {
		$method = new ReflectionMethod( $provider_class, 'extract_stream_delta' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return (string) $method->invoke( null, $frame );
	}
}
