<?php
/**
 * Elementor chatbot widget.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\Elementor;

use DiasMazhenov\WPDsAiChatbot\Chat\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Render the chatbot as a native Elementor widget.
 */
final class ChatbotWidget extends \Elementor\Widget_Base {

	/**
	 * Shared chatbot renderer.
	 *
	 * @var Renderer|null
	 */
	private static $chatbot_renderer;

	/**
	 * Inject the renderer before Elementor instantiates the widget.
	 *
	 * @param Renderer $renderer Shared chatbot renderer.
	 * @return void
	 */
	public static function set_renderer( Renderer $renderer ): void {
		self::$chatbot_renderer = $renderer;
	}

	/**
	 * Return the internal Elementor widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'wpdsac-chatbot';
	}

	/**
	 * Return the widget title shown in Elementor.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( 'DS AI Chatbot', 'wp-ds-aichatbot' );
	}

	/**
	 * Return the Elementor icon identifier.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-comments';
	}

	/**
	 * Return the Elementor widget categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'general' );
	}

	/**
	 * Return required frontend script handles.
	 *
	 * @return array<int, string>
	 */
	public function get_script_depends(): array {
		return array( 'wpdsac-chat' );
	}

	/**
	 * Return required frontend style handles.
	 *
	 * @return array<int, string>
	 */
	public function get_style_depends(): array {
		return array( 'wpdsac-chat' );
	}

	/**
	 * Register widget controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content',
			array( 'label' => esc_html__( 'Content', 'wp-ds-aichatbot' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Title', 'wp-ds-aichatbot' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Use the global title', 'wp-ds-aichatbot' ),
			)
		);

		$this->add_control(
			'welcome_message',
			array(
				'label'       => esc_html__( 'Welcome message', 'wp-ds-aichatbot' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'placeholder' => esc_html__( 'Use the global welcome message', 'wp-ds-aichatbot' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the Elementor widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		if ( ! self::$chatbot_renderer instanceof Renderer ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$args     = array_filter(
			array(
				'title'           => sanitize_text_field( $settings['title'] ?? '' ),
				'welcome_message' => sanitize_textarea_field( $settings['welcome_message'] ?? '' ),
				'expanded'        => true,
			),
			static function ( $value ): bool {
				return '' !== $value;
			}
		);

		// The shared template escapes every dynamic value.
		echo self::$chatbot_renderer->render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
