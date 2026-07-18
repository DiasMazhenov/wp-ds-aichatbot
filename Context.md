# WP DS AI Chatbot — рабочий контекст

Последнее обновление: 2026-07-18

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
- Следующий gate: browser test Elementor frontend/editor и provider-by-provider smoke test с реальными server-side credentials.
