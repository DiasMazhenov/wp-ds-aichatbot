=== WP DS AI Chatbot ===
Contributors: diasmazhenov
Tags: ai, chatbot, elementor, openai, anthropic, gemini
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extensible multi-provider AI chatbot for WordPress and Elementor.

== Description ==

WP DS AI Chatbot provides one secure chatbot renderer for shortcode, global display, and Elementor. It supports OpenAI, Anthropic Claude, Google Gemini, OpenRouter, and the provider-agnostic WordPress AI Client on WordPress 7.0 or newer.

API keys remain server-side. Public requests use signed sessions, atomic rate limits, an in-flight session lock, and a configurable rolling 24-hour request budget.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate WP DS AI Chatbot.
3. Open Settings > DS AI Chatbot and select a provider.
4. Configure the provider key through wp-config.php, an environment variable, or the write-only settings field.
5. Add the [ds_ai_chatbot] shortcode, enable global display, or use the Elementor widget.

== Changelog ==

= 0.5.1 =
* Added enforced WordPress Coding Standards and PHP compatibility checks.
* Added reproducible WordPress and Elementor integration tests.
* Prepared internal database table identifiers with WordPress `%i` placeholders.

= 0.5.0 =
* Added multi-provider AI support.
* Added atomic in-flight request locks.
* Added a configurable rolling 24-hour request budget.
