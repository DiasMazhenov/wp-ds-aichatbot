=== WP DS AI Chatbot ===
Contributors: diasmazhenov
Tags: ai, chatbot, elementor, openai, anthropic, gemini
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.26
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extensible multi-provider AI chatbot for WordPress and Elementor.

== Description ==

WP DS AI Chatbot provides one secure chatbot renderer for shortcode, global display, and Elementor. It supports OpenAI, Anthropic Claude, Google Gemini, OpenRouter, DeepSeek, and the provider-agnostic WordPress AI Client on WordPress 7.0 or newer.

API keys remain server-side. Public requests use signed sessions, atomic rate limits, an in-flight session lock, and a configurable rolling 24-hour request budget.

The Design tab includes separate colors for the header, panel, assistant and visitor messages, input and send button; layout, typography, radii, spacing, shadow and launcher size controls; and open/collapsed live preview. The same appearance applies to global, shortcode, and Elementor chatbots. The collapsed chatbot is a compact circular launcher.

Optional lead collection adds a name/email/phone form inside the chat with required consent, editable quick actions, bounded retention, scheduled cleanup, rate limits, email notifications with the submitted transcript, and WordPress privacy export/erasure.

An optional knowledge layer indexes published WordPress pages, posts, administrator-managed FAQs, WooCommerce products, and selected text PDFs into a dedicated table. Relevant bounded fragments are added as untrusted reference context before any configured AI provider is called.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate WP DS AI Chatbot.
3. Open DS AI Chatbot > Settings and select a provider.
4. Configure the provider key through wp-config.php, an environment variable, or the write-only settings field.
5. Add the [ds_ai_chatbot] shortcode, enable global display, or use the Elementor widget.

== Changelog ==

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
