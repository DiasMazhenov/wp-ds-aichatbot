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

if ( ! $view['show_toggle_icon'] ) {
	$classes .= ' wpdsac-hide-header-icon';
}
?>
<section
	id="<?php echo esc_attr( $view['id'] ); ?>"
	class="<?php echo esc_attr( $classes ); ?>"
	style="<?php echo esc_attr( $view['appearance'] ); ?>"
	data-wpdsac-chat
	data-wpdsac-reply-sound="<?php echo esc_attr( $view['reply_sound'] ); ?>"
	data-wpdsac-intro-trigger="<?php echo esc_attr( $view['intro_trigger'] ); ?>"
	data-wpdsac-intro-delay="<?php echo absint( $view['intro_delay'] ); ?>"
	data-wpdsac-avatar-url="<?php echo esc_url( $view['avatar_url'] ); ?>"
	data-wpdsac-welcome-message="<?php echo esc_attr( $view['welcome_message'] ); ?>"
>
	<button type="button" class="wpdsac-chat__intro-bubble" data-wpdsac-intro-bubble hidden>
		<?php echo esc_html( $view['welcome_message'] ); ?>
	</button>
	<button
		type="button"
		class="wpdsac-chat__toggle"
		aria-expanded="<?php echo $view['expanded'] ? 'true' : 'false'; ?>"
		aria-controls="<?php echo esc_attr( $panel_id ); ?>"
	>
		<span class="wpdsac-chat__toggle-title"><?php echo esc_html( $view['title'] ); ?></span>
		<svg class="wpdsac-chat__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.75c.47 4.88 4.37 8.78 9.25 9.25-4.88.47-8.78 4.37-9.25 9.25C11.53 16.37 7.63 12.47 2.75 12 7.63 11.53 11.53 7.63 12 2.75Z"/></svg>
	</button>

	<div
		id="<?php echo esc_attr( $panel_id ); ?>"
		class="wpdsac-chat__panel"
		<?php echo $view['expanded'] ? '' : 'hidden'; ?>
	>
		<div class="wpdsac-chat__conversation" data-wpdsac-conversation>
			<div class="wpdsac-chat__messages" aria-live="polite">
				<div class="wpdsac-chat__message-row wpdsac-chat__message-row--bot">
					<img class="wpdsac-chat__avatar" src="<?php echo esc_url( $view['avatar_url'] ); ?>" width="32" height="32" alt="">
					<p class="wpdsac-chat__message wpdsac-chat__message--bot" data-wpdsac-message-template="<?php echo esc_attr( $view['welcome_message'] ); ?>">
						<?php echo nl2br( esc_html( $view['welcome_message'] ) ); ?>
					</p>
				</div>
			</div>

			<div class="wpdsac-chat__divider" aria-hidden="true"></div>

			<div class="wpdsac-chat__quick-bar" aria-label="<?php esc_attr_e( 'Quick actions', 'wp-ds-aichatbot' ); ?>" data-wpdsac-quick-actions>
				<?php if ( '' !== $view['call_url'] ) : ?>
					<a href="<?php echo esc_url( $view['call_url'], array( 'http', 'https', 'tel', 'sms' ) ); ?>" class="wpdsac-chat__quick-action" data-wpdsac-quick-action="call">
						<span class="wpdsac-chat__quick-icon">
							<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.4 11.4 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .57 3.57 1 1 0 0 1-.25 1.02l-2.2 2.2Z"/></svg>
						</span>
						<span class="wpdsac-chat__quick-label"><?php echo esc_html( $view['quick_call_label'] ); ?></span>
					</a>
				<?php endif; ?>
				<button type="button" class="wpdsac-chat__quick-action" data-wpdsac-quick-action="lead" data-wpdsac-open-lead>
					<span class="wpdsac-chat__quick-icon">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4.7-8 5.34L4 8.7V6.75l8 5.34 8-5.34V8.7Z"/></svg>
					</span>
					<span class="wpdsac-chat__quick-label"><?php echo esc_html( $view['quick_lead_label'] ); ?></span>
				</button>
				<?php foreach ( $view['custom_actions'] as $quick_action ) : ?>
					<?php if ( 'url' === $quick_action['type'] ) : ?>
						<a href="<?php echo esc_url( $quick_action['value'], array( 'http', 'https' ) ); ?>" class="wpdsac-chat__quick-action" data-wpdsac-quick-action="<?php echo esc_attr( $quick_action['id'] ); ?>">
							<span class="wpdsac-chat__quick-icon">
								<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4M14 4h6v6M20 4 10 14"/></svg>
							</span>
							<span class="wpdsac-chat__quick-label"><?php echo esc_html( $quick_action['label'] ); ?></span>
						</a>
					<?php else : ?>
						<button type="button" class="wpdsac-chat__quick-action" data-wpdsac-quick-action="<?php echo esc_attr( $quick_action['id'] ); ?>" data-wpdsac-quick-message="<?php echo esc_attr( $quick_action['value'] ); ?>">
							<span class="wpdsac-chat__quick-icon">
								<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14l4 4V4a2 2 0 0 0-2-2Zm-2 12H6v-2h12v2Zm0-3H6V9h12v2Zm0-3H6V6h12v2Z"/></svg>
							</span>
							<span class="wpdsac-chat__quick-label"><?php echo esc_html( $quick_action['label'] ); ?></span>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<form class="wpdsac-chat__form" data-wpdsac-form>
			<label class="screen-reader-text" for="<?php echo esc_attr( $view['id'] ); ?>-input">
				<?php esc_html_e( 'Message', 'wp-ds-aichatbot' ); ?>
			</label>
			<div class="wpdsac-chat__input-wrap">
				<input
					id="<?php echo esc_attr( $view['id'] ); ?>-input"
					type="text"
					maxlength="2000"
					placeholder="<?php echo esc_attr( $view['message_placeholder'] ); ?>"
					autocomplete="off"
				>
				<button type="submit" aria-label="<?php esc_attr_e( 'Send message', 'wp-ds-aichatbot' ); ?>">
					<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2 .01 7Z"/></svg>
					<span class="wpdsac-chat__send-label"><?php esc_html_e( 'Send', 'wp-ds-aichatbot' ); ?></span>
				</button>
			</div>
			</form>
			<p class="wpdsac-chat__status" data-wpdsac-status aria-live="polite"></p>
		</div>
	</div>

	<div class="wpdsac-chat__lead-modal" data-wpdsac-lead data-wpdsac-lead-prompt="<?php echo esc_attr( $view['lead_prompt'] ); ?>" hidden>
		<button type="button" class="wpdsac-chat__modal-scrim" data-wpdsac-close-lead aria-label="<?php esc_attr_e( 'Close request form', 'wp-ds-aichatbot' ); ?>"></button>
		<div class="wpdsac-chat__lead-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $view['id'] ); ?>-lead-title" tabindex="-1">
			<header class="wpdsac-chat__lead-header">
				<div>
					<h2 id="<?php echo esc_attr( $view['id'] ); ?>-lead-title"><?php echo esc_html( $view['quick_lead_label'] ); ?></h2>
					<p><?php echo esc_html( $view['lead_prompt'] ); ?></p>
				</div>
				<button type="button" class="wpdsac-chat__modal-close" data-wpdsac-close-lead aria-label="<?php esc_attr_e( 'Close request form', 'wp-ds-aichatbot' ); ?>">
					<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M6.7 5.3 12 10.6l5.3-5.3 1.4 1.4-5.3 5.3 5.3 5.3-1.4 1.4-5.3-5.3-5.3 5.3-1.4-1.4 5.3-5.3-5.3-5.3 1.4-1.4Z"/></svg>
				</button>
			</header>
			<form data-wpdsac-lead-form>
				<label>
					<span><?php esc_html_e( 'Name', 'wp-ds-aichatbot' ); ?></span>
					<input type="text" name="name" maxlength="100" autocomplete="name" required>
				</label>
				<label>
					<span><?php esc_html_e( 'Phone', 'wp-ds-aichatbot' ); ?></span>
					<input type="tel" name="phone" maxlength="50" autocomplete="tel" required>
				</label>
				<label>
					<span><?php esc_html_e( 'How can we help?', 'wp-ds-aichatbot' ); ?></span>
					<textarea name="request" rows="3" maxlength="4000"></textarea>
				</label>
				<label class="wpdsac-chat__consent">
					<input type="checkbox" name="consent" value="1" required>
					<span><?php echo esc_html( $view['lead_consent'] ); ?></span>
				</label>
				<label class="wpdsac-chat__honeypot" aria-hidden="true">
					<span><?php esc_html_e( 'Website', 'wp-ds-aichatbot' ); ?></span>
					<input type="text" name="website" tabindex="-1" autocomplete="off">
				</label>
				<button type="submit" class="wpdsac-chat__lead-submit"><?php echo esc_html( $view['lead_submit_label'] ); ?></button>
			</form>
			<p class="wpdsac-chat__status" data-wpdsac-lead-status aria-live="polite"></p>
		</div>
	</div>
</section>
