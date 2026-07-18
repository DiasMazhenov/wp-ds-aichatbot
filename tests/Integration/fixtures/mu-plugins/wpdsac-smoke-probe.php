<?php
/**
 * Local-only integration probe mounted by WordPress Playground.
 *
 * @package WPDsAiChatbotTests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Determine whether a plugin table exists.
 *
 * @param string $table_name Fully-prefixed table name.
 * @return bool
 */
function wpdsac_test_table_exists( string $table_name ): bool {
	global $wpdb;

	$result = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$wpdb->esc_like( $table_name )
		)
	);

	return $table_name === $result;
}

/**
 * Create a temporary Elementor page containing the chatbot widget.
 *
 * The WordPress Playground site is ephemeral, so the fixture does not need
 * persistent cleanup.
 *
 * @return string|null Published page URL, or null when Elementor is unavailable.
 */
function wpdsac_test_create_elementor_page(): ?string {
	if ( ! class_exists( '\\Elementor\\Plugin' ) || ! defined( 'ELEMENTOR_VERSION' ) ) {
		return null;
	}

	$post_id = wp_insert_post(
		array(
			'post_title'   => 'WPDSAC Elementor Smoke Page',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return null;
	}

	$elementor_data = array(
		array(
			'id'       => 'wpdsac01',
			'elType'   => 'container',
			'settings' => array(),
			'elements' => array(
				array(
					'id'         => 'wpdsac02',
					'elType'     => 'widget',
					'widgetType' => 'wpdsac-chatbot',
					'settings'   => array(
						'title'           => 'Elementor Smoke & Test',
						'welcome_message' => '<script>alert(1)</script>',
					),
					'elements'   => array(),
				),
			),
		),
	);

	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );

	$permalink = get_permalink( $post_id );

	return is_string( $permalink ) ? $permalink : null;
}

/**
 * Collect runtime assertions that require access to WordPress internals.
 *
 * @return WP_REST_Response
 */
function wpdsac_test_probe(): WP_REST_Response {
	global $wpdb;

	$settings       = get_option( 'wpdsac_settings', array() );
	$active_plugins = (array) get_option( 'active_plugins', array() );
	$shortcode_html = do_shortcode(
		'[ds_ai_chatbot title="Smoke &amp; Test" welcome_message="&lt;script&gt;alert(1)&lt;/script&gt;"]'
	);
	$all_options    = wp_load_alloptions();

	$global_settings                   = is_array( $settings ) ? $settings : array();
	$global_settings['global_enabled'] = true;
	update_option( 'wpdsac_settings', $global_settings, false );

	ob_start();
	do_action( 'wp_footer' );
	$global_html = (string) ob_get_clean();

	update_option( 'wpdsac_settings', $settings, false );

	$elementor_loaded            = did_action( 'elementor/loaded' ) > 0;
	$elementor_widget_registered = false;
	$elementor_frontend_url      = null;

	if ( class_exists( '\\Elementor\\Plugin' ) ) {
		$widgets = \Elementor\Plugin::instance()->widgets_manager->get_widget_types();
		$elementor_widget_registered = isset( $widgets['wpdsac-chatbot'] );

		if ( $elementor_widget_registered ) {
			$elementor_frontend_url = wpdsac_test_create_elementor_page();
		}
	}

	return new WP_REST_Response(
		array(
			'plugin_active'               => in_array( 'wp-ds-aichatbot/wp-ds-aichatbot.php', $active_plugins, true ),
			'plugin_loaded'               => defined( 'WPDSAC_VERSION' ) && class_exists( '\\DiasMazhenov\\WPDsAiChatbot\\Plugin' ),
			'plugin_version'              => defined( 'WPDSAC_VERSION' ) ? WPDSAC_VERSION : null,
			'db_version'                  => get_option( 'wpdsac_db_version' ),
			'rate_limit_table'            => wpdsac_test_table_exists( $wpdb->prefix . 'wpdsac_rate_limits' ),
			'request_lock_table'          => wpdsac_test_table_exists( $wpdb->prefix . 'wpdsac_request_locks' ),
			'settings_non_autoloaded'     => ! array_key_exists( 'wpdsac_settings', $all_options ),
			'shortcode_registered'        => shortcode_exists( 'ds_ai_chatbot' ),
			'shortcode_rendered'          => false !== strpos( $shortcode_html, 'wpdsac-chat' ),
			'shortcode_escaped'           => false === stripos( $shortcode_html, '<script' ),
			'global_widget_rendered'      => false !== strpos( $global_html, 'wpdsac-chat' ),
			'elementor_loaded'            => $elementor_loaded,
			'elementor_widget_registered' => $elementor_widget_registered,
			'elementor_frontend_url'      => $elementor_frontend_url,
		),
		200
	);
}

add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'wpdsac-test/v1',
			'/probe',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'wpdsac_test_probe',
				'permission_callback' => '__return_true',
			)
		);
	}
);
