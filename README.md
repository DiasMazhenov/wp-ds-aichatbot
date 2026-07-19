# WP DS AI Chatbot

Безопасный расширяемый AI-чатбот для WordPress с глобальным режимом, shortcode и интеграцией Elementor.

Проект реализуется с чистого листа. Коммерческий код, assets, лицензирование и телеметрия исходного Helper не копируются.

## Текущий статус

Реализованы plugin bootstrap, визуальные настройки с живым предпросмотром, единый renderer, shortcode, Elementor widget, безопасный публичный REST-контур и multi-provider AI слой без streaming.

## Внешний вид

Во вкладке `DS AI Chatbot → Настройки → Дизайн` параметры разделены на цвета, компоновку/типографику и элементы управления. Отдельно настраиваются шапка, панель, сообщения ассистента и посетителя, поле ввода, кнопка отправки, радиусы, отступы, высота истории, тень, шрифт, позиция и размер круглой свёрнутой кнопки. Текст в поле ввода задаётся в `Основные → Плейсхолдер поля сообщения` и сразу обновляется в live preview. Один дизайн применяется к global, shortcode и Elementor widget.

## База знаний

Включите `Использовать знания сайта` в `DS AI Chatbot → Настройки → База знаний`. Плагин постоянно индексирует все опубликованные публичные типы записей, отрисованные Gutenberg-блоки и содержимое Elementor-виджетов, даже когда retrieval временно выключен. Существующие страницы автоматически переиндексируются после обновления правил извлечения. Записи базы знаний, текст администратора, товары WooCommerce и выбранные PDF также разбиваются на ограниченные фрагменты и сохраняются в отдельной таблице.

Структурированные вопросы и ответы создаются прямо со страницы `DS AI Chatbot → База знаний`: заголовок используется как вопрос/тема, редактор — как проверенный ответ. Там же доступны текстовое поле для дополнительных знаний и поля телефона, WhatsApp и Telegram. По явному запросу посетителя плагин гарантированно добавляет найденную ссылку страницы или сохранённые контакты к ответу; URL отображаются кликабельными и создаются без небезопасного HTML.

PDF выбираются явно на странице базы знаний из Media Library: максимум 50 файлов по 10 МБ. Поддерживаются PDF с текстовым слоем; сканы нужно предварительно обработать OCR. WooCommerce является опциональным: при его активации индексируются только опубликованные видимые товары, их описание, SKU, публичная цена, наличие и категории.

Перед запросом к OpenAI, Claude, Gemini, OpenRouter, DeepSeek или WordPress AI Client плагин находит подходящие фрагменты и добавляет их как недоверенный справочный контекст. Изменённые опубликованные источники синхронизируются автоматически. Текущий retrieval использует локальный keyword ranking; semantic embeddings остаются отдельным опциональным адаптером.

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
composer test:unit
npm ci
npm run test:integration
npm run test:elementor
```

PHPUnit проверяет подпись session token, защитные границы lead form, chunking и sanitization дизайна. Integration tests используют WordPress Playground: первый режим проверяет чистый WordPress, второй устанавливает актуальный Elementor и подтверждает регистрацию widget. В отдельном завершающем шаге проверяется полный uninstall таблиц, options и cron. Docker и MySQL не требуются.

## Installable ZIP

```bash
bash scripts/build-zip.sh
```

Архив создаётся как `dist/wp-ds-aichatbot.zip`, содержит корневую папку `wp-ds-aichatbot/` и не включает CI, планы, tests, dev-зависимости и build scripts. GitHub Actions публикует такой же ZIP только после успешных PHP lint и WordPress/Elementor smoke tests.

## REST API

- `POST /wp-json/wp-ds-aichatbot/v1/session` — выдаёт подписанную сессию на 24 часа.
- `POST /wp-json/wp-ds-aichatbot/v1/chat` — принимает `session` и `message` до 2000 символов и возвращает ответ провайдера.

## AI-провайдеры

В `DS AI Chatbot → Настройки → AI-провайдеры` можно выбрать:

- OpenAI — Responses API, модель `gpt-5.6-sol` по умолчанию;
- Anthropic Claude — Messages API, `claude-sonnet-4-6`;
- Google Gemini — stable Interactions API v1, `gemini-3.5-flash`;
- OpenRouter — OpenResponses API, включая модели разных производителей;
- DeepSeek — нативный Chat Completions API, модели `deepseek-v4-flash`/`deepseek-v4-pro` и опциональный thinking mode;
- WordPress AI Client — provider-agnostic режим WordPress 7.0+, использующий настроенный в WordPress AI-коннектор.

Рекомендуемый способ — задать нужные ключи как environment variables в панели хостинга или secret manager:

- `WPDSAC_OPENAI_API_KEY`;
- `WPDSAC_ANTHROPIC_API_KEY`;
- `WPDSAC_GEMINI_API_KEY`;
- `WPDSAC_OPENROUTER_API_KEY`;
- `WPDSAC_DEEPSEEK_API_KEY`.

Плагин также читает одноимённые PHP constants, заданные сервером. Не добавляйте значения ключей в отслеживаемые Git файлы. В крайнем случае ключ можно сохранить через страницу настроек: поля write-only, сохранённые значения обратно не отображаются. Модели, общие инструкции и максимальный ответ настраиваются там же.

Прямые запросы выполняются через WordPress HTTP API с запретом redirects. OpenAI, OpenRouter и Gemini получают `store: false`. Registry расширяется через `wpdsac_ai_providers`, выбор — через `wpdsac_ai_provider_id`/`wpdsac_ai_provider`, request body — через общий `wpdsac_ai_request_body` и provider-specific фильтры вида `wpdsac_deepseek_request_body`. Sanitized diagnostics ошибок передаются в `wpdsac_ai_provider_error`; прежние OpenAI-интеграции сохраняют `wpdsac_openai_error`.

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

## Сбор лидов

Форма контактов появляется только когда посетитель сам просит связаться или нажимает «Оставить заявку». Чат-бот сначала просит контакты, затем показывает обязательные поля имени и телефона с явным согласием. Email посетителя не запрашивается.

Заявка привязывается только к одностороннему hash подписанной chat session; IP и raw session ID не сохраняются. Есть honeypot, отдельный rate limit, retention 1–730 дней, ежедневная очистка и защищённый список в `DS AI Chatbot → Лиды`.
