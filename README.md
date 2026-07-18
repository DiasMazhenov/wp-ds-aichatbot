# WP DS AI Chatbot

Безопасный расширяемый AI-чатбот для WordPress с глобальным режимом, shortcode и интеграцией Elementor.

Проект реализуется с чистого листа. Коммерческий код, assets, лицензирование и телеметрия исходного Helper не копируются.

## Текущий статус

Первый этап: базовый plugin bootstrap, настройки, единый renderer, shortcode и Elementor widget.

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

