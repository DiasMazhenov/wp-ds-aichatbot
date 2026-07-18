<?php
/**
 * Public session issuance endpoint.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Api;

defined( 'ABSPATH' ) || exit;

final class SessionController {

	private const REST_NAMESPACE = 'wp-ds-aichatbot/v1';

	private $tokens;

	/**
	 * @param SessionToken $tokens Session token service.
	 */
	public function __construct( SessionToken $tokens ) {
		$this->tokens = $tokens;
	}

	/**
	 * Register route hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the public session endpoint.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/session',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Issue a new stateless signed session.
	 *
	 * @return \WP_REST_Response
	 */
	public function create(): \WP_REST_Response {
		$response = new \WP_REST_Response( $this->tokens->issue(), 201 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}
}

