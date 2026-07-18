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
- JavaScript и `composer.json` прошли локальную синтаксическую проверку.
- Локальный PHP runtime отсутствует; PHP syntax lint выполняет добавленный GitHub Actions CI. До live-проверки нужен WordPress runtime с PHP.
- Следующий срез: REST endpoint, безопасная session model и rate limiter без OpenAI-вызова.
