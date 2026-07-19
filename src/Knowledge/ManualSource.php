<?php
/**
 * Administrator-authored knowledge source.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Persist and index bounded free-form website knowledge.
 */
final class ManualSource {

	public const OPTION_NAME = 'wpdsac_manual_knowledge';

	private const SOURCE_ID = 1;

	/**
	 * Knowledge repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Text chunker.
	 *
	 * @var Chunker
	 */
	private $chunker;

	/**
	 * Store source dependencies.
	 *
	 * @param Repository $repository Knowledge repository.
	 * @param Chunker    $chunker    Text chunker.
	 */
	public function __construct( Repository $repository, Chunker $chunker ) {
		$this->repository = $repository;
		$this->chunker    = $chunker;
	}

	/**
	 * Return the saved administrator text.
	 *
	 * @return string
	 */
	public function content(): string {
		$content = get_option( self::OPTION_NAME, '' );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Sanitize, store, and index administrator-authored knowledge.
	 *
	 * @param mixed $content Submitted text.
	 * @return int Number of stored fragments.
	 */
	public function save( $content ): int {
		$content = is_string( $content ) ? sanitize_textarea_field( $content ) : '';
		$content = $this->limit( $content, 50000 );

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, '', '', false );
		}

		update_option( self::OPTION_NAME, $content, false );

		return $this->reindex();
	}

	/**
	 * Rebuild this source in the shared knowledge index.
	 *
	 * @return int Number of stored fragments.
	 */
	public function reindex(): int {
		$content = $this->content();

		if ( '' === trim( $content ) ) {
			$this->repository->delete_source( 'manual', self::SOURCE_ID );
			return 0;
		}

		return $this->repository->replace_source(
			'manual',
			self::SOURCE_ID,
			__( 'Additional website knowledge', 'wp-ds-aichatbot' ),
			'',
			$this->chunker->split( $content )
		);
	}

	/**
	 * Limit text without splitting UTF-8 characters.
	 *
	 * @param string $content Sanitized text.
	 * @param int    $length  Maximum characters.
	 * @return string
	 */
	private function limit( string $content, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $content, 0, $length, 'UTF-8' ) : substr( $content, 0, $length );
	}
}
