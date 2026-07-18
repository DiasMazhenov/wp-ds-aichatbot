<?php
/**
 * Elementor integration coordinator.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Elementor;

use DiasMazhenov\WPDsAiChatbot\Chat\Renderer;

defined( 'ABSPATH' ) || exit;

final class Integration {

	private const MINIMUM_VERSION = '3.19.0';

	private $renderer;

	/**
	 * @param Renderer $renderer Shared chatbot renderer.
	 */
	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register Elementor hooks when available.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( did_action( 'elementor/loaded' ) ) {
			$this->on_elementor_loaded();
			return;
		}

		add_action( 'elementor/loaded', array( $this, 'on_elementor_loaded' ) );
	}

	/**
	 * Register widget callback after compatibility checks.
	 *
	 * @return void
	 */
	public function on_elementor_loaded(): void {
		if ( ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, self::MINIMUM_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'render_version_notice' ) );
			return;
		}

		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	/**
	 * Register the chatbot widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widget( $widgets_manager ): void {
		ChatbotWidget::set_renderer( $this->renderer );
		$widgets_manager->register( new ChatbotWidget() );
	}

	/**
	 * Warn administrators about unsupported Elementor versions.
	 *
	 * @return void
	 */
	public function render_version_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: minimum Elementor version. */
						__( 'WP DS AI Chatbot requires Elementor %s or newer for its Elementor widget.', 'wp-ds-aichatbot' ),
						self::MINIMUM_VERSION
					)
				);
				?>
			</p>
		</div>
		<?php
	}
}
