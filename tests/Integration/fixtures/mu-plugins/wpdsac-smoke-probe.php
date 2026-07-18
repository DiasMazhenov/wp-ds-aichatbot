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

	$global_settings                    = is_array( $settings ) ? $settings : array();
	$global_settings['global_enabled']  = true;
	$global_settings['accent_color']    = '#123456';
	$global_settings['chat_width']      = 512;
	$global_settings['global_position'] = 'bottom_left';
	update_option( 'wpdsac_settings', $global_settings, false );

	ob_start();
	do_action( 'wp_footer' );
	$global_html = (string) ob_get_clean();

	update_option( 'wpdsac_settings', $settings, false );

	$sanitized_appearance = \DiasMazhenov\WPDsAiChatbot\Chat\Appearance::sanitize(
		array(
			'accent_color'       => 'not-a-color',
			'chat_width'         => 9999,
			'chat_border_radius' => 999,
			'chat_font_size'     => 1,
			'global_position'    => 'top_center',
		)
	);
	$appearance_sanitized = '#2563eb' === $sanitized_appearance['accent_color']
		&& 640 === $sanitized_appearance['chat_width']
		&& 40 === $sanitized_appearance['chat_border_radius']
		&& 12 === $sanitized_appearance['chat_font_size']
		&& 'bottom_right' === $sanitized_appearance['global_position'];

	$appearance_controller = new \DiasMazhenov\WPDsAiChatbot\Admin\AppearanceSettings();
	$appearance_controller->enqueue_assets( 'settings_page_wpdsac-settings' );
	$admin_preview_assets = wp_style_is( 'wpdsac-admin', 'enqueued' )
		&& wp_script_is( 'wpdsac-admin', 'enqueued' );

	$knowledge_post_id = wp_insert_post(
		array(
			'post_title'   => 'Refund Policy',
			'post_content' => 'Customers may request a refund within thirty calendar days of purchase.',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		),
		true
	);
	$knowledge_indexed = false;
	$knowledge_retrieved = false;
	$knowledge_augmented = false;
	$faq_registered = post_type_exists( \DiasMazhenov\WPDsAiChatbot\Knowledge\FaqPostType::POST_TYPE );
	$faq_indexed = false;
	$conversation_logged = false;
	$privacy_exported = false;
	$privacy_erased = false;

	if ( ! is_wp_error( $knowledge_post_id ) ) {
		$knowledge_repository = new \DiasMazhenov\WPDsAiChatbot\Knowledge\Repository();
		$knowledge_indexer    = new \DiasMazhenov\WPDsAiChatbot\Knowledge\PostIndexer(
			$knowledge_repository,
			new \DiasMazhenov\WPDsAiChatbot\Knowledge\Chunker()
		);
		$knowledge_post       = get_post( $knowledge_post_id );

		if ( $knowledge_post instanceof WP_Post ) {
			$knowledge_indexed = $knowledge_indexer->index_post( $knowledge_post ) > 0;
			$matches           = $knowledge_repository->search( 'What is the refund policy?', 2 );
			$knowledge_retrieved = isset( $matches[0]['content'] )
				&& false !== stripos( $matches[0]['content'], 'thirty calendar days' );

			$knowledge_settings                         = is_array( $settings ) ? $settings : array();
			$knowledge_settings['knowledge_enabled']    = true;
			$knowledge_settings['knowledge_max_chunks'] = 2;
			update_option( 'wpdsac_settings', $knowledge_settings, false );

			$retriever = new \DiasMazhenov\WPDsAiChatbot\Knowledge\Retriever( $knowledge_repository );
			$augmented = $retriever->augment(
				'What is the refund policy?',
				'test-session',
				new WP_REST_Request(),
				'openai'
			);
			$knowledge_augmented = false !== strpos( $augmented, '<knowledge>' )
				&& false !== strpos( $augmented, 'thirty calendar days' )
				&& false !== strpos( $augmented, 'Visitor question:' );

			$faq_id = wp_insert_post(
				array(
					'post_title'   => 'Battery warranty',
					'post_content' => 'The battery warranty lasts twenty-four months from delivery.',
					'post_status'  => 'publish',
					'post_type'    => \DiasMazhenov\WPDsAiChatbot\Knowledge\FaqPostType::POST_TYPE,
				),
				true
			);

			if ( ! is_wp_error( $faq_id ) ) {
				$faq_matches = $knowledge_repository->search( 'How long is the battery warranty?', 2 );
				$faq_indexed = isset( $faq_matches[0]['content'] )
					&& false !== stripos( $faq_matches[0]['content'], 'twenty-four months' );
			}

			update_option( 'wpdsac_settings', $settings, false );
		}
	}

	$privacy_email = 'wpdsac-privacy-' . wp_generate_password( 8, false ) . '@example.test';
	$privacy_user_id = wp_insert_user(
		array(
			'user_login' => 'wpdsac_privacy_' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $privacy_email,
		)
	);

	if ( ! is_wp_error( $privacy_user_id ) ) {
		$logging_settings                       = is_array( $settings ) ? $settings : array();
		$logging_settings['logging_enabled']    = true;
		$logging_settings['log_retention_days'] = 1;
		update_option( 'wpdsac_settings', $logging_settings, false );
		wp_set_current_user( $privacy_user_id );

		do_action(
			'wpdsac_chat_exchange',
			'privacy-test-session',
			'Private visitor message',
			'Private assistant reply',
			new WP_REST_Request()
		);

		$conversation_repository = new \DiasMazhenov\WPDsAiChatbot\Data\ConversationRepository();
		$privacy                 = new \DiasMazhenov\WPDsAiChatbot\Privacy\ConversationPrivacy( $conversation_repository );
		$export                  = $privacy->export( $privacy_email );
		$conversation_logged     = isset( $export['data'] ) && 2 === count( $export['data'] );
		$privacy_exported        = $conversation_logged
			&& false !== strpos( $export['data'][0]['data'][1]['value'], 'Private visitor message' );
		$erasure                 = $privacy->erase( $privacy_email );
		$privacy_erased          = ! empty( $erasure['items_removed'] )
			&& array() === $privacy->export( $privacy_email )['data'];

		wp_set_current_user( 0 );
		update_option( 'wpdsac_settings', $settings, false );
	}

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
			'knowledge_table'             => wpdsac_test_table_exists( $wpdb->prefix . 'wpdsac_knowledge_chunks' ),
			'conversations_table'         => wpdsac_test_table_exists( $wpdb->prefix . 'wpdsac_conversations' ),
			'messages_table'              => wpdsac_test_table_exists( $wpdb->prefix . 'wpdsac_messages' ),
			'conversation_cleanup_cron'   => false !== wp_next_scheduled( 'wpdsac_cleanup_conversations' ),
			'settings_non_autoloaded'     => ! array_key_exists( 'wpdsac_settings', $all_options ),
			'shortcode_registered'        => shortcode_exists( 'ds_ai_chatbot' ),
			'shortcode_rendered'          => false !== strpos( $shortcode_html, 'wpdsac-chat' ),
			'shortcode_escaped'           => false === stripos( $shortcode_html, '<script' ),
			'global_widget_rendered'      => false !== strpos( $global_html, 'wpdsac-chat' ),
			'appearance_rendered'         => false !== strpos( $global_html, '--wpdsac-accent:#123456;' )
				&& false !== strpos( $global_html, '--wpdsac-width:512px;' ),
			'appearance_positioned'       => false !== strpos( $global_html, 'wpdsac-position--bottom-left' ),
			'appearance_sanitized'        => $appearance_sanitized,
			'admin_preview_assets'        => $admin_preview_assets,
			'knowledge_indexed'           => $knowledge_indexed,
			'knowledge_retrieved'         => $knowledge_retrieved,
			'knowledge_augmented'         => $knowledge_augmented,
			'faq_registered'              => $faq_registered,
			'faq_indexed'                 => $faq_indexed,
			'conversation_logged'         => $conversation_logged,
			'privacy_exported'            => $privacy_exported,
			'privacy_erased'              => $privacy_erased,
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
