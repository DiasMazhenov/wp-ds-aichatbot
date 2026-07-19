# WP DS AI Chatbot — рабочий контекст

Последнее обновление: 2026-07-19

## Запрос пользователя

Нужно клонировать по возможностям и адаптировать существующий WordPress AI-chatbot под собственные задачи, соблюдая WordPress Coding Standards, OOP, безопасность, расширяемость и совместимость с Elementor.

- Оригинал: `/Users/diasmazhenov/Downloads/helper114/helper114/Plugin/helper`
- Рабочая папка: `/Users/diasmazhenov/vibecode/wp-ds-aichatbot`
- GitHub: `https://github.com/DiasMazhenov/wp-ds-aichatbot`
- Репозиторий на момент начала был пустым; GitHub подтвердил его как приватный.
- Язык общения и рабочих документов: русский.
- После каждого завершённого исправления/среза требуется commit + push и время коммита в чате.
- `Plan.md` и этот файл требуется обновлять при изменении плана, архитектуры или статуса.

## Аудит оригинала Helper 1.1.4

Оригинал содержит около 12 000 строк собственного PHP, 60+ классов, крупный JS bundle, 2251 SVG-иконку и коммерческий фреймворк `Merkulove\Helper\Unity`.

Функции оригинала:

- глобальный popup и shortcode `[helper]`;
- OpenAI chat, Assistants, streaming и embeddings;
- FAQ и сценарные сообщения;
- сбор пользовательских данных и отправка email;
- bot logs;
- WooCommerce/post/PDF context;
- TTS/STT;
- live preview, аналитика и визуальные настройки;
- Envato activation/updater и телеметрия Merkulove.

Хранение данных оригинала:

- настройки: множество `mdp_helper_*_settings` options;
- логи: CPT `mdp_bot_logs_cpt` + postmeta;
- пользовательские данные: CPT `mdp_user_data_cpt` + произвольный postmeta;
- embeddings: большие массивы в options;
- Assistant thread ID: option с именем, полученным от посетителя;
- cache: таблица `{prefix}_helper_cache`;
- очистка: WP-Cron `mdp_old_logs_delete`.

## Найденные критические проблемы

1. Публичные AJAX create/delete thread используют переданный `session_id` как имя option. Посетитель с frontend nonce может воздействовать на произвольные options.
2. Публичный `update_log()` меняет postmeta по произвольному ID без проверки типа записи и владения сессией.
3. Upload/delete файлов использует POST-пути, `file_put_contents()` и не проверяет capability; возможен path traversal.
4. Streaming реализован прямым `message-stream.php`, который вручную подключает `wp-load.php` и принимает вопрос через GET.
5. Settings регистрируются без полноценной схемы sanitization.
6. Rate limiting по IP/hash не защищает бюджет от параллельных и распределённых запросов.
7. Произвольные пользовательские поля сохраняются без privacy exporter/eraser и retention policy.
8. Install/activation отправляет данные на внешние серверы Merkulove.
9. Используются устаревающие OpenAI Chat/Assistants-потоки; новый код должен использовать Responses API.

## Elementor-аудит

Оригинал не содержит фактически готового Helper Elementor widget:

- Elementor-режим определяется по слову `elementor` в text domain/slug;
- slug оригинала — `helper`, значит ветка не активируется;
- ожидаемая директория `Helper/Elementor/widgets` отсутствует;
- реальная совместимость достигается в основном глобальным popup и shortcode.

Решение для нового плагина: настоящий widget через `elementor/widgets/register`, использующий тот же renderer, что shortcode и глобальный режим.

## Лицензионное решение

Оригинал распространяется по Envato License и не должен быть механически переименован и опубликован в открытом GitHub-репозитории. Новый проект — clean-room реализация нужного поведения. Не переносим коммерческий PHP/JS/CSS, `Unity`, updater/licensing, телеметрию, брендинг и набор иконок.

## Зафиксированные технические решения

- Namespace: `DiasMazhenov\WPDsAiChatbot`.
- Prefix: `wpdsac_`.
- Text domain: `wp-ds-aichatbot`.
- Shortcode: `[ds_ai_chatbot]`.
- REST namespace: `wp-ds-aichatbot/v1`.
- WordPress HTTP API вместо старого `orhanerday/open-ai`, если официальный PHP SDK не даёт явного преимущества.
- OpenAI Responses API вместо новой реализации Assistants API.
- Единый renderer для всех способов вывода.
- Настройки в одном структурированном non-autoload option с sanitization schema.
- Логи, сообщения и embeddings не складывать в autoloaded options.
- API key хранится только серверно; предусмотреть override через constant/environment.
- Дополнительные функции реализуются модулями после рабочего безопасного ядра.

## Что входит в начальный MVP

1. Plugin bootstrap и autoload.
2. Lifecycle hooks.
3. Настройки заголовка, welcome message и глобального отображения.
4. Общий HTML renderer и условная загрузка assets.
5. Shortcode.
6. Elementor widget.
7. После этого — REST/OpenAI слой и безопасная сессия.

## Статус

- Анализ оригинала: завершён.
- План архитектуры: утверждён как стартовый.
- Git: локальный `main` и remote `git@github.com:DiasMazhenov/wp-ds-aichatbot.git` настроены.
- Документация: `Plan.md`, `Context.md`, `README.md` созданы.
- Первый срез: bootstrap, autoload, lifecycle, Settings API, общий renderer, shortcode, глобальный режим и Elementor widget реализованы.
- Assets загружаются только при фактическом render чатбота.
- Версия `0.2.0`: добавлены REST `/session` и `/chat`, подписанные stateless session tokens и ограничение сообщения до 2000 символов.
- Session token содержит только UUID/время жизни, подписан `wp_salt('auth')` и не создаёт options по пользовательскому ключу.
- Rate limit хранится в `{prefix}wpdsac_rate_limits` и увеличивается атомарным SQL upsert отдельно для session и прямого IP.
- Схема таблицы версируется через `Migrator`/`dbDelta()`; истёкшие buckets очищает WP-Cron.
- Frontend запрашивает сессию в REST, хранит token только в `sessionStorage` и блокирует повторную отправку формы во время запроса.
- `wpdsac_chat_reply` остаётся общим extension point; без настроенного API key `/chat` возвращает ожидаемый HTTP 503.
- Версия `0.3.0`: добавлены `ProviderInterface`, `ProviderManager` и non-streaming `OpenAIProvider` на Responses API.
- Актуальный рекомендуемый модельный дефолт на 2026-07-18 — `gpt-5.6-sol`; модель остаётся редактируемой из настроек.
- OpenAI request использует `instructions`, `input`, `max_output_tokens` и `store: false`; текст извлекается из `output_text` либо `output[].content[]`.
- API key разрешается в порядке `WPDSAC_OPENAI_API_KEY` constant → одноимённая environment variable → отдельная non-autoload option.
- Поле ключа write-only: сохранённый secret не выводится в HTML; provider errors возвращаются посетителю в обобщённом виде, а status/request ID/error code доступны интеграциям через `wpdsac_openai_error`.
- Альтернативный provider подключается через `wpdsac_ai_provider`, а request body — через `wpdsac_openai_request_body`.
- Версия `0.4.0`: `ProviderManager` преобразован в registry с выбором `ai_provider` в настройках.
- Из коробки доступны OpenAI Responses, Anthropic Messages, Gemini Interactions v1, OpenRouter OpenResponses и WordPress 7.0 AI Client.
- Общий `AbstractHttpProvider` централизует HTTPS POST, JSON validation, timeout, запрет redirects, безопасные публичные ошибки и sanitized diagnostics.
- Для Anthropic, Gemini и OpenRouter добавлены отдельные write-only credentials с constant/environment overrides: `WPDSAC_ANTHROPIC_API_KEY`, `WPDSAC_GEMINI_API_KEY`, `WPDSAC_OPENROUTER_API_KEY`.
- Общие provider-independent настройки: `ai_instructions` и `ai_max_output_tokens`; старые `openai_*` значения читаются для миграционной совместимости.
- Расширения request body: общий `wpdsac_ai_request_body` и provider-specific `wpdsac_{provider}_request_body`; diagnostics: `wpdsac_ai_provider_error`.
- JavaScript и `composer.json` прошли локальную синтаксическую проверку.
- GitHub Actions CI успешно выполнил PHP syntax lint на PHP 7.4, 8.1 и 8.3 для commits `ebf6388`, `11ea7f1` и REST/security-среза `3d95513`.
- GitHub Actions CI также успешно завершился для OpenAI provider commit `1ba8c49` на PHP 7.4, 8.1 и 8.3.
- Multi-provider commit `e5689f3` успешно прошёл GitHub Actions PHP syntax lint на PHP 7.4, 8.1 и 8.3.
- Нативный локальный PHP отсутствует; воспроизводимый WordPress runtime теперь предоставляет официальный WordPress Playground CLI.
- Версия `0.4.0` прошла CI и готова к provider-by-provider smoke test после установки на WordPress с соответствующими API keys.
- Версия `0.5.0`: добавлена таблица `{prefix}wpdsac_request_locks`, схема БД обновлена до версии `2`.
- Один session UUID может иметь только один активный provider request; lock создаётся атомарно, имеет TTL 45 секунд и удаляется только владельцем matching token.
- Добавлен атомарный site-wide rolling budget `daily_request_limit` на 24 часа, по умолчанию 500 provider calls; `0` отключает бюджет.
- REST возвращает HTTP 409 для параллельного request и HTTP 429 + `Retry-After` при исчерпании session/IP или site budget.
- WP-Cron очищает истёкшие rate-limit buckets и request locks; uninstall удаляет обе plugin tables.
- Добавлены `.gitattributes`, `scripts/build-zip.sh` и WordPress-style `readme.txt`; package имеет корень `wp-ds-aichatbot/` и исключает dev/CI/context files.
- GitHub Actions после успешного PHP lint собирает и публикует `dist/wp-ds-aichatbot.zip` как artifact на 14 дней.
- Security/budget commit `f827942` прошёл PHP lint на PHP 7.4, 8.1 и 8.3.
- Package commit `e7bb7be` прошёл полный workflow; artifact `wp-ds-aichatbot-e7bb7be49e36eee3ed12448a4ab480293d77194b` создан с ID `8432407203`, размером 32 587 байт и не expired.
- Локальный ZIP проверен через `unzip -t`; entrypoint и `readme.txt` присутствуют, dev-файлы отсутствуют.
- GitGuardian incident для commit `1ba8c49` вызван документационным placeholder `ваш-ключ`, а не реальным credential; README переведён на примеры без статических значений ключей.
- Добавлен WordPress Playground CLI `3.1.45` и local-only MU probe, исключённый из installable ZIP.
- Core smoke test подтверждает активацию, DB version `2`, обе таблицы, non-autoload settings, shortcode/global rendering, escaping, session REST, malformed-token rejection и безопасный HTTP 503 без credentials.
- Elementor smoke test устанавливает актуальный Elementor из WordPress.org и подтверждает загрузку Elementor и регистрацию widget `wpdsac-chatbot`.
- GitHub Actions собирает artifact только после PHP lint и обоих WordPress Playground smoke tests.
- Версия `0.5.1`: включён обязательный WPCS/PHPCompatibilityWP gate, зависимости зафиксированы в `composer.lock`, а PSR-4 filenames имеют узкое documented исключение из WordPress filename sniff.
- PHPCBF исправил форматирование; для внутренних таблиц SQL использует `%i`, прямые атомарные DB operations документированы точечными PHPCS annotations, а base64 используется только как base64url transport encoding подписанных session tokens.
- После WPCS/SQL правок core и Elementor smoke tests повторно прошли локально.
- Elementor integration smoke test теперь публикует временную страницу с реальным `wpdsac-chatbot` widget и проверяет frontend markup, escaping пользовательских значений, CSS, JavaScript и локализованную REST-конфигурацию.
- Core и расширенный Elementor frontend smoke tests успешно прошли локально 2026-07-19 без добавления браузерных npm-зависимостей.
- `node_modules` (WordPress Playground/PHP-WASM) и dev-пакеты `vendor` нужны только для разработки и исключены из Git; ZIP включает только production runtime PDF parser.
- Версия `0.5.2`: добавлен отдельный `Chat\Appearance` со схемой визуальных defaults, whitelist позиции и ограниченными числовыми диапазонами.
- В `Настройки → DS AI Chatbot` доступны цвета акцента/панели/текста/сообщений/границ, ширина, размер шрифта, скругление, позиция глобального виджета и отступы.
- Административный live preview работает без сторонних UI-библиотек; его CSS/JS загружаются только на странице настроек плагина.
- Визуальные значения передаются через экранированные CSS custom properties общему renderer и одинаково работают для global, shortcode и Elementor.
- Интеграционные проверки подтверждают custom appearance, левую позицию, fallback невалидного цвета и ограничения ширины/скругления/шрифта в core и Elementor режимах.
- Версия `0.5.3`: схема БД обновлена до `3`, добавлена таблица `{prefix}wpdsac_knowledge_chunks` с уникальным source/chunk index и hash содержимого.
- `Knowledge\Chunker` удаляет shortcode/HTML, нормализует пробелы и создаёт ограниченные текстовые фрагменты; в одной индексации источник ограничен 200 chunks.
- `Knowledge\PostIndexer` индексирует только опубликованные `page`/`post`, автоматически синхронизирует изменения и удаление; список post types расширяется фильтром `wpdsac_knowledge_post_types`.
- `Инструменты → DS AI Knowledge` показывает количество фрагментов и запускает capability/nonce-protected переиндексацию до 200 последних изменённых источников за один запрос.
- `Knowledge\Repository` хранит данные вне `wp_options` и выполняет bounded keyword retrieval по максимум восьми словам и 80 кандидатам.
- `Knowledge\Retriever` помечает найденный контекст как недоверенный reference material и подключается через общий `wpdsac_ai_message`, поэтому работает со всеми AI providers.
- Core runtime test подтверждает миграцию таблицы, индексацию WordPress page, поиск фрагмента и RAG augmentation без внешнего API key.
- Правило версий: до отдельного решения остаёмся в ветке `0.5.x` и увеличиваем только третью цифру (`0.5.3`, `0.5.4`, ...).
- Версия `0.5.4`: зарегистрирован приватный CPT `wpdsac_faq` с нативным WordPress UI в `Инструменты → AI FAQs`; все CRUD capabilities привязаны к `manage_options`.
- FAQ не имеет публичного URL и не появляется в frontend search/archive, но опубликованные записи автоматически индексируются `PostIndexer` с source type `faq`.
- Общая переиндексация теперь включает pages, posts и AI FAQs; Retriever не добавляет пустой URL для приватного FAQ.
- Runtime probe подтверждает регистрацию FAQ post type и автоматическую индексацию опубликованного FAQ при включённом knowledge retrieval.
- Следующий Knowledge-срез: optional semantic embeddings; затем PDF и WooCommerce.
- Версия `0.5.5`: DB schema `4` добавляет отдельные таблицы `{prefix}wpdsac_conversations` и `{prefix}wpdsac_messages`; logging выключен по умолчанию.
- Успешные exchanges записывает отдельный `Data\ConversationLogger`; raw session UUID и IP не сохраняются, session хранится как HMAC-SHA256.
- Retention ограничен диапазоном 1–365 дней; ежедневный `wpdsac_cleanup_conversations` удаляет истёкшие conversations и child messages bounded batches.
- Для same-origin авторизованных пользователей frontend передаёт стандартный REST nonce, поэтому журнал может связать разговор с WordPress user ID.
- `Privacy\ConversationPrivacy` регистрирует WordPress personal data exporter/eraser и suggested privacy-policy content; anonymous logs не связываются с email.
- Runtime test подтверждает DB version `4`, обе таблицы, cron, opt-in logging, экспорт и фактическое удаление пользовательских сообщений.
- Версия `0.5.6`: добавлен `Knowledge\PdfIndexer` с явным выбором до 50 PDF из Media Library, лимитом 10 МБ, realpath-проверкой внутри uploads и ограничением извлечённого текста.
- PDF parser поставляется как production Composer dependency; installable ZIP исключает Composer metadata, dev dependencies и `vendor/bin`, но включает runtime autoloader и лицензии.
- PDF без текстового слоя безопасно пропускаются; прямые URL PDF не передаются посетителю через RAG context.
- `Knowledge\WooCommerceSource` опционально добавляет только опубликованные видимые товары через публичные WooCommerce API: описание, SKU, цена, наличие и категории.
- Core runtime probe проверяет фактическое извлечение текста из валидного PDF, non-autoload selection option и индексирование WooCommerce-compatible product fixture.
- Следующий основной срез: сбор лидов с consent, retention и privacy exporter/eraser.
- Версия `0.5.7`: DB schema `5` добавляет `{prefix}wpdsac_leads`; lead collection выключен по умолчанию и требует явного checkbox consent.
- Общий chat template показывает опциональную форму имени/email; REST `/lead` требует подписанную session, валидный email, consent=true и пустой honeypot.
- Lead rate limit отделён от AI budget: 3 отправки на session и 10 на прямой peer IP за час; IP и raw session UUID не сохраняются.
- Lead хранит name, email, точный consent text, timestamps, user ID при авторизации и HMAC session hash; retention ограничен 1–730 днями.
- `Инструменты → DS AI Leads` доступен только `manage_options`; WordPress privacy tools экспортируют и удаляют заявки по email, включая анонимных посетителей.
- Ежедневный `wpdsac_cleanup_leads` удаляет истёкшие данные; deactivation очищает schedule, uninstall удаляет table.
- Runtime probe подтверждает migration `5`, lead table/cron, frontend consent form, persistence, privacy export/erase и REST rejection без consent.
- Следующий основной срез: unit/security tests, lifecycle/rate-limit verification и финальный installable ZIP audit.
- Версия `0.5.8`: добавлен PHPUnit 9.6 с CI matrix PHP 7.4/8.3; unit suite проверяет signed session round-trip/tampering, chunk bounds, appearance sanitization и lead honeypot/length limits.
- Playground probe подтверждает session и lead rate limits, очищение cron при deactivation и повторное планирование при activation.
- В конце каждого isolated smoke run production `uninstall.php` удаляет все шесть plugin tables, options и schedules; результат проверяется до уничтожения Playground runtime.
- Package job зависит от PHPUnit, WPCS, PHP lint и обоих integration modes; ZIP продолжает включать только production Composer dependencies.
- Основные реализуемые этапы завершены. Внешние ручные gates: Elementor editor iframe на реальном сайте и provider smoke с секретами владельца сайта.
- Первый CI run `0.5.8` выявил две environment-разницы: integration checkout не имел production PDF vendor, а Composer на PHP 8.5 выбрал `doctrine/instantiator 2.1`, несовместимый с PHP 7.4/8.3.
- Версия `0.5.9` добавляет `config.platform.php=7.4.0`, фиксирует `doctrine/instantiator ^1.5` и устанавливает `composer install --no-dev` перед Playground integration; локальный PHPUnit дополнительно прошёл через PHP-WASM 7.4.
- GitHub Actions run `29664260145` для commit `aaf3120` завершился успешно во всех jobs; artifact ID `8435277185`, имя `wp-ds-aichatbot-aaf3120a3d0f4fe5e5f0a877651514e77e231ec7`, размер 202 840 bytes.
- Финальный локальный `dist/wp-ds-aichatbot.zip` версии `0.5.9` прошёл `unzip -t`, весит около 232 КБ и не содержит tests, CI, `node_modules`, dev dependencies или `vendor/bin`.
- После проверки локальные `node_modules` и dev-`vendor` удалены; рабочий каталог вместе с ZIP занимает около 3.1 МБ и остаётся clean.
- Версия `0.5.10`: добавлен нативный `DeepSeekProvider` для `https://api.deepseek.com/chat/completions`, актуальный дефолт `deepseek-v4-flash` и опциональный thinking mode.
- DeepSeek credential разрешается через `WPDSAC_DEEPSEEK_API_KEY` constant → environment variable → отдельную non-autoload option; uninstall удаляет option.
- Страница настроек разделена на доступные вкладки: основные, AI-провайдеры, база знаний, дизайн, приватность и лиды; активная вкладка сохраняется в текущей browser session.
- В AI-вкладке показываются только поля выбранного провайдера. Write-only ключи не возвращаются в HTML, но теперь имеют явный статус сохранения; пустая повторная отправка сохраняет прежний ключ.
- Возле названия страницы настроек динамически выводится текущая версия из `WPDSAC_VERSION`.
- Версия `0.5.11`: добавлен общий `Support\PluginInfo`; административные названия настроек, Knowledge, FAQ, Leads и Elementor widget всегда получают актуальную версию из `WPDSAC_VERSION`.
- Публичный заголовок чат-бота остаётся пользовательским и не показывает техническую версию посетителям; стандартный экран Plugins продолжает брать версию из plugin header.
- В `0.5.11` вкладка дизайна разделена на Colors, Layout and typography, Controls and shapes; добавлены отдельные цвета сообщений/поля/кнопки, font preset, высота истории, padding, shadow opacity и отдельные радиусы.
- Свёрнутый чат теперь отображается круглым launcher с настраиваемым размером 44–96 px; live preview умеет переключаться между открытым и свёрнутым состояниями.
- Версия `0.5.12`: отдельный `Admin\PluginList` через `all_plugins` меняет только display metadata текущего плагина и показывает `WP DS AI Chatbot v{WPDSAC_VERSION}` непосредственно в жирном названии на экране Plugins.
- Версия `0.5.13`: Settings стал верхнеуровневым admin menu; Settings, Knowledge, приватный FAQ CPT и Leads собраны в одном dropdown вместо Settings/Tools.
- Добавлен полный `ru_RU` gettext pack (`.po` + compiled `.mo`) для админки, Elementor, frontend-сообщений и публичных ошибок; textdomain загружается на `init` priority 0.
- Вкладка дизайна снова использует двухколоночный workspace: компактные раскрывающиеся группы контролов слева и крупный sticky live preview справа; на узких экранах колонки складываются.
- Настройки сохраняются без перезагрузки через `wp_ajax_wpdsac_save_settings`; handler требует `manage_options`, проверяет nonce и повторно использует существующие sanitization schemas. `options.php` остаётся fallback без JavaScript.
- AJAX handler явно сохраняет write-only API key каждого direct provider в его отдельную non-autoload option. Валидация выполняется до любой записи, чтобы ошибка ключа не оставляла частично обновлённые настройки.
- Версия `0.5.14`: плейсхолдер поля сообщения редактируется в общих настройках, санитизируется и передаётся в единый renderer для global, shortcode и Elementor; live preview обновляется до сохранения.
- Версия `0.5.15`: `wp-chatbot.svg` имеет intrinsic-размер 20×20 px для корректного вывода WordPress admin menu; шапка страницы по-прежнему задаёт иконке размер 46×46 px через HTML-атрибуты.
- Версия `0.5.16`: добавлен безопасный provider diagnostics без API-ключа: provider, model, credential source и configured flag в UI, AJAX response и `wpdsacDebugProvider()`; frontend REST-ошибки выводят в консоль только path/status/code/message. Скрытие provider rows закреплено через `[hidden] { display:none!important; }`, API-ключ маскируется точками. Для иконки добавлены versioned URL и inline CSS 20×20 px на всех admin screens.
- Версия `0.5.17`: после remote-диагностики `missing/false` API-ключ переведён на явный structured AJAX transport `wpdsac_credentials[provider]` с fallback на прежнее top-level поле. Серверный ответ добавляет только safe flags `credentialSubmitted` и `storageVerified`; значение ключа никогда не возвращается.
- Версия `0.5.18`: late `admin_menu` reorder помещает Settings первым submenu перед автоматическим FAQ CPT. Provider rows скрываются прямым `.wpdsac-provider-setting[hidden]` и inline `display:none!important`. Для credential добавлен JSON transport и non-autoload `wpdsac_provider_credentials`; resolver читает bundle до legacy individual options, uninstall удаляет оба формата.
- Версия `0.5.19`: remote diagnostics показал `credentialSubmitted:false`; provider fields получили явные DOM wrappers, а key input теперь определяется по wrapper, option name и data attribute. Непустое значение кешируется только в JS memory до success; preflight не логирует сам секрет.
- Версия `0.5.20`: исправлена регрессия, когда active provider row сохранял `display:none!important`. Visibility loop теперь проходит только по `data-wpdsac-provider-field`; активная строка получает `removeAttribute('hidden')` и `style.removeProperty('display')`.
- Версия `0.5.21`: добавлен модуль `AI/PromptGuard`. До вызова провайдера он блокирует высокодостоверные prompt injection и вопросы о модели/провайдере; при заполненном поле тематики также блокирует вопросы без пересечения с разрешёнными темами. Все провайдеры получают неизменяемую security policy, а текст отказа настраивается в AI-вкладке.
- Версия `0.5.22`: приватный FAQ CPT больше не создаёт отдельный submenu и представлен как структурированные записи внутри единой страницы «База знаний». Добавлен модуль `Knowledge/ManualSource` с textarea на 10 строк: текст санитизируется, ограничивается 50 000 символами, хранится в non-autoload option и автоматически разбивается в общий RAG-индекс.
- Версия `0.5.23`: `PostIndexer` больше не зависит от включённого retrieval, пакетно индексирует все public post types и автоматически пересобирает старый индекс по version marker. `ElementorSource` добавляет frontend-render или безопасный JSON fallback для `_elementor_data`, Gutenberg проходит через `do_blocks`. `AnswerEnricher` гарантирует реальный source URL при запросе ссылки и точные контакты при запросе связи; `ContactSource` хранит phone/WhatsApp/Telegram в non-autoload option. Frontend безопасно превращает только HTTP(S) URL в кликабельные ссылки через DOM API.
- Версия `0.5.24`: устранена invalid HTML regression в Settings API — вызовы `do_settings_fields()` больше не выводят `<tr>` вне `<table>`. Общий helper рендерит `form-table/tbody` как для обычных вкладок, так и для accordion-групп дизайна; CSS задаёт полноширинную grid-row структуру, читабельные размеры, checkbox alignment и мобильный fallback. Это также восстанавливает корректное скрытие целых строк невыбранных AI-провайдеров.
- Версия `0.5.25`: чат сначала показывает редактируемый баббл и запрашивает имя, сохраняя его только в sessionStorage браузера. Добавлены редактируемые быстрые действия «Позвонить» и «Оставить заявку»; форма открывается также по явному contact intent и принимает email или телефон, описание заявки и согласие. Lead schema v6 хранит phone/request_text, а отдельный `Data\LeadNotifier` через `wp_mail()` отправляет на заданный адрес bounded plain-text transcript без сохранения переписки в lead row.
- На всех административных экранах плагина доступен переключатель светлой/тёмной темы. Выбор хранится в localStorage, применяется без перезагрузки и по умолчанию учитывает системную цветовую схему.
- Версия `0.5.26`: teaser bubble получил принудительный responsive width, normal whitespace и overflow wrapping. В основных настройках выбирается `off/soft/chime/pop`; звуки генерируются тихо через Web Audio API и проигрываются только после ответа ассистента, без медиафайлов. Имя из pre-chat шага отправляется как `visitor_name`, доступно в AI-инструкциях через `{username}` и очищается из runtime context в `finally` после вызова провайдера.
- Версия `0.5.27`: аватар выбирается из WordPress Media Library и выводится возле каждого статического и динамического сообщения ассистента, включая запрос имени и live preview. Intro bubble скрыт до выбранного триггера: 10-секундная задержка по умолчанию, 50% scroll, desktop exit intent, immediate или disabled; на touch exit intent безопасно использует задержку. При раннем ручном открытии пользователь сразу видит запрос имени внутри панели. Welcome template подставляет `{username}` и `(username)` на клиенте; chat rows, input grid и mobile stacking предотвращают выход контента за панель. Bubble использует translucent surface и backdrop blur.
- Версия `0.5.28`: удалено ошибочное процентное ограничение ширины send button, из-за которого надпись «Отправить» обрезалась. Кнопка использует intrinsic text width и nowrap, input остаётся shrinkable через `minmax(0, 1fr)`, а mobile breakpoint сбрасывает intrinsic minimum и растягивает кнопку на всю строку.
- Версия `0.5.29`: frontend собирает хронологическую историю текущего виджета из DOM, сохраняет её в `sessionStorage` вкладки для переходов между страницами и отправляет до 30 сообщений / 20 000 символов вместе с новым вопросом. REST endpoint принимает только роли `user/assistant`, ограничивает запись 4 000 символами и санитизирует текст. `ProviderManager` добавляет историю ко входу любого провайдера как untrusted context; `PromptGuard` запрещает истории менять policy и требует не повторять приветствие или представление. При отключённом optional logging новая память отдельно на сервере не сохраняется.
