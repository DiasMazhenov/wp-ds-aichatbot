<?php
/**
 * Local-only integration probe mounted by WordPress Playground.
 *
 * @package WPDsAiChatbotTests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimal WooCommerce-compatible product fixture.
 */
final class WPDSAC_Test_Product {

	public function is_visible(): bool {
		return true;
	}

	public function get_sku(): string {
		return 'SOLAR-42';
	}

	public function get_price_html(): string {
		return '<span>$49.00</span>';
	}

	public function get_stock_status(): string {
		return 'instock';
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( int $post_id ): WPDSAC_Test_Product {
		unset( $post_id );

		return new WPDSAC_Test_Product();
	}
}

/**
 * Build a tiny valid text PDF without a binary repository fixture.
 *
 * @return string
 */
function wpdsac_test_pdf(): string {
	$stream  = 'BT /F1 12 Tf 72 720 Td (Solar warranty lasts ten years) Tj ET';
	$objects = array(
		'<< /Type /Catalog /Pages 2 0 R >>',
		'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
		'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
		'<< /Length ' . strlen( $stream ) . ">>\nstream\n" . $stream . "\nendstream",
	);
	$pdf     = "%PDF-1.4\n";
	$offsets = array( 0 );

	foreach ( $objects as $index => $object ) {
		$offsets[] = strlen( $pdf );
		$pdf      .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
	}

	$xref = strlen( $pdf );
	$pdf .= "xref\n0 6\n0000000000 65535 f \n";

	foreach ( array_slice( $offsets, 1 ) as $offset ) {
		$pdf .= sprintf( "%010d 00000 n \n", $offset );
	}

	return $pdf . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
}

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
	$credential_store = get_option( 'wpdsac_provider_credentials', array() );
	$test_credential  = str_repeat( 'x', 32 );
	update_option( 'wpdsac_provider_credentials', array( 'deepseek' => $test_credential ), false );
	$credential_bundle_resolved = $test_credential === ( new \DiasMazhenov\WPDsAiChatbot\AI\CredentialResolver() )->get_api_key( 'deepseek' );
	update_option( 'wpdsac_provider_credentials', is_array( $credential_store ) ? $credential_store : array(), false );

	$global_settings                    = is_array( $settings ) ? $settings : array();
	$global_settings['global_enabled']  = true;
	$global_settings['accent_color']    = '#123456';
	$global_settings['user_message_color'] = '#654321';
	$global_settings['chat_width']      = 512;
	$global_settings['launcher_size']   = 72;
	$global_settings['global_position'] = 'bottom_left';
	$global_settings['message_placeholder'] = 'Ask about delivery';
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
	$appearance_controller->enqueue_assets( 'toplevel_page_wpdsac-settings' );
	$admin_preview_assets = wp_style_is( 'wpdsac-admin', 'enqueued' )
		&& wp_script_is( 'wpdsac-admin', 'enqueued' );
	$ajax_save_registered = false !== has_action( 'wp_ajax_wpdsac_save_settings' );
	$deepseek_registered = in_array(
		'deepseek',
		\DiasMazhenov\WPDsAiChatbot\AI\CredentialResolver::provider_ids(),
		true
	) && 'deepseek-v4-flash' === \DiasMazhenov\WPDsAiChatbot\Admin\Settings::defaults()['deepseek_model'];

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
	$faq_type       = get_post_type_object( \DiasMazhenov\WPDsAiChatbot\Knowledge\FaqPostType::POST_TYPE );
	$faq_under_plugin_menu = $faq_type instanceof WP_Post_Type
		&& \DiasMazhenov\WPDsAiChatbot\Admin\Settings::PAGE_SLUG === $faq_type->show_in_menu;
	$faq_indexed = false;
	$pdf_indexed = false;
	$pdf_option_non_autoloaded = false;
	$woocommerce_indexed = false;
	$conversation_logged = false;
	$privacy_exported = false;
	$privacy_erased = false;
	$lead_rendered = false;
	$lead_saved = false;
	$lead_privacy_exported = false;
	$lead_privacy_erased = false;
	$rate_limit_enforced = false;
	$lead_rate_limit_enforced = false;
	$deactivation_clean = false;
	$lifecycle_rescheduled = false;
	$lead_admin_denied = false;

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

			$pdf_upload = wp_upload_bits( 'wpdsac-knowledge.pdf', null, wpdsac_test_pdf() );

			if ( empty( $pdf_upload['error'] ) ) {
				$pdf_attachment_id = wp_insert_attachment(
					array(
						'post_title'     => 'Solar Warranty PDF',
						'post_status'    => 'inherit',
						'post_mime_type' => 'application/pdf',
					),
					$pdf_upload['file']
				);

				if ( ! is_wp_error( $pdf_attachment_id ) ) {
					update_attached_file( $pdf_attachment_id, $pdf_upload['file'] );
					$pdf_indexer = new \DiasMazhenov\WPDsAiChatbot\Knowledge\PdfIndexer(
						$knowledge_repository,
						new \DiasMazhenov\WPDsAiChatbot\Knowledge\Chunker()
					);
					$pdf_result  = $pdf_indexer->save_selection( array( $pdf_attachment_id ) );
					$pdf_matches = $knowledge_repository->search( 'solar warranty years', 3 );
					$pdf_indexed = 1 === $pdf_result['indexed']
						&& false !== stripos( wp_json_encode( $pdf_matches ), 'ten years' );
					$pdf_option_non_autoloaded = ! array_key_exists(
						\DiasMazhenov\WPDsAiChatbot\Knowledge\PdfIndexer::OPTION_NAME,
						wp_load_alloptions()
					);
				}
			}

			register_post_type(
				'product',
				array(
					'public' => true,
				)
			);
			register_taxonomy( 'product_cat', 'product' );
			$product_id = wp_insert_post(
				array(
					'post_title'   => 'Solar Controller',
					'post_content' => 'A controller for off-grid solar systems.',
					'post_status'  => 'publish',
					'post_type'    => 'product',
				),
				true
			);

			if ( ! is_wp_error( $product_id ) ) {
				( new \DiasMazhenov\WPDsAiChatbot\Knowledge\WooCommerceSource() )->register_hooks();
				$product_post = get_post( $product_id );

				if ( $product_post instanceof WP_Post ) {
					$knowledge_indexer->index_post( $product_post );
					$product_matches = $knowledge_repository->search( 'SOLAR-42 stock', 3 );
					$woocommerce_indexed = false !== stripos( wp_json_encode( $product_matches ), 'SOLAR-42' )
						&& false !== stripos( wp_json_encode( $product_matches ), 'instock' );
				}
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

	$lead_settings                        = is_array( $settings ) ? $settings : array();
	$lead_settings['leads_enabled']       = true;
	$lead_settings['lead_consent_text']   = 'I consent to a follow-up about my request.';
	$lead_settings['lead_retention_days'] = 1;
	update_option( 'wpdsac_settings', $lead_settings, false );

	$lead_html     = do_shortcode( '[ds_ai_chatbot]' );
	$lead_rendered = false !== strpos( $lead_html, 'data-wpdsac-lead-form' )
		&& false !== strpos( $lead_html, 'I consent to a follow-up' );
	$lead_email    = 'wpdsac-lead-' . wp_generate_password( 8, false ) . '@example.test';
	$lead_repository = new \DiasMazhenov\WPDsAiChatbot\Data\LeadRepository();
	$lead_saved      = $lead_repository->save(
		'lead-privacy-session',
		0,
		'Lead Test',
		$lead_email,
		$lead_settings['lead_consent_text'],
		1
	);

	if ( $lead_saved ) {
		$lead_privacy          = new \DiasMazhenov\WPDsAiChatbot\Privacy\LeadPrivacy( $lead_repository );
		$lead_export           = $lead_privacy->export( $lead_email );
		$lead_privacy_exported = isset( $lead_export['data'][0]['data'][1]['value'] )
			&& $lead_email === $lead_export['data'][0]['data'][1]['value'];
		$lead_erasure          = $lead_privacy->erase( $lead_email );
		$lead_privacy_erased   = ! empty( $lead_erasure['items_removed'] )
			&& array() === $lead_privacy->export( $lead_email )['data'];
	}

	wp_set_current_user( 0 );
	ob_start();
	( new \DiasMazhenov\WPDsAiChatbot\Admin\LeadsPage( $lead_repository ) )->render_page();
	$lead_admin_denied = '' === (string) ob_get_clean();

	$security_limiter = new \DiasMazhenov\WPDsAiChatbot\Api\RateLimiter();
	$security_limiter->consume_request( 'unit-rate-session', 2, 60 );
	$security_limiter->consume_request( 'unit-rate-session', 2, 60 );
	$rate_limit_result = $security_limiter->consume_request( 'unit-rate-session', 2, 60 );
	$rate_limit_enforced = ! $rate_limit_result['allowed'] && 0 === $rate_limit_result['remaining'];

	for ( $lead_attempt = 0; $lead_attempt < 3; ++$lead_attempt ) {
		$security_limiter->consume_lead( 'unit-lead-rate-session' );
	}
	$lead_rate_result = $security_limiter->consume_lead( 'unit-lead-rate-session' );
	$lead_rate_limit_enforced = ! $lead_rate_result['allowed'];

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

	\DiasMazhenov\WPDsAiChatbot\Lifecycle\Deactivator::deactivate();
	$deactivation_clean = false === wp_next_scheduled( 'wpdsac_cleanup_rate_limits' )
		&& false === wp_next_scheduled( 'wpdsac_cleanup_conversations' )
		&& false === wp_next_scheduled( 'wpdsac_cleanup_leads' );
	\DiasMazhenov\WPDsAiChatbot\Lifecycle\Activator::activate();
	$lifecycle_rescheduled = false !== wp_next_scheduled( 'wpdsac_cleanup_rate_limits' )
		&& false !== wp_next_scheduled( 'wpdsac_cleanup_conversations' )
		&& false !== wp_next_scheduled( 'wpdsac_cleanup_leads' );

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
			'leads_table'                 => wpdsac_test_table_exists( $wpdb->prefix . 'wpdsac_leads' ),
			'conversation_cleanup_cron'   => false !== wp_next_scheduled( 'wpdsac_cleanup_conversations' ),
			'lead_cleanup_cron'           => false !== wp_next_scheduled( 'wpdsac_cleanup_leads' ),
			'settings_non_autoloaded'     => ! array_key_exists( 'wpdsac_settings', $all_options ),
			'shortcode_registered'        => shortcode_exists( 'ds_ai_chatbot' ),
			'shortcode_rendered'          => false !== strpos( $shortcode_html, 'wpdsac-chat' ),
			'shortcode_escaped'           => false === stripos( $shortcode_html, '<script' ),
			'global_widget_rendered'      => false !== strpos( $global_html, 'wpdsac-chat' ),
			'custom_message_placeholder_rendered' => false !== strpos( $global_html, 'placeholder="Ask about delivery"' ),
			'appearance_rendered'         => false !== strpos( $global_html, '--wpdsac-accent:#123456;' )
				&& false !== strpos( $global_html, '--wpdsac-user-message:#654321;' )
				&& false !== strpos( $global_html, '--wpdsac-width:512px;' )
				&& false !== strpos( $global_html, '--wpdsac-launcher-size:72px;' ),
			'appearance_positioned'       => false !== strpos( $global_html, 'wpdsac-position--bottom-left' ),
			'appearance_sanitized'        => $appearance_sanitized,
			'admin_preview_assets'        => $admin_preview_assets,
			'ajax_save_registered'        => $ajax_save_registered,
			'credential_bundle_resolved'  => $credential_bundle_resolved,
			'deepseek_registered'         => $deepseek_registered,
			'knowledge_indexed'           => $knowledge_indexed,
			'knowledge_retrieved'         => $knowledge_retrieved,
			'knowledge_augmented'         => $knowledge_augmented,
			'faq_registered'              => $faq_registered,
			'faq_under_plugin_menu'       => $faq_under_plugin_menu,
			'faq_indexed'                 => $faq_indexed,
			'pdf_indexed'                 => $pdf_indexed,
			'pdf_option_non_autoloaded'   => $pdf_option_non_autoloaded,
			'woocommerce_indexed'         => $woocommerce_indexed,
			'conversation_logged'         => $conversation_logged,
			'privacy_exported'            => $privacy_exported,
			'privacy_erased'              => $privacy_erased,
			'lead_rendered'               => $lead_rendered,
			'lead_saved'                  => $lead_saved,
			'lead_privacy_exported'       => $lead_privacy_exported,
			'lead_privacy_erased'         => $lead_privacy_erased,
			'rate_limit_enforced'         => $rate_limit_enforced,
			'lead_rate_limit_enforced'    => $lead_rate_limit_enforced,
			'deactivation_clean'          => $deactivation_clean,
			'lifecycle_rescheduled'       => $lifecycle_rescheduled,
			'lead_admin_denied'           => $lead_admin_denied,
			'elementor_loaded'            => $elementor_loaded,
			'elementor_widget_registered' => $elementor_widget_registered,
			'elementor_frontend_url'      => $elementor_frontend_url,
		),
		200
	);
}

/**
 * Run the production uninstall routine in the ephemeral Playground site.
 *
 * @return WP_REST_Response
 */
function wpdsac_test_uninstall(): WP_REST_Response {
	global $wpdb;

	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', 'wp-ds-aichatbot/wp-ds-aichatbot.php' );
	}

	require WPDSAC_PATH . 'uninstall.php';

	$tables = array(
		$wpdb->prefix . 'wpdsac_rate_limits',
		$wpdb->prefix . 'wpdsac_request_locks',
		$wpdb->prefix . 'wpdsac_knowledge_chunks',
		$wpdb->prefix . 'wpdsac_conversations',
		$wpdb->prefix . 'wpdsac_messages',
		$wpdb->prefix . 'wpdsac_leads',
	);
	$tables_removed = true;

	foreach ( $tables as $table ) {
		$tables_removed = $tables_removed && ! wpdsac_test_table_exists( $table );
	}

	return new WP_REST_Response(
		array(
			'tables_removed'  => $tables_removed,
			'options_removed' => false === get_option( 'wpdsac_settings', false )
				&& false === get_option( 'wpdsac_pdf_attachment_ids', false )
				&& false === get_option( 'wpdsac_deepseek_api_key', false )
				&& false === get_option( 'wpdsac_provider_credentials', false )
				&& false === get_option( 'wpdsac_db_version', false ),
			'cron_removed'    => false === wp_next_scheduled( 'wpdsac_cleanup_rate_limits' )
				&& false === wp_next_scheduled( 'wpdsac_cleanup_conversations' )
				&& false === wp_next_scheduled( 'wpdsac_cleanup_leads' ),
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

		register_rest_route(
			'wpdsac-test/v1',
			'/uninstall',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'wpdsac_test_uninstall',
				'permission_callback' => '__return_true',
			)
		);
	}
);
