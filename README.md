# WP DS AI Chatbot

Безопасный расширяемый AI-чатбот для WordPress с глобальным режимом, shortcode и интеграцией Elementor.

Проект реализуется с чистого листа. Коммерческий код, assets, лицензирование и телеметрия исходного Helper не копируются.

## Текущий статус

Реализованы plugin bootstrap, настройки, единый renderer, shortcode, Elementor widget и безопасный публичный REST-контур. OpenAI provider пока не подключён.

Подробности: [Plan.md](Plan.md) и [Context.md](Context.md).

## Требования

- WordPress 6.6+
- PHP 7.4+
- Elementor 3.19+ — опционально

## Shortcode

```text
[ds_ai_chatbot]
[ds_ai_chatbot title="Поддержка" welcome_message="Чем можем помочь?"]
```

## Разработка

```bash
composer install
composer lint
```

## REST API

- `POST /wp-json/wp-ds-aichatbot/v1/session` — выдаёт подписанную сессию на 24 часа.
- `POST /wp-json/wp-ds-aichatbot/v1/chat` — принимает `session` и `message` до 2000 символов.

До подключения AI provider endpoint чата ожидаемо возвращает HTTP 503. Ответ подключается через фильтр `wpdsac_chat_reply`.
