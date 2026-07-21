# Design System

## Theme

Light mode default with full dark mode support via CSS custom properties. The widget adapts to the host site's design through admin-configurable appearance settings.

## Colors

### Primary palette

| Token | Role | Default | OKLCH approximation |
|---|---|---|---|
| `--wpdsac-accent` | Primary action, links, active states | `#2563eb` | oklch(0.55 0.2 250) |
| `--wpdsac-accent-text` | Text on accent backgrounds | `#ffffff` | — |
| `--wpdsac-surface` | Card/panel background | `#ffffff` | — |
| `--wpdsac-text` | Primary body text | `#172033` | oklch(0.2 0.02 260) |
| `--wpdsac-muted` | Secondary/supporting text | `#64748b` | oklch(0.5 0.03 250) |
| `--wpdsac-border` | Subtle borders, dividers | `#dce2ee` | oklch(0.9 0.01 260) |

### Message colors

| Token | Role | Default |
|---|---|---|
| `--wpdsac-bot-message` | Bot message bubble background | `#eff4ff` |
| `--wpdsac-bot-text` | Bot message text | `#172033` |
| `--wpdsac-user-message` | User message bubble background | `#2563eb` |
| `--wpdsac-user-text` | User message text | `#ffffff` |

### Input & controls

| Token | Role | Default |
|---|---|---|
| `--wpdsac-input` | Input field background | `#ffffff` |
| `--wpdsac-input-text` | Input field text | `#172033` |
| `--wpdsac-send` | Send button background | `#2563eb` |
| `--wpdsac-send-text` | Send button icon/text | `#ffffff` |
| `--wpdsac-quick-bg` | Quick action chip background | `#ffffff` |
| `--wpdsac-quick-text` | Quick action chip text | `#2563eb` |
| `--wpdsac-quick-border` | Quick action chip border | `#b8c8ea` |

## Typography

### Font stack

```css
--wpdsac-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
```

System font stack for maximum compatibility and native feel.

### Scale

| Element | Size | Weight |
|---|---|---|
| Body / messages | `16px` (configurable) | 400 |
| Toggle title | inherited | 700 |
| Quick actions | `13px` | 650 |
| Status text | `12px` | 400 |
| Code blocks | `0.88em` | 400 |

## Spacing

| Token | Value | Usage |
|---|---|---|
| `--wpdsac-panel-padding` | `18px` | Panel inner padding |
| `--wpdsac-msg-pt/pb` | `20px` | Messages area vertical padding |
| `--wpdsac-msg-px` | `3px` | Messages area horizontal padding |
| `--wpdsac-panel-mt` | `0` | Gap between toggle and panel |
| `--wpdsac-offset-x/y` | `24px` | Widget distance from viewport edge |

## Border radius

| Token | Value | Usage |
|---|---|---|
| `--wpdsac-radius` | `18px` | Panel outer radius |
| `--wpdsac-toggle-radius` | `18px` | Toggle button radius |
| `--wpdsac-message-radius` | `14px` | Message bubble radius |
| `--wpdsac-input-radius` | `18px` | Input field radius |
| `--wpdsac-quick-radius` | `16px` | Quick action chip radius |

## Dimensions

| Token | Value |
|---|---|
| `--wpdsac-width` | `380px` |
| `--wpdsac-height` | `500px` |
| `--wpdsac-messages-height` | `320px` |
| `--wpdsac-launcher-size` | `60px` |
| `--wpdsac-font-size` | `16px` |

## Shadows

```css
box-shadow: 0 10px 30px rgb(23 32 51 / var(--wpdsac-shadow-opacity));
```

Shadow opacity configurable via `--wpdsac-shadow-opacity` (default `16%`).

## Component patterns

### Chat widget

- Fixed position (bottom-right or bottom-left configurable)
- Collapsed: circular launcher with avatar/icon
- Expanded: panel with header toggle, message area, quick actions, input
- Header: title (left) → status (center, flex grow) → avatar icon (right)

### Message bubbles

- Bot: left-aligned, light background (`--wpdsac-bot-message`), left avatar
- User: right-aligned, accent background (`--wpdsac-user-message`), no avatar
- Asymmetric border-radius (sharp bottom-left for bot, sharp bottom-right for user)
- Markdown rendered in bot messages (bold, italic, code, lists, headings, blockquotes)

### Quick actions

- Horizontal flex-wrap row of chips
- Configurable label, up to 8 admin-defined actions
- Call and Request actions built-in

### Input

- Pill-shaped input with send button inside
- Enter to send, Shift+Enter for newline (if multiline)
- Typing indicator in status area

## Animation

- Panel open/close: CSS transition on height/opacity
- Messages: smooth scroll to latest
- Reduced motion: `@media (prefers-reduced-motion: reduce)` disables transitions
- Intro bubble: delayed appearance with fade

## Dark mode

All tokens are overridable. The admin appearance panel provides light/dark theme toggle with full color customization for every element.
