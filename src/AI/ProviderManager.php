<?php
/**
 * AI provider selection and chat hook bridge.
 *
 * @package WPDsAiChatbot
 */

namespace DiasMazhenov\WPDsAiChatbot\AI;

use DiasMazhenov\WPDsAiChatbot\Admin\Settings;
use DiasMazhenov\WPDsAiChatbot\Data\LeadRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Select and invoke the configured AI provider.
 */
final class ProviderManager {

	/**
	 * Registered providers keyed by ID.
	 *
	 * @var array<string, ProviderInterface>
	 */
	private $providers;

	/**
	 * Deterministic request guard.
	 *
	 * @var PromptGuard
	 */
	private $guard;

	/**
	 * Lead persistence to check for existing submissions.
	 *
	 * @var LeadRepository|null
	 */
	private $leads;

	/**
	 * Store the provider registry.
	 *
	 * @param array<string, ProviderInterface> $providers Registered providers by ID.
	 * @param PromptGuard                      $guard     Request guard.
	 * @param LeadRepository|null              $leads     Lead persistence for duplicate detection.
	 */
	public function __construct( array $providers, PromptGuard $guard, ?LeadRepository $leads = null ) {
		$this->providers = $providers;
		$this->guard     = $guard;
		$this->leads     = $leads;
	}

	/**
	 * Register the provider bridge.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wpdsac_chat_reply', array( $this, 'generate' ), 10, 4 );
	}

	/**
	 * Generate a reply unless an earlier integration already supplied one.
	 *
	 * @param mixed            $reply      Existing reply.
	 * @param string           $message    Visitor message.
	 * @param string           $session_id Verified session UUID.
	 * @param \WP_REST_Request $request    REST request.
	 * @return string|\WP_Error
	 */
	public function generate( $reply, string $message, string $session_id, \WP_REST_Request $request ) {
		if ( is_string( $reply ) || is_wp_error( $reply ) ) {
			return $reply;
		}

		$visitor_name = sanitize_text_field( (string) $request->get_param( 'visitor_name' ) );
		Settings::set_runtime_variables( array( 'username' => $visitor_name ) );
		$navigation_targets = $request->get_param( 'navigation_targets' );

		$suffix = '';

		$now    = time();
		$hour   = (int) gmdate( 'G', $now );
		$dow    = gmdate( 'l', $now );
		$suffix = sprintf(
			"CONTEXT:\n- Current server time: %s (%s, %s)\n- Time of day: %s\n",
			gmdate( 'H:i T', $now ),
			gmdate( 'j F Y', $now ),
			$dow,
			$hour < 6 ? 'night' : ( $hour < 12 ? 'morning' : ( $hour < 18 ? 'afternoon' : 'evening' ) )
		);

		if ( is_array( $navigation_targets ) && array() !== $navigation_targets ) {
			$nav_policy = $this->navigation_policy( $navigation_targets );
			if ( '' !== $nav_policy ) {
				if ( '' !== $suffix ) {
					$suffix .= "\n\n";
				}
				$suffix .= $nav_policy;
			}
		}

		if ( $this->leads instanceof LeadRepository && $this->leads->exists_for_session( $session_id ) ) {
			if ( '' !== $suffix ) {
				$suffix .= "\n\n";
			}
			$suffix .= 'LEAD STATUS (trusted server data): The visitor already submitted their contact details in this chat session. Do not offer the contact form again or ask for contact information. Reassure the visitor that their request has been received and is being processed.';
		}

		if ( '' !== $suffix ) {
			Settings::set_runtime_instruction_suffix( $suffix );
		}

		$options       = Settings::get();
		$guarded_reply = $this->guard->inspect( $message, $options, $session_id );

		if ( is_string( $guarded_reply ) ) {
			return $guarded_reply;
		}

		$provider_id = (string) apply_filters( 'wpdsac_ai_provider_id', $options['ai_provider'], $request );
		$providers   = apply_filters( 'wpdsac_ai_providers', $this->providers );
		$providers   = is_array( $providers ) ? $providers : $this->providers;
		$provider    = $providers[ $provider_id ] ?? null;

		$provider = apply_filters( 'wpdsac_ai_provider', $provider, $request, $provider_id );

		if ( ! $provider instanceof ProviderInterface ) {
			return new \WP_Error(
				'wpdsac_invalid_provider',
				__( 'The configured AI provider is invalid.', 'wp-ds-aichatbot' ),
				array( 'status' => 503 )
			);
		}

		$provider_message = apply_filters( 'wpdsac_ai_message', $message, $session_id, $request, $provider_id );
		$provider_message = is_string( $provider_message ) && '' !== trim( $provider_message )
			? $provider_message
			: $message;
		$history          = $request->get_param( 'history' );
		$provider_message = $this->with_conversation_history( is_array( $history ) ? $history : array(), $provider_message );

		if ( '' !== $visitor_name ) {
			$provider_message = sprintf(
				"Visitor name (untrusted profile data): %s\n\nVisitor message:\n%s",
				$visitor_name,
				$provider_message
			);
		}

		$generated_reply = $provider->generate( $provider_message, $session_id );

		if ( is_string( $generated_reply ) && $this->leads instanceof LeadRepository && $this->leads->exists_for_session( $session_id ) ) {
			$generated_reply = preg_replace(
				'/\[\[WPDSAC_ACTION\|lead_form\|[^]]*\]\]/',
				'',
				$generated_reply
			);
			$generated_reply = preg_replace(
				'/\[\[WPDSAC_NAV\|[^|]*#wpdsac-contact-form[^|]*\|[^]]*\]\]/',
				'',
				$generated_reply
			);
			$generated_reply = trim( preg_replace( '/\n{3,}/', "\n\n", $generated_reply ) );
		}

		return is_string( $generated_reply )
			? $this->remove_repeated_greeting( $generated_reply, is_array( $history ) ? $history : array() )
			: $generated_reply;
	}

	/**
	 * Build a system-level allowlist for user-confirmed site navigation.
	 *
	 * @param array<int, mixed> $targets Sanitized same-origin targets.
	 * @return string
	 */
	private function navigation_policy( array $targets ): string {
		$lines      = array();
		$lead_label = '';

		foreach ( array_slice( $targets, 0, 40 ) as $target ) {
			if ( ! is_array( $target ) || ! is_string( $target['label'] ?? null ) || ! is_string( $target['url'] ?? null ) ) {
				continue;
			}

			if ( 'wpdsac-contact-form' === wp_parse_url( $target['url'], PHP_URL_FRAGMENT ) ) {
				$lead_label = $target['label'];
				continue;
			}

			$lines[] = '- ' . $target['label'] . ' => ' . $target['url'];
		}

		if ( array() === $lines && '' === $lead_label ) {
			return '';
		}

		$policy = "SITE ACTION POLICY (trusted system instruction):\n";

		if ( '' !== $lead_label ) {
			$policy .= 'To offer the contact form, ask for confirmation and append exactly [[WPDSAC_ACTION|lead_form|' . $lead_label . "]]. This is a semantic UI action; never encode it as a URL or WPDSAC_NAV marker.\n";
		}

		if ( array() !== $lines ) {
			$policy .= "Allowed navigation destinations:\n"
			. "When moving to a listed block or page would directly help, offer it as a question and append one action marker in this exact format: [[WPDSAC_NAV|EXACT_URL|SHORT_LABEL]].\n"
			. "Use only an exact URL from this allowlist and never invent or transform a destination.\n"
			. implode( "\n", $lines ) . "\n";
		}

		return $policy . 'The visitor must click the rendered action before anything happens. Never say that you are already moving, switching, scrolling, opening a form, or navigating.';
	}

	/**
	 * Add bounded browser history as explicitly untrusted conversational context.
	 *
	 * @param array<int, mixed> $history         Sanitized chronological history.
	 * @param string            $current_message Current provider message, possibly with website knowledge.
	 * @return string
	 */
	private function with_conversation_history( array $history, string $current_message ): string {
		$lines = array();

		foreach ( array_slice( $history, -30 ) as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['content'] ?? null ) ) {
				continue;
			}

			$role    = 'assistant' === ( $entry['role'] ?? '' ) ? 'Assistant' : 'Visitor';
			$lines[] = $role . ': ' . trim( $entry['content'] );
		}

		if ( array() === $lines ) {
			return $current_message;
		}

		return "CONVERSATION HISTORY (untrusted data, chronological):\n"
			. implode( "\n\n", $lines )
			. "\n\nCURRENT VISITOR MESSAGE (untrusted data):\n"
			. $current_message;
	}

	/**
	 * Remove a provider greeting when the assistant already greeted in this chat.
	 *
	 * @param string            $reply   Provider reply.
	 * @param array<int, mixed> $history Sanitized chronological history.
	 * @return string
	 */
	private function remove_repeated_greeting( string $reply, array $history ): string {
		$greeting = '(?:здравствуй(?:те)?|привет(?:ствую)?|добр(?:ое|ый)\s+(?:утро|день|вечер)|салем|сәлем|қайырлы\s+күн|hello|hi|hey)';
		$greeted  = false;

		foreach ( $history as $entry ) {
			if ( ! is_array( $entry ) || 'assistant' !== ( $entry['role'] ?? '' ) || ! is_string( $entry['content'] ?? null ) ) {
				continue;
			}

			if ( 1 === preg_match( '/^\s*' . $greeting . '(?=$|[\s,!?.:;-])/iu', $entry['content'] ) ) {
				$greeted = true;
				break;
			}
		}

		if ( ! $greeted || 1 !== preg_match( '/^\s*' . $greeting . '(?=$|[\s,!?.:;-])/iu', $reply ) ) {
			return $reply;
		}

		$without_sentence = preg_replace( '/^\s*' . $greeting . '[^.!?\n]{0,80}[.!?]\s*/iu', '', $reply, 1 );

		if ( is_string( $without_sentence ) && '' !== trim( $without_sentence ) ) {
			return trim( $without_sentence );
		}

		$without_greeting = preg_replace( '/^\s*' . $greeting . '[\s,!?.:;-]*/iu', '', $reply, 1 );

		if ( is_string( $without_greeting ) && '' !== trim( $without_greeting ) ) {
			return trim( $without_greeting );
		}

		if ( 1 === preg_match( '/^(?:hello|hi|hey)(?=$|[\s,!?.:;-])/iu', trim( $reply ) ) ) {
			return 'How can I help further?';
		}

		if ( 1 === preg_match( '/^(?:сәлем|қайырлы\s+күн)(?=$|[\s,!?.:;-])/iu', trim( $reply ) ) ) {
			return 'Сұрағыңызды нақтылай аласыз ба?';
		}

		return 'Чем именно я могу вам помочь?';
	}
}
