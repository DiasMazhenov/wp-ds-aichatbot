# WP DS AI Chatbot — план разработки

Последнее обновление: 2026-07-18

## Цель

Создать поддерживаемый WordPress-плагин AI-чатбота с безопасной серверной интеграцией OpenAI, глобальным выводом, shortcode и полноценным Elementor widget. Реализация ведётся с чистого листа: оригинальный коммерческий код Helper используется только как функциональный референс.

## Именование

- Plugin slug и text domain: `wp-ds-aichatbot`
- PHP namespace: `DiasMazhenov\WPDsAiChatbot`
- Глобальный префикс: `wpdsac_`
- Option: `wpdsac_settings`
- REST namespace: `wp-ds-aichatbot/v1`
- Shortcode: `[ds_ai_chatbot]`
- Asset handles: `wpdsac-chat`, `wpdsac-admin`
- Cron prefix: `wpdsac_`

## Этапы

### 0. Репозиторий и документация

- [x] Зафиксировать аудит оригинального плагина.
- [x] Создать `Plan.md` и `Context.md`.
- [x] Инициализировать Git и подключить `DiasMazhenov/wp-ds-aichatbot`.
- [x] Добавить базовые правила репозитория и CI.

### 1. Безопасный каркас

- [x] Главный bootstrap-файл плагина.
- [x] PSR-4-совместимая автозагрузка.
- [x] Классы activation/deactivation.
- [x] Централизованная регистрация hooks.
- [x] Базовая страница настроек с `sanitize_callback`.
- [x] Общий renderer для global/shortcode/Elementor.

### 2. Первый вертикальный срез UI

- [x] Безопасная HTML-разметка чатбота.
- [x] CSS/JS только при фактическом выводе виджета.
- [x] Shortcode `[ds_ai_chatbot]`.
- [x] Опциональный глобальный вывод через `wp_footer`.
- [x] Настройки заголовка и приветственного сообщения.

### 3. Elementor

- [x] Проверка наличия и минимальной версии Elementor.
- [x] Регистрация через `elementor/widgets/register`.
- [x] Widget с базовыми контентными контролами (выбор профиля добавится вместе с профилями AI).
- [x] Использование общего renderer без дублирования логики.
- [ ] Проверка frontend и editor iframe.

### 4. API и безопасность

- [x] REST-контроллер `wp-ds-aichatbot/v1/chat`.
- [x] Строгая схема аргументов и ограничения длины.
- [x] Серверный session UUID; запрет пользовательских option names.
- [x] Rate limit с атомарным счётчиком.
- [x] Атомарная защита от параллельных запросов одной сессии с TTL и ownership token.
- [x] Глобальный rolling request budget на 24 часа и максимальный output token limit.
- [ ] Безопасный streaming без прямого PHP endpoint и ручного `wp-load.php`.

### 5. AI provider

- [x] `ProviderInterface`.
- [x] OpenAI provider на Responses API без streaming.
- [x] API key только на сервере; write-only option и приоритетный constant/env override.
- [x] Обработка timeout, HTTP-кодов и request ID без утечки provider details посетителю.
- [x] Фильтры для подключения других провайдеров и изменения request body.
- [x] Provider registry с выбором активного провайдера в настройках.
- [x] Anthropic Claude Messages API.
- [x] Google Gemini Interactions API v1.
- [x] OpenRouter OpenResponses API.
- [x] WordPress 7.0 AI Client adapter для provider-agnostic коннекторов.

### 6. Данные и приватность

- [x] Версионируемые миграции через `dbDelta()`.
- [ ] Отдельное хранилище для conversations/messages при включённых логах.
- [ ] Срок хранения и cron-очистка.
- [ ] WordPress privacy exporter/eraser.
- [x] Безопасный `uninstall.php` для созданных на текущем этапе данных.

### 7. Дополнительные модули

- [ ] FAQ.
- [ ] Knowledge/RAG по записям WordPress.
- [ ] PDF ingestion.
- [ ] WooCommerce source.
- [ ] Сбор лидов.
- [ ] TTS/STT и аналитика — только при подтверждённой необходимости.

### 8. Проверка и релиз

- [x] PHP syntax lint в CI на PHP 7.4, 8.1 и 8.3.
- [ ] WordPress Coding Standards.
- [ ] Unit и integration tests.
- [ ] Проверка активации/деактивации/удаления.
- [ ] Проверка прав доступа, REST и rate limiting.
- [ ] Проверка WordPress и Elementor frontend/editor.
- [x] Воспроизводимая сборка и валидация installable ZIP.
- [x] Публикация ZIP как GitHub Actions artifact после PHP lint.

## Архитектурные правила

1. Не копировать `Unity`, Envato updater/licensing, телеметрию, коммерческие assets и vendor оригинала.
2. Nonce не считается авторизацией: административные операции всегда требуют capability.
3. Пользовательский ввод никогда не используется как option name, путь файла, имя таблицы или SQL identifier.
4. Настройки проходят схему sanitization; вывод экранируется в момент использования.
5. Shortcode, Elementor и глобальный режим используют один renderer и один frontend bundle.
6. Большие embeddings и журналы не хранятся в autoloaded options.
7. Каждый завершённый срез проверяется, коммитится и отправляется в GitHub; время коммита фиксируется в чате.

## Предлагаемая структура

```text
wp-ds-aichatbot/
├── wp-ds-aichatbot.php
├── uninstall.php
├── composer.json
├── phpcs.xml.dist
├── Plan.md
├── Context.md
├── src/
│   ├── Plugin.php
│   ├── Support/Autoloader.php
│   ├── Lifecycle/{Activator,Deactivator,Migrator}.php
│   ├── Admin/{Settings,SettingsPage}.php
│   ├── Chat/{Assets,Renderer,Shortcode}.php
│   ├── Api/{ChatController,StreamController,RateLimiter}.php
│   ├── AI/{ProviderInterface,OpenAIProvider,PromptBuilder}.php
│   ├── Data/{ConversationRepository,KnowledgeRepository,LeadRepository}.php
│   ├── Knowledge/{PostSource,PdfSource,WooCommerceSource}.php
│   ├── Elementor/{Integration,ChatbotWidget}.php
│   └── Privacy/{Exporter,Eraser}.php
├── templates/chatbot.php
├── assets/{src,build}/
├── languages/
├── tests/{Unit,Integration}/
└── .github/workflows/ci.yml
```

## Текущий следующий шаг

Проверить installable ZIP в CI и скачать artifact версии `0.5.0`. После этого следующий gate — реальная активация/миграция на WordPress, shortcode/global/Elementor frontend/editor и provider-by-provider smoke test. WordPress AI Client проверяется отдельно на WordPress 7.0 с настроенным AI Connector.
