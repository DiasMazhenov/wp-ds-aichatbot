<?php
/**
 * Chatbot shell template.
 *
 * @var array<string, mixed> $view Prepared and sanitized view data.
 *
 * @package WPDsAiChatbot
 */

defined( 'ABSPATH' ) || exit;

$panel_id = $view['id'] . '-panel';
$classes  = 'wpdsac-chat';

if ( $view['expanded'] ) {
	$classes .= ' is-expanded';
}

$classes .= ' ' . $view['position_class'];
?>
<section
	id="<?php echo esc_attr( $view['id'] ); ?>"
	class="<?php echo esc_attr( $classes ); ?>"
	style="<?php echo esc_attr( $view['appearance'] ); ?>"
	data-wpdsac-chat
>
	<button
		type="button"
		class="wpdsac-chat__toggle"
		aria-expanded="<?php echo $view['expanded'] ? 'true' : 'false'; ?>"
		aria-controls="<?php echo esc_attr( $panel_id ); ?>"
	>
		<span class="wpdsac-chat__toggle-title"><?php echo esc_html( $view['title'] ); ?></span>
		<span aria-hidden="true">✦</span>
	</button>

	<div
		id="<?php echo esc_attr( $panel_id ); ?>"
		class="wpdsac-chat__panel"
		<?php echo $view['expanded'] ? '' : 'hidden'; ?>
	>
		<div class="wpdsac-chat__messages" aria-live="polite">
			<p class="wpdsac-chat__message wpdsac-chat__message--bot">
				<?php echo nl2br( esc_html( $view['welcome_message'] ) ); ?>
			</p>
		</div>

		<?php if ( $view['leads_enabled'] ) : ?>
			<div class="wpdsac-chat__lead" data-wpdsac-lead>
				<p><strong><?php echo esc_html( $view['lead_prompt'] ); ?></strong></p>
				<form data-wpdsac-lead-form>
					<label>
						<span><?php esc_html_e( 'Name', 'wp-ds-aichatbot' ); ?></span>
						<input type="text" name="name" maxlength="100" autocomplete="name">
					</label>
					<label>
						<span><?php esc_html_e( 'Email', 'wp-ds-aichatbot' ); ?></span>
						<input type="email" name="email" maxlength="190" autocomplete="email" required>
					</label>
					<label class="wpdsac-chat__consent">
						<input type="checkbox" name="consent" value="1" required>
						<span><?php echo esc_html( $view['lead_consent'] ); ?></span>
					</label>
					<label class="wpdsac-chat__honeypot" aria-hidden="true">
						<span><?php esc_html_e( 'Website', 'wp-ds-aichatbot' ); ?></span>
						<input type="text" name="website" tabindex="-1" autocomplete="off">
					</label>
					<button type="submit"><?php esc_html_e( 'Send contact details', 'wp-ds-aichatbot' ); ?></button>
				</form>
				<p class="wpdsac-chat__status" data-wpdsac-lead-status aria-live="polite"></p>
			</div>
		<?php endif; ?>

		<form class="wpdsac-chat__form" data-wpdsac-form>
			<label class="screen-reader-text" for="<?php echo esc_attr( $view['id'] ); ?>-input">
				<?php esc_html_e( 'Message', 'wp-ds-aichatbot' ); ?>
			</label>
			<input
				id="<?php echo esc_attr( $view['id'] ); ?>-input"
				type="text"
				maxlength="2000"
				placeholder="<?php esc_attr_e( 'Type your message…', 'wp-ds-aichatbot' ); ?>"
				autocomplete="off"
			>
			<button type="submit"><?php esc_html_e( 'Send', 'wp-ds-aichatbot' ); ?></button>
		</form>
		<p class="wpdsac-chat__status" data-wpdsac-status aria-live="polite"></p>
	</div>
</section>
