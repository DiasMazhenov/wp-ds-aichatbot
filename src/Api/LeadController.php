<?php
/**
 * Public consented lead endpoint.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

use DiasMazhenov\WPDsAiChatbot\Api\SessionToken;
use DiasMazhenov\WPDsAiChatbot\Api\RateLimiter;
use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;
use DiasMazhenov\WPDsAiChatbot\Data\LeadNotifier;

defined( 'ABSPATH' ) || exit;

/**
 * Validate a signed session, consent, and lead fields before persistence.
 */
final class LeadController {

	private const REST_NAMESPACE = 'wp-ds-aichatbot/v1';

	/**
	 * Session token service.
	 *
	 * @var SessionToken
	 */
	private $tokens;

	/**
	 * Public request limiter.
	 *
	 * @var RateLimiter
	 */
	private $limiter;

	/**
	 * Lead repository.
	 *
	 * @var LeadRepository
	 */
	private $repository;

	/**
	 * Lead email notifier.
	 *
	 * @var LeadNotifier
	 */
	private $notifier;

	/**
	 * Verified session UUID.
	 *
	 * @var string
	 */
	private $session_id = '';

	/**
	 * Store endpoint dependencies.
	 *
	 * @param SessionToken   $tokens     Session token service.
	 * @param RateLimiter    $limiter    Public request limiter.
	 * @param LeadRepository $repository Lead repository.
	 * @param LeadNotifier   $notifier   Lead email notifier.
	 */
	public function __construct( SessionToken $tokens, RateLimiter $limiter, LeadRepository $repository, LeadNotifier $notifier ) {
		$this->tokens     = $tokens;
		$this->limiter    = $limiter;
		$this->repository = $repository;
		$this->notifier   = $notifier;
	}

	/**
	 * Register REST route.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the lead endpoint and fixed schema.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/lead',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'session'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_session' ),
					),
					'name'       => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_name' ),
					),
					'phone'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_phone' ),
					),
					'email'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => array( $this, 'validate_email' ),
					),
					'request'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => array( $this, 'validate_request' ),
					),
					'transcript' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => array( $this, 'validate_transcript' ),
					),
					'consent'    => array(
						'type'     => 'boolean',
						'required' => true,
					),
					'website'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_honeypot' ),
					),
				),
			)
		);
	}

	/**
	 * Validate a bounded session token shape.
	 *
	 * @param mixed $value Raw token.
	 * @return bool
	 */
	public function validate_session( $value ): bool {
		return is_string( $value ) && strlen( $value ) <= 1024 && false !== strpos( $value, '.' );
	}

	/**
	 * Validate a required name.
	 *
	 * @param mixed $value Raw name.
	 * @return bool
	 */
	public function validate_name( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		return '' !== trim( $value ) && self::length( $value ) <= 100;
	}

	/**
	 * Validate a required phone number.
	 *
	 * @param mixed $value Raw phone number.
	 * @return bool
	 */
	public function validate_phone( $value ): bool {
		if ( ! is_string( $value ) || self::length( $value ) > 50 || 1 !== preg_match( '/^[0-9+().\/\-\s]*$/', $value ) ) {
			return false;
		}

		$digits = preg_replace( '/\D/', '', $value );

		return is_string( $digits ) && strlen( $digits ) >= 7 && strlen( $digits ) <= 20;
	}

	/**
	 * Validate an optional email address.
	 *
	 * @param mixed $value Raw email.
	 * @return bool
	 */
	public function validate_email( $value ): bool {
		if ( '' === $value ) {
			return true;
		}

		return is_string( $value ) && self::length( $value ) <= 100 && is_email( $value );
	}

	/**
	 * Validate an optional request description.
	 *
	 * @param mixed $value Raw request text.
	 * @return bool
	 */
	public function validate_request( $value ): bool {
		return is_string( $value ) && self::length( $value ) <= 4000;
	}

	/**
	 * Validate a bounded plain-text transcript.
	 *
	 * @param mixed $value Raw transcript.
	 * @return bool
	 */
	public function validate_transcript( $value ): bool {
		return is_string( $value ) && self::length( $value ) <= 20000;
	}

	/**
	 * Reject filled bot-only honeypot fields.
	 *
	 * @param mixed $value Raw honeypot.
	 * @return bool
	 */
	public function validate_honeypot( $value ): bool {
		return is_string( $value ) && '' === trim( $value );
	}

	/**
	 * Validate the signed session.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public function permissions_check( \WP_REST_Request $request ) {
		$session_id = $this->tokens->validate( (string) $request->get_param( 'session' ) );

		if ( is_wp_error( $session_id ) ) {
			return $session_id;
		}

		$this->session_id = $session_id;

		return true;
	}

	/**
	 * Store one consented lead.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ) {
		if ( true !== $request->get_param( 'consent' ) ) {
			return new \WP_Error(
				'wpdsac_lead_consent_required',
				__( 'Consent is required before contact details can be saved.', 'wp-ds-aichatbot' ),
				array( 'status' => 400 )
			);
		}

		$name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );
		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( ! $this->validate_name( $name ) || ! $this->validate_phone( $phone ) ) {
			return new \WP_Error(
				'wpdsac_lead_phone_required',
				__( 'Enter your name and a valid phone number.', 'wp-ds-aichatbot' ),
				array( 'status' => 400 )
			);
		}

		$limit = $this->limiter->consume_lead( $this->session_id );

		if ( ! $limit['allowed'] ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'wpdsac_lead_rate_limited',
					'message' => __( 'Too many contact submissions. Please try again later.', 'wp-ds-aichatbot' ),
				),
				429
			);
			$response->header( 'Retry-After', (string) $limit['retry_after'] );

			return $response;
		}

		$options = Settings::get();
		$saved   = $this->repository->save(
			$this->session_id,
			get_current_user_id(),
			$name,
			$email,
			$phone,
			(string) $request->get_param( 'request' ),
			(string) $options['lead_consent_text'],
			(int) $options['lead_retention_days']
		);

		if ( ! $saved ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only operational metadata without SQL or visitor data.
					sprintf(
						'[WP DS AI Chatbot] Lead save failed; database schema version: %s',
						sanitize_text_field( (string) get_option( 'wpdsac_db_version', '0' ) )
					)
				);
			}

			return new \WP_Error(
				'wpdsac_lead_not_saved',
				__( 'Contact details could not be saved. Please try again.', 'wp-ds-aichatbot' ),
				array( 'status' => 500 )
			);
		}

		do_action( 'wpdsac_lead_created', $phone, $this->session_id );
		$notified = $this->notifier->send(
			array(
				'name'       => $name,
				'phone'      => $phone,
				'email'      => $email,
				'request'    => (string) $request->get_param( 'request' ),
				'transcript' => (string) $request->get_param( 'transcript' ),
			)
		);

		$webhook_url = (string) $options['lead_webhook_url'];
		if ( '' !== $webhook_url ) {
			wp_safe_remote_post(
				$webhook_url,
				array(
					'timeout'     => 10,
					'redirection' => 0,
					'headers'     => array( 'Content-Type' => 'application/json' ),
					'body'        => wp_json_encode(
						array(
							'name'    => $name,
							'phone'   => $phone,
							'email'   => $email,
							'request' => (string) $request->get_param( 'request' ),
						)
					),
				)
			);
		}

		$response = new \WP_REST_Response(
			array(
				'message'  => __( 'Thank you. Your request was sent.', 'wp-ds-aichatbot' ),
				'notified' => $notified,
			),
			201
		);
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Count Unicode characters when available.
	 *
	 * @param string $value Input value.
	 * @return int
	 */
	private static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
