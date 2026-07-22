<?php
/**
 * WordPress Plugins screen integration.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Admin;

use DiasMazhenov\WPDsAiChatbot\Support\PluginInfo;

defined( 'ABSPATH' ) || exit;

/**
 * Display version, settings link, and author link on the Plugins screen.
 */
final class PluginList {

	/**
	 * Register filters.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'all_plugins', array( $this, 'append_version' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPDSAC_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Add the runtime version to this plugin's Name and Title metadata.
	 *
	 * @param array<string, array<string, mixed>> $plugins Installed plugin metadata.
	 * @return array<string, array<string, mixed>>
	 */
	public function append_version( array $plugins ): array {
		$plugin_file = plugin_basename( WPDSAC_FILE );

		if ( ! isset( $plugins[ $plugin_file ] ) ) {
			return $plugins;
		}

		$versioned_name                  = PluginInfo::versioned_label( __( 'WP DS AI Chatbot', 'wp-ds-aichatbot' ) );
		$plugins[ $plugin_file ]['Name'] = $versioned_name;

		if ( isset( $plugins[ $plugin_file ]['Title'] ) ) {
			$plugins[ $plugin_file ]['Title'] = $versioned_name;
		}

		return $plugins;
	}

	/**
	 * Add Settings link in the plugin row actions.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public function action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=' . Settings::PAGE_SLUG );
		$settings     = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'wp-ds-aichatbot' ) . '</a>';

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Replace the author link in the plugin row meta.
	 *
	 * @param array<int, string> $meta  Existing row meta.
	 * @param string             $file  Plugin basename.
	 * @return array<int, string>
	 */
	public function row_meta( array $meta, string $file ): array {
		if ( plugin_basename( WPDSAC_FILE ) !== $file ) {
			return $meta;
		}

		$new_meta = array();

		foreach ( $meta as $item ) {
			// Replace "By <author>" link.
			if ( false !== strpos( $item, '>Dias Mazhenov<' ) || false !== strpos( $item, '>DiasMazhenov<' ) ) {
				$new_meta[] = sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( 'https://mazhenov.kz/' ),
					esc_html__( 'Visit author site', 'wp-ds-aichatbot' )
				);
				continue;
			}

			$new_meta[] = $item;
		}

		return $new_meta;
	}
}
