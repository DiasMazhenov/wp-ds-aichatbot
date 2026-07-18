<?php
/**
 * Explicitly selected PDF knowledge source.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Extract bounded text from administrator-selected Media Library PDFs.
 */
final class PdfIndexer {

	public const OPTION_NAME = 'wpdsac_pdf_attachment_ids';

	private const MAX_FILES = 50;

	private const MAX_FILE_BYTES = 10485760;

	private const MAX_TEXT_CHARS = 1000000;

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
	 * Store dependencies.
	 *
	 * @param Repository $repository Knowledge repository.
	 * @param Chunker    $chunker    Text chunker.
	 */
	public function __construct( Repository $repository, Chunker $chunker ) {
		$this->repository = $repository;
		$this->chunker    = $chunker;
	}

	/**
	 * Register attachment cleanup.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );
	}

	/**
	 * Return selected PDF attachment IDs.
	 *
	 * @return array<int, int>
	 */
	public function selected_ids(): array {
		$value = get_option( self::OPTION_NAME, array() );

		return $this->normalize_ids( is_array( $value ) ? $value : array() );
	}

	/**
	 * Replace the selected source set and rebuild its index.
	 *
	 * @param array<int, mixed> $attachment_ids Raw attachment IDs.
	 * @return array{indexed: int, failed: int}
	 */
	public function save_selection( array $attachment_ids ): array {
		$selected = $this->normalize_ids( $attachment_ids );
		$previous = $this->selected_ids();

		foreach ( array_diff( $previous, $selected ) as $attachment_id ) {
			$this->repository->delete_source( 'pdf', $attachment_id );
		}

		$indexed = 0;
		$failed  = 0;
		$valid   = array();

		foreach ( $selected as $attachment_id ) {
			if ( $this->index_attachment( $attachment_id ) > 0 ) {
				$valid[] = $attachment_id;
				++$indexed;
			} else {
				$this->repository->delete_source( 'pdf', $attachment_id );
				++$failed;
			}
		}

		update_option( self::OPTION_NAME, $valid, false );

		return array(
			'indexed' => $indexed,
			'failed'  => $failed,
		);
	}

	/**
	 * Rebuild all selected PDFs.
	 *
	 * @return int Number of indexed documents.
	 */
	public function reindex_selected(): int {
		$result = $this->save_selection( $this->selected_ids() );

		return $result['indexed'];
	}

	/**
	 * Extract and index one Media Library PDF.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int Number of stored chunks.
	 */
	public function index_attachment( int $attachment_id ): int {
		$attachment_id = absint( $attachment_id );

		if ( 0 === $attachment_id || 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
			return 0;
		}

		$file = get_attached_file( $attachment_id, true );

		if ( ! is_string( $file ) || ! $this->safe_upload_path( $file ) || filesize( $file ) > self::MAX_FILE_BYTES ) {
			return 0;
		}

		if ( ! class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			return 0;
		}

		try {
			$document = ( new \Smalot\PdfParser\Parser() )->parseFile( $file );
			$text     = $document->getText();
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return 0;
		}

		$text = is_string( $text ) ? self::slice( $text, self::MAX_TEXT_CHARS ) : '';

		if ( '' === trim( $text ) ) {
			return 0;
		}

		$title = get_the_title( $attachment_id );
		$title = is_string( $title ) && '' !== $title ? $title : __( 'PDF document', 'wp-ds-aichatbot' );

		return $this->repository->replace_source(
			'pdf',
			$attachment_id,
			$title,
			'',
			$this->chunker->split( $text )
		);
	}

	/**
	 * Remove deleted attachment state and fragments.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function delete_attachment( int $attachment_id ): void {
		$this->repository->delete_source( 'pdf', $attachment_id );
		update_option(
			self::OPTION_NAME,
			array_values( array_diff( $this->selected_ids(), array( absint( $attachment_id ) ) ) ),
			false
		);
	}

	/**
	 * Accept only readable files located below the WordPress uploads directory.
	 *
	 * @param string $file Candidate path.
	 * @return bool
	 */
	private function safe_upload_path( string $file ): bool {
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		$path    = realpath( $file );

		if ( false === $base || false === $path || ! is_readable( $path ) || ! is_file( $path ) ) {
			return false;
		}

		$base = trailingslashit( wp_normalize_path( $base ) );
		$path = wp_normalize_path( $path );

		return 0 === strpos( $path, $base );
	}

	/**
	 * Normalize, deduplicate, and bound attachment IDs.
	 *
	 * @param array<int, mixed> $values Raw IDs.
	 * @return array<int, int>
	 */
	private function normalize_ids( array $values ): array {
		$values = array_map( 'absint', $values );
		$values = array_filter( array_unique( $values ) );

		return array_slice( array_values( $values ), 0, self::MAX_FILES );
	}

	/**
	 * Bound extracted Unicode text.
	 *
	 * @param string $value  Extracted text.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private static function slice( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
