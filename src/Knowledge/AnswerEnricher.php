<?php
/**
 * Deterministic knowledge links and contact details for AI replies.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Ensure requested source links and contacts are not lost in model output.
 */
final class AnswerEnricher {

	/**
	 * Knowledge repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Contact source.
	 *
	 * @var ContactSource
	 */
	private $contacts;

	/**
	 * Store enrichment dependencies.
	 *
	 * @param Repository    $repository Knowledge repository.
	 * @param ContactSource $contacts   Contact source.
	 */
	public function __construct( Repository $repository, ContactSource $contacts ) {
		$this->repository = $repository;
		$this->contacts   = $contacts;
	}

	/**
	 * Register after the provider reply filter.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wpdsac_chat_reply', array( $this, 'enrich' ), 20, 4 );
	}

	/**
	 * Append exact requested data when the provider omitted it.
	 *
	 * @param mixed            $reply      Provider reply.
	 * @param string           $message    Visitor message.
	 * @param string           $session_id Verified session UUID.
	 * @param \WP_REST_Request $request    REST request.
	 * @return mixed
	 */
	public function enrich( $reply, string $message, string $session_id, \WP_REST_Request $request ) {
		unset( $session_id, $request );

		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			return $reply;
		}

		$append = array();

		if ( $this->is_link_request( $message ) ) {
			foreach ( $this->repository->search( $message, 4 ) as $source ) {
				$url = esc_url_raw( $source['source_url'], array( 'http', 'https' ) );

				if ( '' !== $url && false === strpos( $reply, $url ) ) {
					$append[] = $url;
					break;
				}
			}
		}

		if ( $this->is_contact_request( $message ) ) {
			foreach ( $this->contacts->answer_lines() as $line ) {
				if ( false === strpos( $reply, $line ) ) {
					$append[] = $line;
				}
			}
		}

		return array() === $append ? $reply : rtrim( $reply ) . "\n\n" . implode( "\n", $append );
	}

	/**
	 * Detect an explicit request for a page or URL.
	 *
	 * @param string $message Visitor message.
	 * @return bool
	 */
	private function is_link_request( string $message ): bool {
		return 1 === preg_match( '/\b(link|url|page|website|ссылк|страниц|сайт)\p{L}*\b/iu', $message );
	}

	/**
	 * Detect an explicit contact request.
	 *
	 * @param string $message Visitor message.
	 * @return bool
	 */
	private function is_contact_request( string $message ): bool {
		return 1 === preg_match( '/\b(contact|call|phone|telephone|whatsapp|telegram|manager|связаться|контакт|позвонить|телефон|номер|ватсап|вотсап|телеграм|менеджер|консультация)\b/iu', $message );
	}
}
