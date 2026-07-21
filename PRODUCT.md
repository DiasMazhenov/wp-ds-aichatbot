# Product

## Register

product

## Users

WordPress site owners and their visitors. Site owners install the plugin to provide AI-powered customer support directly on their website. Visitors interact with the chatbot to get answers about products, services, or to leave contact requests.

## Product Purpose

A lightweight, embeddable AI chatbot widget for WordPress sites. It connects to multiple AI providers (OpenAI, Anthropic, Gemini, DeepSeek, OpenRouter) and provides real-time conversational support with knowledge base integration, lead collection, and full customization through WordPress admin.

## Brand Personality

Professional, trustworthy, unobtrusive. The chatbot should feel like a helpful assistant — present when needed, invisible when not. Clean, modern UI that adapts to the host site's design through extensive theming options.

## Anti-references

- Generic SaaS chat widgets with aggressive popups and forced engagement
- Chatbots that dominate the page or force interaction
- Overly playful/toylike design with bouncing animations or cartoon avatars
- Heavy, bloated chat widgets that slow down the page

## Design Principles

1. **Non-intrusive**: The chatbot enhances the page without competing for attention
2. **Adaptable**: Full theming system through CSS custom properties — works with any site design
3. **Accessible**: Keyboard navigation, ARIA labels, screen reader support, reduced motion respect
4. **Performant**: Minimal footprint, lazy-loaded, no heavy dependencies
5. **Secure**: Server-side API key storage, nonce verification, rate limiting, data sanitization

## Accessibility & Inclusion

- WCAG 2.1 AA compliance target
- Keyboard navigable (Tab, Enter, Escape)
- ARIA labels on interactive elements
- `aria-live="polite"` on message containers
- `prefers-reduced-motion` respected for all animations
- Screen reader friendly status announcements
