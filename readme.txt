=== WP DS AI Chatbot ===
Contributors: diasmazhenov
Tags: ai, chatbot, elementor, openai, anthropic, gemini
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extensible multi-provider AI chatbot for WordPress and Elementor.

== Description ==

WP DS AI Chatbot provides one secure chatbot renderer for shortcode, global display, and Elementor. It supports OpenAI, Anthropic Claude, Google Gemini, OpenRouter, and the provider-agnostic WordPress AI Client on WordPress 7.0 or newer.

API keys remain server-side. Public requests use signed sessions, atomic rate limits, an in-flight session lock, and a configurable rolling 24-hour request budget.

The plugin settings include a live chat preview with shared colors, width, typography, corner radius, global position, and offsets. The same appearance applies to global, shortcode, and Elementor chatbots.

An optional knowledge layer indexes published WordPress pages and posts into a dedicated table. Relevant bounded fragments are added as untrusted reference context before any configured AI provider is called.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate WP DS AI Chatbot.
3. Open Settings > DS AI Chatbot and select a provider.
4. Configure the provider key through wp-config.php, an environment variable, or the write-only settings field.
5. Add the [ds_ai_chatbot] shortcode, enable global display, or use the Elementor widget.

== Changelog ==

= 0.5.4 =
* Added administrator-managed AI FAQ entries under Tools.
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
