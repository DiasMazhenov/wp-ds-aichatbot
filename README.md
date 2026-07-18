# WP DS AI Chatbot

Безопасный расширяемый AI-чатбот для WordPress с глобальным режимом, shortcode и интеграцией Elementor.

Проект реализуется с чистого листа. Коммерческий код, assets, лицензирование и телеметрия исходного Helper не копируются.

## Текущий статус

Реализованы plugin bootstrap, настройки, единый renderer, shortcode, Elementor widget, безопасный публичный REST-контур и первый non-streaming OpenAI Responses provider.

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
- `POST /wp-json/wp-ds-aichatbot/v1/chat` — принимает `session` и `message` до 2000 символов и возвращает ответ провайдера.

## OpenAI

Рекомендуемый способ — задать ключ только на сервере в `wp-config.php`:

```php
define( 'WPDSAC_OPENAI_API_KEY', 'ваш-ключ' );
```

Также поддерживается environment variable `WPDSAC_OPENAI_API_KEY`. В крайнем случае ключ можно сохранить через `Настройки → DS AI Chatbot`: поле write-only, сохранённое значение обратно не отображается. Настройки модели, инструкций и максимального ответа находятся там же.

Дефолтная модель — `gpt-5.6-sol`; её можно заменить в админке, если модель недоступна проекту или нужен более экономичный вариант.

Запросы отправляются через WordPress HTTP API на OpenAI Responses API с `store: false`. Расширения доступны через фильтры `wpdsac_ai_provider` и `wpdsac_openai_request_body`; sanitized diagnostics ошибок — через action `wpdsac_openai_error`.

Без настроенного API key endpoint чата ожидаемо возвращает HTTP 503.
