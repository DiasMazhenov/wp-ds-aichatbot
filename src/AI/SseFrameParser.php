<?php
/**
 * Incremental Server-Sent Events frame parser.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Preserve incomplete SSE frames between arbitrary transport chunks.
 */
final class SseFrameParser {

	/**
	 * Unconsumed transport data.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * Append a transport chunk and return every complete SSE frame.
	 *
	 * @param string $chunk Raw transport chunk.
	 * @return array<int, string>
	 */
	public function push( string $chunk ): array {
		if ( '' !== $chunk ) {
			$this->buffer .= $chunk;
		}

		return $this->extract_complete_frames();
	}

	/**
	 * Return the final non-empty frame when a stream closes without a separator.
	 *
	 * @return array<int, string>
	 */
	public function finish(): array {
		$frames = $this->extract_complete_frames();
		$tail   = trim( $this->buffer );

		$this->buffer = '';

		if ( '' !== $tail ) {
			$frames[] = $tail;
		}

		return $frames;
	}

	/**
	 * Remove complete CRLF or LF-delimited frames from the internal buffer.
	 *
	 * @return array<int, string>
	 */
	private function extract_complete_frames(): array {
		$frames = array();

		while ( preg_match( '/\r?\n\r?\n/', $this->buffer, $match, PREG_OFFSET_CAPTURE ) ) {
			$separator = (string) $match[0][0];
			$offset    = (int) $match[0][1];
			$frame     = substr( $this->buffer, 0, $offset );

			$this->buffer = (string) substr( $this->buffer, $offset + strlen( $separator ) );

			if ( '' !== trim( $frame ) ) {
				$frames[] = $frame;
			}
		}

		return $frames;
	}
}
