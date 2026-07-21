# WP DS AI Chatbot — план разработки

Последнее обновление: 2026-07-20

## Цель

Создать поддерживаемый WordPress-плагин AI-чатбота с безопасными серверными интеграциями разных AI-провайдеров, глобальным выводом, shortcode и полноценным Elementor widget. Реализация ведётся с чистого листа: оригинальный коммерческий код Helper используется только как функциональный референс.

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
- [x] Детальные визуальные настройки в админке: отдельные цвета элементов, размеры, типографика, скругления, тень, позиция и live preview открытого/свёрнутого состояния.

### 3. Elementor

- [x] Проверка наличия и минимальной версии Elementor.
- [x] Регистрация через `elementor/widgets/register`.
- [x] Widget с базовыми контентными контролами (выбор профиля добавится вместе с профилями AI).
- [x] Использование общего renderer без дублирования логики.
- [x] Runtime-проверка загрузки Elementor и регистрации widget через WordPress Playground.
- [x] Проверка Elementor frontend: реальная опубликованная страница, widget markup, escaping и assets.
- [ ] Проверка Elementor editor iframe на целевом WordPress-сайте (внешний ручной gate).

### 4. API и безопасность

- [x] REST-контроллер `wp-ds-aichatbot/v1/chat`.
- [x] Строгая схема аргументов и ограничения длины.
- [x] Серверный session UUID; запрет пользовательских option names.
- [x] Rate limit с атомарным счётчиком.
- [x] Атомарная защита от параллельных запросов одной сессии с TTL и ownership token.
- [x] Глобальный rolling request budget на 24 часа и максимальный output token limit.
- [x] Документация без hardcoded-looking credentials; примеры используют только имена server-side environment variables/constants.
- [ ] Streaming — опциональное улучшение; текущий безопасный non-streaming режим является релизным.

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
- [x] DeepSeek Chat Completions API с моделями v4 и опциональным thinking mode.
- [x] WordPress 7.0 AI Client adapter для provider-agnostic коннекторов.

### 6. Данные и приватность

- [x] Версионируемые миграции через `dbDelta()`.
- [x] Отдельное хранилище для conversations/messages при включённых логах.
- [x] Срок хранения и cron-очистка.
- [x] WordPress privacy exporter/eraser для авторизованных пользователей.
- [x] Безопасный `uninstall.php` для созданных на текущем этапе данных.

### 7. Дополнительные модули

- [x] Ручной FAQ через приватный WordPress admin UI с автоматической индексацией.
- [x] Хранилище фрагментов базы знаний вне `wp_options`.
- [x] Knowledge/RAG по страницам и записям WordPress с локальным keyword ranking.
- [x] Semantic embeddings — опциональный provider-neutral adapter; локальный RAG является релизным.
- [x] PDF ingestion.
- [x] WooCommerce source.
- [x] Сбор лидов.
- [ ] TTS/STT и аналитика — только при подтверждённой необходимости.

### 8. Проверка и релиз

- [x] PHP syntax lint в CI на PHP 7.4, 8.1 и 8.3.
- [x] WordPress Coding Standards и PHPCompatibilityWP как обязательный CI gate.
- [x] Unit tests.
- [x] Integration smoke tests на чистом WordPress и с актуальным Elementor.
- [x] Проверка активации и миграций в чистой WordPress-инсталляции.
- [x] Базовая проверка публичного REST: session, malformed token и безопасный 503 без credentials.
- [x] Расширенная проверка деактивации/удаления, прав доступа и rate limiting.
- [x] Проверка WordPress и Elementor frontend.
- [ ] Проверка Elementor editor iframe на целевом сайте.
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
8. До отдельного решения о minor/major release версия остаётся в ветке `0.5.x`; каждый релиз повышает только третью цифру.

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

Последнее обновление: 2026-07-21

## Текущий статус

Версия `0.5.74`: критический фикс 500 ошибки (`resolve()` → `get_api_key()` в OpenAIEmbeddingsProvider). Чат работает.

### Выполнено в сессии 2026-07-21 (0.5.55 – 0.5.74)

- **Lead collection**: multi-step inline (имя → телефон), триггер на 5-м сообщении, скрытие после отправки
- **Communication styles**: 10 пресетов + Custom в AI-вкладке
- **Greetings pool**: рандомный выбор из textarea, fallback при пустом welcome
- **AI instructions**: наводящие вопросы о болях клиента, time-of-day контекст, запрет повторных приветствий, чтение всей истории
- **Avatar**: hard crop 200×200, визуальный crop в модалке (drag + zoom + wheel), object-position слайдеры, `object-fit: cover` для 1:1 маппинга с фронтендом
- **CSS**: переменные из `:root` → `.wpdsac-chat`, устранена каскадная утечка
- **Quick actions**: кнопки не скрываются после клика, контекстные QA-кнопки от AI (`[[WPDSAC_QA|Label|message|Text]]`)
- **ProviderManager**: откачен к рабочей версии 0.5.61, добавлен try-catch с debug-выводом в консоль
- **Переводы**: русские `.po/.mo` для lead-сообщений и greetings pool
- **500 fix**: `resolve()` → `get_api_key()` в OpenAIEmbeddingsProvider (корень всех проблем)

### Обнаруженная проблема деплоя

CSS/JS файлы (`chat.css`, `chat.js`) возвращают 404 на сервере — папка `assets/build/` отсутствует в деплое. Без CSS чат-бот рендерится голым HTML в футере. Решение: залить `assets/build/` через FTP или переустановить плагин из ZIP.

### Следующие шаги

1. Решить проблему деплоя build-файлов
2. Проверить, исчезли ли «сломанные стили» после загрузки CSS
3. Дальнейшие доработки по запросу
