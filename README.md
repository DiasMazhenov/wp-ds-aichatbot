# WP DS AI Chatbot

Безопасный расширяемый AI-чатбот для WordPress с глобальным режимом, shortcode и интеграцией Elementor.

Проект реализуется с чистого листа. Коммерческий код, assets, лицензирование и телеметрия исходного Helper не копируются.

## Текущий статус

Реализованы plugin bootstrap, визуальные настройки с живым предпросмотром, единый renderer, shortcode, Elementor widget, безопасный публичный REST-контур и multi-provider AI слой без streaming.

## Внешний вид

В `Настройки → DS AI Chatbot → Chat appearance` доступны цвета, ширина, размер текста, скругление, позиция глобального виджета и отступы. Предпросмотр обновляется до сохранения. Один дизайн применяется к глобальному режиму, shortcode и Elementor widget.

## База знаний

Включите `Use website knowledge` в `Настройки → DS AI Chatbot`, затем откройте `Инструменты → DS AI Knowledge` и запустите индексацию. Опубликованные страницы, записи, ручные `AI FAQs`, публичные товары WooCommerce и выбранные PDF разбиваются на ограниченные фрагменты и сохраняются в отдельной таблице, а не в `wp_options`.

FAQ создаются через `Инструменты → AI FAQs`: заголовок используется как вопрос/тема, редактор — как проверенный ответ. Управление FAQ ограничено администраторами, записи не публикуются как отдельные frontend-страницы.

PDF выбираются явно на странице базы знаний из Media Library: максимум 50 файлов по 10 МБ. Поддерживаются PDF с текстовым слоем; сканы нужно предварительно обработать OCR. WooCommerce является опциональным: при его активации индексируются только опубликованные видимые товары, их описание, SKU, публичная цена, наличие и категории.

Перед запросом к OpenAI, Claude, Gemini, OpenRouter или WordPress AI Client плагин находит подходящие фрагменты и добавляет их как недоверенный справочный контекст. Изменённые опубликованные источники синхронизируются автоматически. Текущий retrieval использует локальный keyword ranking; semantic embeddings остаются отдельным опциональным адаптером.

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
npm ci
npm run test:integration
npm run test:elementor
```

Integration tests используют WordPress Playground: первый режим проверяет чистый WordPress, второй устанавливает актуальный Elementor и подтверждает регистрацию widget. Docker, локальный PHP и MySQL не требуются.

## Installable ZIP

```bash
bash scripts/build-zip.sh
```

Архив создаётся как `dist/wp-ds-aichatbot.zip`, содержит корневую папку `wp-ds-aichatbot/` и не включает CI, планы, tests, dev-зависимости и build scripts. GitHub Actions публикует такой же ZIP только после успешных PHP lint и WordPress/Elementor smoke tests.

## REST API

- `POST /wp-json/wp-ds-aichatbot/v1/session` — выдаёт подписанную сессию на 24 часа.
- `POST /wp-json/wp-ds-aichatbot/v1/chat` — принимает `session` и `message` до 2000 символов и возвращает ответ провайдера.

## AI-провайдеры

В `Настройки → DS AI Chatbot` можно выбрать:

- OpenAI — Responses API, модель `gpt-5.6-sol` по умолчанию;
- Anthropic Claude — Messages API, `claude-sonnet-4-6`;
- Google Gemini — stable Interactions API v1, `gemini-3.5-flash`;
- OpenRouter — OpenResponses API, включая модели разных производителей;
- WordPress AI Client — provider-agnostic режим WordPress 7.0+, использующий настроенный в WordPress AI-коннектор.

Рекомендуемый способ — задать нужные ключи как environment variables в панели хостинга или secret manager:

- `WPDSAC_OPENAI_API_KEY`;
- `WPDSAC_ANTHROPIC_API_KEY`;
- `WPDSAC_GEMINI_API_KEY`;
- `WPDSAC_OPENROUTER_API_KEY`.

Плагин также читает одноимённые PHP constants, заданные сервером. Не добавляйте значения ключей в отслеживаемые Git файлы. В крайнем случае ключ можно сохранить через страницу настроек: поля write-only, сохранённые значения обратно не отображаются. Модели, общие инструкции и максимальный ответ настраиваются там же.

Прямые запросы выполняются через WordPress HTTP API с запретом redirects. OpenAI, OpenRouter и Gemini получают `store: false`. Registry расширяется через `wpdsac_ai_providers`, выбор — через `wpdsac_ai_provider_id`/`wpdsac_ai_provider`, request body — через общий `wpdsac_ai_request_body` и provider-specific фильтры вида `wpdsac_gemini_request_body`. Sanitized diagnostics ошибок передаются в `wpdsac_ai_provider_error`; прежние OpenAI-интеграции сохраняют `wpdsac_openai_error`.

Без ключа выбранного direct provider или настроенного WordPress AI Connector endpoint чата ожидаемо возвращает HTTP 503.

## Защита бюджета

- session/IP rate limit хранится в атомарных database buckets;
- одновременно разрешён только один provider request на подписанную сессию;
- глобальный rolling-лимит `AI requests per 24 hours` по умолчанию равен `500`; значение `0` отключает его;
- максимальный размер ответа ограничивается общей настройкой `Maximum output tokens`;
- истёкшие rate-limit buckets и брошенные locks удаляются WP-Cron.

Параллельный запрос получает HTTP 409, исчерпанный локальный или глобальный лимит — HTTP 429 с `Retry-After`.

## Журналы и приватность

Conversation logging по умолчанию выключен. При включении успешные сообщения сохраняются в отдельных таблицах с обязательным сроком хранения от 1 до 365 дней. Идентификатор сессии хранится только как односторонний HMAC hash; IP-адреса в журнал не записываются.

Истёкшие разговоры удаляет ежедневный WP-Cron. Для авторизованных пользователей сообщения доступны стандартным WordPress-инструментам экспорта и удаления персональных данных. Анонимные журналы невозможно связать с email пользователя.
