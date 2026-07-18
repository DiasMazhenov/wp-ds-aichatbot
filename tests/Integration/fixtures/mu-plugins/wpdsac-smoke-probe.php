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

	if ( class_exists( '\\Elementor\\Plugin' ) ) {
		$widgets = \Elementor\Plugin::instance()->widgets_manager->get_widget_types();
		$elementor_widget_registered = isset( $widgets['wpdsac-chatbot'] );
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
