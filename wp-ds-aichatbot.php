<?php
/**
 * Plugin Name:       WP DS AI Chatbot
 * Plugin URI:        https://github.com/DiasMazhenov/wp-ds-aichatbot
 * Description:       Extensible AI chatbot for WordPress and Elementor.
 * Version:           0.2.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Dias Mazhenov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-ds-aichatbot
 * Domain Path:       /languages
 *
 * @package WPDsAiChatbot
 */

defined( 'ABSPATH' ) || exit;

define( 'WPDSAC_VERSION', '0.2.0' );
define( 'WPDSAC_FILE', __FILE__ );
define( 'WPDSAC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPDSAC_URL', plugin_dir_url( __FILE__ ) );

require_once WPDSAC_PATH . 'src/Support/Autoloader.php';

\DiasMazhenov\WPDsAiChatbot\Support\Autoloader::register();

register_activation_hook(
	WPDSAC_FILE,
	array( \DiasMazhenov\WPDsAiChatbot\Lifecycle\Activator::class, 'activate' )
);

register_deactivation_hook(
	WPDSAC_FILE,
	array( \DiasMazhenov\WPDsAiChatbot\Lifecycle\Deactivator::class, 'deactivate' )
);

add_action(
	'plugins_loaded',
	static function () {
		\DiasMazhenov\WPDsAiChatbot\Plugin::instance()->boot();
	},
	5
);
