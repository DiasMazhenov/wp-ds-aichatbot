<?php
/**
 * Public consented lead endpoint.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;

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
	 */
	public function __construct( SessionToken $tokens, RateLimiter $limiter, LeadRepository $repository ) {
		$this->tokens     = $tokens;
		$this->limiter    = $limiter;
		$this->repository = $repository;
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
					'session' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_session' ),
					),
					'name'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_name' ),
					),
					'email'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => 'is_email',
					),
					'consent' => array(
						'type'     => 'boolean',
						'required' => true,
					),
					'website' => array(
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
	 * Validate optional name length.
	 *
	 * @param mixed $value Raw name.
	 * @return bool
	 */
	public function validate_name( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		return self::length( $value ) <= 100;
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
	 * Validate signed session and feature state.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public function permissions_check( \WP_REST_Request $request ) {
		$options = Settings::get();

		if ( empty( $options['leads_enabled'] ) ) {
			return new \WP_Error(
				'wpdsac_leads_disabled',
				__( 'Lead collection is not enabled.', 'wp-ds-aichatbot' ),
				array( 'status' => 404 )
			);
		}

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
			(string) $request->get_param( 'name' ),
			(string) $request->get_param( 'email' ),
			(string) $options['lead_consent_text'],
			(int) $options['lead_retention_days']
		);

		if ( ! $saved ) {
			return new \WP_Error(
				'wpdsac_lead_not_saved',
				__( 'Contact details could not be saved. Please try again.', 'wp-ds-aichatbot' ),
				array( 'status' => 500 )
			);
		}

		do_action( 'wpdsac_lead_created', sanitize_email( (string) $request->get_param( 'email' ) ), $this->session_id );

		$response = new \WP_REST_Response(
			array( 'message' => __( 'Thank you. Your contact details were saved.', 'wp-ds-aichatbot' ) ),
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
