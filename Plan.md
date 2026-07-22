# WP DS AI Chatbot — план разработки

Последнее обновление: 2026-07-22

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

Последнее обновление: 2026-07-22

## Текущий статус

Версия `0.5.94`: финальные правки стилей — фон композера убран, фон сообщений `#11182763`, blur панели 2px, иконка отправки по центру.

### Выполнено в 0.5.94

- [x] Композер: фон убран (opacity 0 по умолчанию).
- [x] Сообщения: тёмный полупрозрачный фон `#11182763`.
- [x] Панель: blur уменьшен до 2px.
- [x] Кнопка отправки: SVG иконка центрирована.

### Выполнено в 0.5.93

- [x] Панель: полупрозрачный белый фон + `backdrop-filter: blur(12px)` + тонкая рамка.
- [x] Сообщения: полностью прозрачные, скрытый скроллбар (Chrome/Firefox/IE).
- [x] Композер: лёгкий фон `rgba(255,255,255, 10%)` + опциональная разделительная линия сверху.
- [x] Новые настройки: panel_blur, panel_bg_opacity, panel_border_opacity, composer_border_top.

### Следующие этапы

#### 1. Стриминг ответов AI
- [x] Typing indicator (анимированные точки) при ожидании ответа — 0.5.101.
- [ ] Настоящий SSE-стриминг: WordPress AJAX с `text/event-stream`, raw curl для потоковых эндпоинтов провайдеров, фронтенд с `ReadableStream`.
- [ ] Пословный рендеринг из стрима сразу при получении чанков.

#### 2. Модульность chat.js
- [ ] Разбить на модули: `session.js`, `reengage.js`, `lead.js`, `messages.js`.
- [ ] Общий `chat-core.js` с `scrollToLatest`, `appendMessage`, `request()`.

#### 3. Email/webhook для лидов
- [ ] Поле email в lead-форме.
- [ ] Webhook URL → POST при новом лиде.
- [ ] CSV-экспорт лидов из админки.

#### 4. Accessibility
- [ ] Навигация с клавиатуры, focus trap, `role="log"`.
- [ ] Анонсирование screen-reader'ом.

#### 5. Кеширование inline_style
- [ ] Transient для CSS, инвалидация при сохранении.

#### 6. Visual regression tests
- [ ] Playwright скриншоты в CI, блокировка регресса.

### Выполнено в 0.5.106

- [x] **Критическое исправление**: утечка админ-ссылок через `document.querySelectorAll('a[href]')`.
- [x] Client: `isPublicUrl()`, только публичные селекторы DOM, запрет admin bar.
- [x] Server: `UrlDenylist` (decode, dot-segments, backslash, case), интеграция в ChatController + ProviderManager.
- [x] PromptGuard: правила anti-leak (нет доступа к админке, БД, плагинам).

### Выполнено в 0.5.105

- [x] Ссылка «Настройки» в plugin row actions.
- [x] Author URI: https://mazhenov.kz/.

### Выполнено в 0.5.104

- [x] Фон композера убран полностью: CSS, inline_style, defaults, color_keys, number_constraints, настройки админки.

### Выполнено в 0.5.103

- [x] chat.js: 12 секционных заголовков.
- [x] Visual regression: 7 CSS-ассертов в integration-тесте.

### Выполнено в 0.5.102

- [x] Email поле в lead-форме + валидация.
- [x] Webhook URL настройка + POST dispatch.

### Выполнено в 0.5.101

- [x] Typing indicator (3 точки) при ожидании ответа AI.
- [x] Удалены мёртвые CSS-переменные.

### Выполнено в 0.5.100

- [x] Preview stage фон изменён на чёрный.
- [x] Фон композера снова настраиваемый.

### Выполнено в 0.5.99

- [x] Preview: `<form>` заменён на `<div>` — исправлена кнопка сохранения настроек.

### Следующий обязательный этап

- [ ] Production-деплой после прохождения всех CI.

### Выполнено в 0.5.92

- [x] DOM: композер вынесен из `__conversation`, стал прямым потомком `__panel`.
- [x] Новый раздел «Окно сообщений»: режим, цвет, прозрачность, blur, saturation, radius, border, glare, shadow, padding, отступ.
- [x] Раздел «Нижняя панель»: упрощён до bg/opacity/radius/padding/gap/scroll.

### Выполнено в 0.5.91

- [x] DOM: быстрые кнопки и форма ввода обёрнуты в `.wpdsac-chat__composer`.
- [x] CSS: композер — единая стеклянная панель с grid, blur, тенью; история — опционально прозрачная.
- [x] 15 настроек: фон, opacity, blur, radius, padding, gap, border, shadow, spacing, scrollable кнопки, прозрачная история.
- [x] Live preview: композер с кнопками и полем ввода, мгновенное обновление.
- [x] Безопасность: sanitize_hex_color, числовые range, существующие defaults.

### Следующий обязательный этап

- [ ] Production-деплой после прохождения всех CI.

### Выполнено в 0.5.87

- [x] **Re-engage count**: увеличивается ровно один раз после успешного непустого ответа; cooldown ставится до запроса; ошибка AI не увеличивает count.
- [x] **Activity flag**: `wpdsac_chat_exchange` → `mark_activity()` — re-engage не запускается до первого реального обмена; browser history не является доказательством.
- [x] **Reengage REST state**: `{reply, quick_replies, reengage: {allowed, reason, count, max_count, retry_after}}`; безопасные коды `disabled`/`no_conversation`/`lead_exists`/`cooldown`/`max_reached`/`provider_error`.
- [x] **JS**: не запускает таймер на загрузке; toggle не отменяет re-engage; `handleReengageState()` обрабатывает серверные коды; закрытый чат сохраняет `quick_replies` в sessionStorage для показа при открытии.
- [x] **QuickReplyParser**: fallback «Выберите подходящий вариант:» при пустом reply после удаления маркеров.
- [x] **CSS**: `--wpdsac-panel-bg` → `--wpdsac-surface`; усиленный selector audit (bare tag, global, Elementor, universal selectors запрещены).
- [x] **ProviderManager**: QUICK REPLY VARIANTS policy для re-engage запроса.

- [x] **AI/QuickReplyParser**: серверный парсер `[[WPDSAC_QA|Label|message|Text]]`, извлекает маркеры из ответа AI, возвращает `{reply, quick_replies}`; валидация 2–5 вариантов, лимиты label/message, игнорирование HTML/URL-действий.
- [x] **AI/ReengageService**: серверные проверки (enabled, user messages, lead exists, cooldown transient, max count transient), HMAC-ключ от session UUID, `reengage_instructions` из настроек.
- [x] **Api/ReengageController**: REST `POST /reengage`, проверяет nonce/session, вызывает `wpdsac_reengage_exchange` вместо `/chat`.
- [x] **ProviderManager**: добавлен обработчик `wpdsac_reengage_exchange` — отдельный путь без QUICK REPLY VARIANTS suffix, без prompt guard, чистое системное сообщение.
- [x] **ChatController**: ответ прогоняется через QuickReplyParser, `quick_replies` возвращаются в REST-ответе.
- [x] **chat.js**: re-engage через sessionStorage (`dueAt`, `count`, `lastActivity`), сброс таймера при любом вводе/клике/форме, проверка `visibilitychange`, раздельный `/reengage` endpoint.
- [x] Закрытый чат: ответ добавляется в историю + intro bubble с preview, при открытии нет дублирования, при ошибке восстанавливается предыдущий текст.
- [x] **Контекстные кнопки**: `[data-wpdsac-context-actions]` контейнер над формой ввода, НЕ внутри сообщений. Обычные quick actions скрываются при наличии QA, восстанавливаются при клике/вводе. При ошибке API обычные кнопки обязательно восстанавливаются.
- [x] **CSS**: `.wpdsac-chat__context-actions` и `.wpdsac-chat__context-action` строго под `.wpdsac-chat`, с акцентным фоном, hover, max-height с прокруткой.

### Выполнено в 0.5.83

- [x] **Re-engagement timer**: настраиваемый таймер бездействия в браузере, отправляет контекстный follow-up вопрос через AI
- [x] Настройки: `reengage_enabled`, `reengage_delay` (10–1800 сек), `reengage_max_count` (0–5), `reengage_instructions`
- [x] Таймер запускается после ответа бота, отменяется при любой активности пользователя
- [x] Открытый чат — сообщение в истории, закрытый — в intro bubble
- [x] Не отправляется если: лид уже оставлен, нет сообщений пользователя, лимит исчерпан
- [x] **QA variant buttons**: AI получает инструкции генерировать 2–5 кнопок для multiple-choice вопросов
- [x] Формат `[[WPDSAC_QA|Label|message|Text]]` зафиксирован в suffix ProviderManager
- [x] При наличии QA-кнопок обычные quick actions временно скрываются
- [x] При клике на QA или ручном вводе — QA скрываются, quick actions возвращаются
- [x] Кнопки используют существующие CSS-классы `.wpdsac-chat__quick-action` + `.wpdsac-chat__qa-action`

### Критический аудит 0.5.75

- [x] Скрыть исключения провайдеров и внутренние пути из публичного REST.
- [x] Удалить SQL, сообщения БД и данные посетителя из REST-ответов и browser console.
- [x] Перевести сохранение лидов с прямого `mysqli_query()` на `$wpdb->replace()`.
- [x] Подключить фоновую пакетную генерацию embeddings и исключить пустые API-вызовы.
- [x] Обновить integration expectations до plugin `0.5.75` / DB `8`, stable tag и changelog.
- [x] Найти причину падения run `29855924222`: устаревший `/hideQuickAction/` assertion.
- [x] Исправить assertions `/hideQuickAction/` и `SITE NAVIGATION POLICY` в соответствии с текущим поведением и добавить обязательный pre-push протокол в `Context.md`.
- [x] Локально подтвердить core и Elementor WordPress Playground smoke tests.
- [x] Подтвердить зелёный GitHub Actions run `29856541857` для 0.5.76; package job создал installable ZIP artifact.
- [x] Убрать жёсткую привязку embeddings к OpenAI и добавить независимый выбор Auto/OpenAI/Gemini/OpenRouter.
- [x] Сбрасывать несовместимые сохранённые векторы при смене embedding provider/model.
- [x] Ограничить увеличенный аватар круглой clipping-рамкой во всех frontend и admin preview.
- [x] Добавить fallback-текст и пример сообщения посетителя в live preview.
- [x] Добавить live-настройки line-height, размеров заголовка, сообщений, поля ввода и кнопки отправки.
- [x] Использовать имя чат-бота из основных настроек в защищённых инструкциях всех AI-провайдеров.
- [x] Добавить provider-independent правила естественной речи без длинных тире, шаблонных вступлений и лишнего форматирования.
- [x] Добавить варианты анимации свернутого круга, три цвета градиента, скорость, интенсивность и live preview с reduced-motion.
- [x] Добавить пословную анимацию новых AI-ответов с плавным ростом баббла, немедленным сохранением полного текста и live replay.
- [x] Отправлять одно отдельное письмо с историей диалога без контактов после периода бездействия; переносить таймер при продолжении и отменять письмо при создании лида.
- [x] Жёстко изолировать frontend CSS корнем чата и закрепить запрет глобальной утечки integration-тестом.
- [x] Преобразовывать шаблонные варианты приветствия в естественную фразу по локальному времени сайта для welcome, preview и AI-ответов.

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

1. Дождаться зелёного GitHub Actions run для 0.5.75 и скачать актуальный artifact ZIP.
2. Выполнить чистое обновление установленной копии, чтобы восстановить `assets/build/`.
3. Проверить frontend и фактическое сохранение лида на production.
