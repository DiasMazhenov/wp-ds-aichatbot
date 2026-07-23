=== WP DS AI Chatbot ===
Contributors: diasmazhenov
Tags: ai, chatbot, elementor, openai, anthropic, gemini
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.107
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extensible multi-provider AI chatbot for WordPress and Elementor.

== Description ==

WP DS AI Chatbot provides one secure chatbot renderer for shortcode, global display, and Elementor. It supports OpenAI, Anthropic Claude, Google Gemini, OpenRouter, DeepSeek, and the provider-agnostic WordPress AI Client on WordPress 7.0 or newer.

API keys remain server-side. Public requests use signed sessions, atomic rate limits, an in-flight session lock, and a configurable rolling 24-hour request budget.

Each request includes a sanitized, bounded copy of the current browser chat history so every provider can continue the conversation without greeting or introducing itself again. The history remains available while the visitor navigates between pages in the same browser tab. This context is not stored separately on the server when optional conversation logging is disabled.

The Design tab includes separate colors for the header, panel, assistant and visitor messages, input and send button; layout, typography, radii, spacing, shadow and launcher size controls; and open/collapsed live preview. New AI replies can appear word by word while their message bubble grows naturally; speed and replay preview controls are included, and reduced-motion preferences bypass the effect. The same appearance applies to global, shortcode, and Elementor chatbots. The collapsed chatbot is a compact circular launcher.

Contact collection appears only after the visitor explicitly requests contact or presses the request action. The assistant asks for a required name and phone number, while consent, bounded retention, scheduled cleanup, rate limits, administrator email notifications, and a bounded transcript remain enforced. Optionally, one separately titled transcript email is sent after an unfinished conversation becomes inactive; its timer is postponed after every reply and cancelled when that session submits a contact request.

An optional knowledge layer indexes published WordPress pages, posts, administrator-managed FAQs, WooCommerce products, and selected text PDFs into a dedicated table. Relevant bounded fragments are added as untrusted reference context before any configured AI provider is called.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate WP DS AI Chatbot.
3. Open DS AI Chatbot > Settings and select a provider.
4. Configure the provider key through wp-config.php, an environment variable, or the write-only settings field.
5. Add the [ds_ai_chatbot] shortcode, enable global display, or use the Elementor widget.

== Changelog ==

= 0.5.107 =

* Accessibility: focus trap keeps keyboard navigation within chat panel when open.
* Accessibility: Escape key closes lead modal dialog (standard dialog pattern).
* Accessibility: aria-label on chat section and panel for screen readers.
* Accessibility: aria-live="polite" and role="log" on messages container for new message announcements.
* Accessibility: Escape key collapses expanded chat when no modal is open.

= 0.5.106 =

* Security: admin URLs blocked from AI navigation (client/server/provider triple layer).
* Shared UrlDenylist: percent-encode, dot-segment, backslash, case-insensitive protection.
* Frontend: document-wide link collection removed, restricted to public DOM areas.
* PromptGuard: anti-leak rules deny WP admin, database, plugins, server config access.

= 0.5.105 =

* Settings link added to plugin row actions.
* Author URI set to https://mazhenov.kz/ in plugin header and row meta.

= 0.5.104 =

* Composer background removed from CSS, inline styles, admin settings, and defaults.
* Removed composer_bg_color and composer_bg_opacity from all settings schemas.

= 0.5.103 =

* chat.js restructured with 12 logical section headers for readability.
* Visual regression assertions in CSS integration test: panel blur, messages transparency, scrollbar, typing indicator.

= 0.5.102 =

* Email field added to lead form (optional, validated).
* Webhook URL setting: POST with lead data on new submission.
* LeadController passes email to repository and notifier.

= 0.5.101 =

* Typing indicator (3 animated dots) while waiting for AI response.
* Removed dead CSS variables: composer-blur, messages-height.
* Cleaned unused code from Appearance inline_style.

= 0.5.100 =

* Preview stage background changed to black (#000) for better glass-effect visibility.
* Composer background is now configurable again (color and opacity) via Bottom panel settings.
* Panel blur and opacity controls remain available in Message animation settings.

= 0.5.99 =

* Fixed: preview form changed to div to avoid nested forms breaking the Save Settings button.

= 0.5.98 =

* Admin preview DOM now fully matches real chat: bot message as div, SVG avatar fallback, context-actions, id/for, maxlength, autocomplete.

= 0.5.97 =

* Admin preview now fully mirrors the real chat DOM: composer actions, form with SVG send icon, screen-reader label.

= 0.5.96 =

* Composer: background property removed entirely.
* Messages: background #11182763 and border-radius 10px, both configurable in settings.

= 0.5.95 =

* Fixed: messages background mode defaults to solid instead of transparent so the configured color applies.
* Composer background removed, messages bg #11182763, panel blur 2px, centered send icon.

= 0.5.94 =

* Composer background removed (opacity defaults to 0).
* Messages background set to semi-transparent dark (#11182763).
* Panel glass blur reduced to 2px.
* Send button SVG icon centered vertically and horizontally.

= 0.5.93 =

* Glass-morphism panel: semi-transparent white background with backdrop-filter blur, adjustable opacity/blur/border via settings.
* Messages area: permanently transparent background, hidden scrollbar.
* Composer: light semi-transparent background, optional separator line above.
* New panel settings: glass blur (0-40 px), background opacity (0-100%), border opacity (0-100%).
* New composer setting: separator line toggle.

= 0.5.92 =

* Restructured DOM: composer panel moved outside conversation.
* New Message window settings section: background mode, blur, saturation, radius, border, glare, shadow, padding.
* Bottom panel simplified to background, opacity, radius, padding, and gap.

= 0.5.91 =

* New unified bottom composer panel wraps quick actions and input form together with glass-morphism styling.
* Transparent message history background option with configurable color and opacity.
* 15 new visual controls in a dedicated Bottom panel settings section: background, blur, radius, padding, gap, border, shadow, and spacing.
* Quick buttons can now scroll horizontally when they do not fit in one row.
* Live preview shows the composer with messages, avatar, and buttons; all new controls update instantly.
*
= 0.5.90 =

* Re-engage state machine: handleReengageState returns explicit stop/retry/schedule_next/none; finally only clears in-flight flag.
* Terminal states clear the timer, save terminalReason, and block visibilitychange; a new /chat exchange resets provider_error.
* REST /reengage returns rate_limited, daily_limit, request_in_progress, empty_reply as safe codes instead of default ok.
* Cooldown retries use exact retry_after; only one active timer per chat instance.
* Contextual quick replies stored per chat session (wpdsacReengage:{sessionHash}:{chatId}) with 24h TTL; cleared on button click, manual input, lead form, and history reset.
* CSS selector audit rewritten as a strict allowlist (only .wpdsac-chat rooted, body > .wpdsac-chat, body.wpdsac-modal-open, and [data-wpdsac-navigation-highlight]).
* QuickReplyParser uses gettext fallback instead of hardcoded Russian; ChatController drops unused ReengageService import.
*
= 0.5.89 =

* Add repository-level OpenCode instructions and an explicit project configuration so coding agents must follow the local preflight, version synchronization, CI diagnosis, security, quick-action placement, and frontend CSS isolation contracts before pushing.

= 0.5.88 =

* Correct the CSS audit to allow the existing scoped `body > .wpdsac-chat` fixed-position rules while retaining the documented chatbot isolation contract.

= 0.5.87 =

* Re-engage count incremented only after successful non-empty AI reply — cooldown set before, count after.
* Server-side activity flag prevents re-engage before a real chat exchange; history alone is not trusted.
* Machine-readable reengage state returned in every /reengage response; JS respects disabled/max_reached/cooldown codes.
* Toggle open/close no longer disables re-engagement; closed chat stores quick_replies for restoration on open.
* QuickReplyParser returns a safe fallback sentence when the AI reply contains only markers with no text.
* QUICK REPLY VARIANTS policy added to the re-engage provider path; CSS --wpdsac-panel-bg → --wpdsac-surface.
* Strengthened CSS selector audit: bare tag, global, Elementor-scoped and universal selectors are all forbidden.

= 0.5.85 =

* Separate re-engagement endpoint (/reengage) with server-only guard checks (cooldown, max count, lead status, reengage_instructions).
* Server-side QA marker parsing via QuickReplyParser; clean reply text with structured quick_replies array in REST response.
* Contextual reply buttons rendered in a dedicated container above the input bar, not inside message bubbles.
* SessionStorage-based re-engagement state with dueAt, count, lastActivity; activity-tracking to defer timers; visibility-change aware.

= 0.5.82 =

* Isolated every frontend component selector under the chatbot root and added a regression guard against site-wide CSS leakage.
* Added natural time-aware greetings with the `{time_greeting}` token and automatic handling of common Russian choice notation.

= 0.5.81 =
* Add configurable word-by-word animation for new AI replies with naturally growing message bubbles, immediate full-history persistence, replay preview, and reduced-motion fallback.
* Add one debounced transcript email for conversations without contact details, with a separate subject, configurable inactivity delay, bounded temporary storage, retry handling, and automatic cancellation when the session becomes a lead.

= 0.5.80 =
* Add provider-independent human writing rules that avoid em dashes, canned assistant phrases, filler, repeated summaries, and excessive formatting; normalize long dashes in provider output.
* Add five collapsed-launcher animation modes with three gradient colors, duration, intensity, size, and an accessible live preview.
* Disable launcher motion for reduced-motion preferences and pause it during direct interaction.

= 0.5.79 =
* Rename the general Title field to Chatbot name and explain how the value is used.
* Supply the configured public chatbot name to every provider so the assistant introduces itself consistently once per conversation.

= 0.5.78 =
* Always render readable assistant and visitor sample messages in the appearance live preview, including a safe fallback when the welcome message is empty.
* Add live typography controls for base and message line height, header size and weight, message size, input size, and send-button size.

= 0.5.77 =
* Decouple knowledge embeddings from the chat provider with Auto, OpenAI, Gemini, and OpenRouter adapters plus keyword fallback for unsupported providers.
* Invalidate stored vectors safely when the embeddings provider or model changes.
* Clip scaled chatbot avatars inside fixed circular frames in static messages, dynamic replies, and administration previews.

= 0.5.76 =
* Fix stale quick-action and site-action policy assertions that caused every GitHub Actions run to fail before WordPress Playground started.
* Derive integration version expectations from the plugin source to prevent future version drift.

= 0.5.75 =
* Stop exposing provider exceptions, database diagnostics, SQL fragments, and visitor data through public REST responses and browser logs.
* Save leads through the portable WordPress database API instead of direct mysqli calls.
* Generate knowledge embeddings in bounded background batches and skip semantic API requests until stored vectors exist.
* Synchronize integration expectations and package metadata with the current plugin and database versions.

= 0.5.36 =
* Keep a safe popup-to-chat association after moving the lead modal outside Elementor containers.
* Fix lead submission failing with a null `querySelectorAll` error.

= 0.5.35 =
* Open the request form through a dedicated semantic action instead of an Elementor or navigation URL.
* Move the lead modal to the document body so Elementor container overflow and transforms cannot clip it.
* Keep every unclicked quick button visible instead of clipping additional rows.

= 0.5.34 =
* Fixed AI-generated contact actions so they open the lead form instead of attempting page navigation.
* Added a built-in contact-form navigation target and safer handling of malformed page anchors.

= 0.5.33 =
* Add up to 8 custom quick buttons that can send a prepared message or open a safe URL; the built-in request button always opens the modal.
* Add visual controls for quick-button colors, border, font size, padding, radius, and gap.
* Move the contact form into an accessible centered modal with focus management and keyboard dismissal.
* Keep the expanded chat at a configured fixed height with one auto-scrolling message viewport.
* Let the assistant propose allowlisted same-origin blocks and pages through a user-confirmed navigation action.
* Refine spacing, hierarchy, focus states, compact actions, and responsive behavior after a UI/UX audit.

= 0.5.32 =
* Recognize a visitor name and phone number in chat messages, then prefill the consent form instead of sending the contact data to the AI provider.
* Repair the lead database schema automatically and retry once when persistence fails.
* Keep the contact form reusable after submission and accept common phone punctuation.
* Fall back to the WordPress administrator email and report mail failures in debug logs and the browser console.

= 0.5.31 =
* Remove the mandatory pre-chat name gate and open the conversation immediately.
* Show the name and required phone form only after explicit contact intent, with an assistant prompt before it.
* Place quick actions directly above the message input and hide each action after it is used.
* Add editable Call and Request URLs; an empty Request URL keeps the built-in contact form.
* Remove the Contact form in chat toggle and the visitor email field.

= 0.5.30 =
* Deterministically remove a repeated provider greeting after the assistant has already greeted in the current conversation.
* Expire browser conversation memory after 24 hours so a new-day greeting is allowed again.

= 0.5.29 =
* Send bounded current-session conversation history to every AI provider and retain it across page navigation in the same tab.
* Preserve conversational continuity and prevent repeated greetings or introductions.
* Treat all browser-provided history as untrusted data and avoid new server-side storage.

= 0.5.28 =
* Keep the Send button at its natural text width so its label is never clipped.
* Let the message field consume the remaining row width and retain the stacked mobile layout.

= 0.5.27 =
* Show the configurable chatbot avatar beside every assistant message and in the name prompt.
* Add delayed, scroll, exit-intent, immediate, and disabled introductory bubble triggers.
* Apply visitor-name templates to the welcome message and improve responsive chat layout.
* Give the introductory bubble a translucent blurred surface.

= 0.5.26 =
* Keep the introductory bubble text inside its responsive bounds at large font sizes.
* Add selectable quiet Web Audio reply sounds with an accessible No sound option and settings preview.
* Pass the visitor name to AI requests and support the safe {username} instruction template tag.

= 0.5.25 =
* Add an editable pre-chat name prompt, quick Call and Leave a request actions, and an inline contact form triggered by button or contact intent.
* Store the submitted phone and request text, then email the configured recipient a plain-text lead summary and bounded chat transcript.
* Add a persistent light/dark mode switch across Settings, Knowledge base, and Leads administration screens.

= 0.5.24 =
* Restore valid Settings API table markup so labels, controls, descriptions, and provider visibility render correctly.
* Improve form-card spacing, checkbox alignment, responsive layout, and keyboard focus styling across settings tabs.

= 0.5.23 =
* Index rendered Gutenberg blocks, Elementor widget content, and every published public post type even while retrieval is disabled.
* Rebuild existing knowledge automatically after extraction upgrades and include exact source URLs when visitors request links.
* Add indexed phone, WhatsApp, and Telegram fields plus safe clickable links in chatbot replies.

= 0.5.22 =
* Merge FAQ management into the Knowledge base page and remove its separate admin menu item.
* Add a ten-row administrator text source for instructions and additional searchable knowledge.

= 0.5.21 =
* Add server-side prompt-injection, model-probing, and configurable off-topic request protection.
* Apply an immutable security policy to every AI provider and allow a custom localized refusal message.

= 0.5.20 =
* Always remove stale hidden and inline display styles from the selected provider row.
* Simplify provider visibility to use only the explicit provider field wrappers.

= 0.5.19 =
* Add explicit DOM markers around every provider-specific field and hide their complete table rows.
* Capture typed credentials in temporary page memory before browser extensions can clear password inputs.
* Add safe credential preflight diagnostics without logging the credential value.

= 0.5.18 =
* Keep Settings as the first and default plugin submenu ahead of the FAQ post type.
* Hide inactive provider rows with structure-independent CSS and an inline JavaScript fallback.
* Add a JSON credential transport and a dedicated non-autoloaded provider credential store.
* Keep legacy individual credential options as a backwards-compatible fallback.

= 0.5.17 =
* Explicitly transport entered provider credentials in a structured AJAX payload.
* Keep compatibility with the legacy top-level credential fields.
* Report whether a credential reached the server and whether storage was verified without exposing the key.

= 0.5.16 =
* Add safe provider diagnostics to the AI settings screen and browser console.
* Verify the selected provider credential after AJAX saves without exposing the secret.
* Reliably hide inactive provider fields and mask stored API keys with dots.
* Bust the menu icon cache and enforce its 20 by 20 pixel size across all administration screens.

= 0.5.15 =
* Constrain the custom WordPress administration menu icon to its native 20 by 20 pixel size.

= 0.5.14 =
* Add a customizable message input placeholder shared by global, shortcode, and Elementor chatbots.

= 0.5.13 =
* Consolidate all plugin pages under one top-level WordPress administration menu.
* Add a complete Russian interface translation.
* Redesign the settings screen and restore side-by-side appearance controls with a sticky live preview.
* Save settings securely through AJAX without reloading the page.

= 0.5.12 =
* Show the current version directly in the bold plugin name on the WordPress Plugins screen.

= 0.5.11 =
* Show the current plugin version beside its name across administrative screens and menus.
* Expand chat design controls with grouped colors, typography, layout, shapes, and live states.
* Render the collapsed chatbot as a configurable circular launcher.

= 0.5.10 =
* Add native DeepSeek Chat Completions support with optional thinking mode.
* Show an explicit saved status for write-only API key fields.

= 0.5.9 =
* Locked development dependencies against the minimum PHP 7.4 platform.
* Installed the production PDF runtime before WordPress Playground integration tests.

= 0.5.8 =
* Added PHPUnit security and boundary tests on PHP 7.4 and 8.3.
* Added runtime verification of chat/lead rate limits and lifecycle scheduling.
* Added an isolated full uninstall test for plugin tables, options, and cron events.

= 0.5.7 =
* Added optional in-chat lead collection with explicit consent and a honeypot.
* Added lead retention, scheduled cleanup, protected administration, and rate limits.
* Added WordPress personal-data export and erasure for submitted contact details.

= 0.5.6 =
* Added explicit PDF selection and bounded text extraction from the Media Library.
* Added automatic public WooCommerce product knowledge through WooCommerce APIs.
* Added production-only Composer packaging for the PDF runtime.

= 0.5.5 =
* Added opt-in conversation logging with mandatory retention.
* Added WordPress privacy exporter and eraser integration.
* Added scheduled cleanup and one-way hashed session identifiers.

= 0.5.4 =
* Added administrator-managed structured knowledge entries.
* Added automatic FAQ synchronization with the shared knowledge index.

= 0.5.3 =
* Added a provider-agnostic Knowledge/RAG pipeline for WordPress pages and posts.
* Added automatic source synchronization and a protected manual reindex tool.
* Added a dedicated knowledge fragment table outside WordPress options.

= 0.5.2 =
* Added visual chat settings with a live admin preview.
* Added shared colors, width, font size, corner radius, position, and offset controls.

= 0.5.1 =
* Added enforced WordPress Coding Standards and PHP compatibility checks.
* Added reproducible WordPress and Elementor integration tests.
* Prepared internal database table identifiers with WordPress `%i` placeholders.

= 0.5.0 =
* Added multi-provider AI support.
* Added atomic in-flight request locks.
* Added a configurable rolling 24-hour request budget.
