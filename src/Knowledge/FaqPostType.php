<?php
/**
 * Administrator-managed FAQ knowledge source.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Knowledge;

use DiasMazhenov\WPDsAiChatbot\Support\PluginInfo;

defined( 'ABSPATH' ) || exit;

/**
 * Register private FAQ entries with a native WordPress editing interface.
 */
final class FaqPostType {

	public const POST_TYPE = 'wpdsac_faq';

	/**
	 * Register the post type hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register private, administrator-managed FAQ entries.
	 *
	 * @return void
	 */
	public function register(): void {
		$capabilities = array(
			'edit_post'              => 'manage_options',
			'read_post'              => 'manage_options',
			'delete_post'            => 'manage_options',
			'edit_posts'             => 'manage_options',
			'edit_others_posts'      => 'manage_options',
			'publish_posts'          => 'manage_options',
			'read_private_posts'     => 'manage_options',
			'delete_posts'           => 'manage_options',
			'delete_private_posts'   => 'manage_options',
			'delete_published_posts' => 'manage_options',
			'delete_others_posts'    => 'manage_options',
			'edit_private_posts'     => 'manage_options',
			'edit_published_posts'   => 'manage_options',
			'create_posts'           => 'manage_options',
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => PluginInfo::versioned_label( __( 'AI FAQs', 'wp-ds-aichatbot' ) ),
					'singular_name' => __( 'AI FAQ', 'wp-ds-aichatbot' ),
					'menu_name'     => PluginInfo::versioned_label( __( 'AI FAQs', 'wp-ds-aichatbot' ) ),
					'add_new_item'  => __( 'Add AI FAQ', 'wp-ds-aichatbot' ),
					'edit_item'     => __( 'Edit AI FAQ', 'wp-ds-aichatbot' ),
					'new_item'      => __( 'New AI FAQ', 'wp-ds-aichatbot' ),
					'search_items'  => __( 'Search AI FAQs', 'wp-ds-aichatbot' ),
					'not_found'     => __( 'No AI FAQs found.', 'wp-ds-aichatbot' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'tools.php',
				'show_in_rest'        => true,
				'map_meta_cap'        => false,
				'capabilities'        => $capabilities,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_icon'           => 'dashicons-editor-help',
			)
		);
	}
}
