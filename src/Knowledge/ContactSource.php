<?php
/**
 * Administrator-managed contact knowledge source.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Persist contact fields and expose them through the shared knowledge index.
 */
final class ContactSource {

	public const OPTION_NAME = 'wpdsac_contact_information';

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
	 * Return sanitized contact fields.
	 *
	 * @return array{phone: string, whatsapp: string, telegram: string}
	 */
	public function fields(): array {
		$fields = get_option( self::OPTION_NAME, array() );
		$fields = is_array( $fields ) ? $fields : array();

		return array(
			'phone'    => $this->sanitize_phone( $fields['phone'] ?? '' ),
			'whatsapp' => $this->sanitize_whatsapp( $fields['whatsapp'] ?? '' ),
			'telegram' => $this->sanitize_telegram( $fields['telegram'] ?? '' ),
		);
	}

	/**
	 * Sanitize, save, and index contact fields.
	 *
	 * @param mixed $fields Submitted fields.
	 * @return int Number of stored fragments.
	 */
	public function save( $fields ): int {
		$fields = is_array( $fields ) ? $fields : array();
		$fields = array(
			'phone'    => $this->sanitize_phone( $fields['phone'] ?? '' ),
			'whatsapp' => $this->sanitize_whatsapp( $fields['whatsapp'] ?? '' ),
			'telegram' => $this->sanitize_telegram( $fields['telegram'] ?? '' ),
		);

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, array(), '', false );
		}

		update_option( self::OPTION_NAME, $fields, false );

		return $this->reindex();
	}

	/**
	 * Rebuild contact details in the knowledge index.
	 *
	 * @return int Number of stored fragments.
	 */
	public function reindex(): int {
		$fields = $this->fields();
		$lines  = array(
			'Контакты. Contacts. Связь. Связаться с нами. Contact us. Менеджер. Manager. Написать сообщение. Консультация.',
		);

		if ( '' !== $fields['phone'] ) {
			$lines[] = 'Телефон / Phone: ' . $fields['phone'];
		}

		if ( '' !== $fields['whatsapp'] ) {
			$lines[] = 'WhatsApp: ' . $fields['whatsapp'];
			$lines[] = 'WhatsApp URL: ' . $this->whatsapp_url( $fields['whatsapp'] );
		}

		if ( '' !== $fields['telegram'] ) {
			$lines[] = 'Telegram: ' . $fields['telegram'];
			$lines[] = 'Telegram URL: ' . $this->telegram_url( $fields['telegram'] );
		}

		if ( 1 === count( $lines ) ) {
			$this->repository->delete_source( 'contact', self::SOURCE_ID );
			return 0;
		}

		return $this->repository->replace_source(
			'contact',
			self::SOURCE_ID,
			__( 'Contact information', 'wp-ds-aichatbot' ),
			'',
			$this->chunker->split( implode( "\n", $lines ) )
		);
	}

	/**
	 * Build exact public contact lines for deterministic chat replies.
	 *
	 * @return array<int, string>
	 */
	public function answer_lines(): array {
		$fields = $this->fields();
		$lines  = array();

		if ( '' !== $fields['phone'] ) {
			$lines[] = __( 'Phone:', 'wp-ds-aichatbot' ) . ' ' . $fields['phone'];
		}

		if ( '' !== $fields['whatsapp'] ) {
			$lines[] = 'WhatsApp: ' . $this->whatsapp_url( $fields['whatsapp'] );
		}

		if ( '' !== $fields['telegram'] ) {
			$lines[] = 'Telegram: ' . $this->telegram_url( $fields['telegram'] );
		}

		return $lines;
	}

	/**
	 * Return a safe telephone URL for the configured public phone number.
	 *
	 * @return string
	 */
	public function call_url(): string {
		$phone = $this->fields()['phone'];
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		return is_string( $phone ) && '' !== $phone ? 'tel:' . $phone : '';
	}

	/**
	 * Normalize a public phone number.
	 *
	 * @param mixed $value Submitted phone.
	 * @return string
	 */
	private function sanitize_phone( $value ): string {
		$value = sanitize_text_field( is_string( $value ) ? $value : '' );
		$value = preg_replace( '/[^0-9+()\-\s]/', '', $value );

		return is_string( $value ) ? substr( trim( $value ), 0, 50 ) : '';
	}

	/**
	 * Normalize a WhatsApp number or wa.me URL.
	 *
	 * @param mixed $value Submitted WhatsApp value.
	 * @return string
	 */
	private function sanitize_whatsapp( $value ): string {
		$value = sanitize_text_field( is_string( $value ) ? $value : '' );

		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, 'http://' ) || 0 === strpos( $value, 'https://' ) ) {
			$url  = esc_url_raw( $value, array( 'https' ) );
			$host = wp_parse_url( $url, PHP_URL_HOST );

			return in_array( $host, array( 'wa.me', 'www.wa.me', 'api.whatsapp.com' ), true ) ? $url : '';
		}

		$digits = preg_replace( '/\D+/', '', $value );

		return is_string( $digits ) ? substr( $digits, 0, 20 ) : '';
	}

	/**
	 * Normalize a Telegram username or t.me URL.
	 *
	 * @param mixed $value Submitted Telegram value.
	 * @return string
	 */
	private function sanitize_telegram( $value ): string {
		$value = sanitize_text_field( is_string( $value ) ? $value : '' );

		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strpos( $value, 'http://' ) || 0 === strpos( $value, 'https://' ) ) {
			$url  = esc_url_raw( $value, array( 'https' ) );
			$host = wp_parse_url( $url, PHP_URL_HOST );

			return in_array( $host, array( 't.me', 'www.t.me', 'telegram.me', 'www.telegram.me' ), true ) ? $url : '';
		}

		$username = ltrim( $value, '@' );

		return 1 === preg_match( '/^[A-Za-z0-9_]{5,32}$/', $username ) ? '@' . $username : '';
	}

	/**
	 * Convert a saved WhatsApp value to a clickable HTTPS URL.
	 *
	 * @param string $value Saved WhatsApp value.
	 * @return string
	 */
	private function whatsapp_url( string $value ): string {
		return 0 === strpos( $value, 'https://' ) ? $value : 'https://wa.me/' . $value;
	}

	/**
	 * Convert a saved Telegram value to a clickable HTTPS URL.
	 *
	 * @param string $value Saved Telegram value.
	 * @return string
	 */
	private function telegram_url( string $value ): string {
		return 0 === strpos( $value, 'https://' ) ? $value : 'https://t.me/' . ltrim( $value, '@' );
	}
}
