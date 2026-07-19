<?php
/**
 * Shared plugin identity helpers.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Keep administrative labels synchronized with the current plugin version.
 */
final class PluginInfo {

	/**
	 * Append the runtime plugin version to an administrative label.
	 *
	 * @param string $label Translated label.
	 * @return string
	 */
	public static function versioned_label( string $label ): string {
		return sprintf( '%1$s v%2$s', $label, WPDSAC_VERSION );
	}
}
