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
 * Display the current version directly beside the plugin name.
 */
final class PluginList {

	/**
	 * Register the Plugins screen filter.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'all_plugins', array( $this, 'append_version' ) );
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
}
