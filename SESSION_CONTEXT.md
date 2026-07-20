# Session Context

## Objective
Предотвратить повторное предложение формы заявки чатботом, если посетитель уже оставил контактные данные.

## Changes Made
- [2026-07-20] `src/Data/LeadRepository.php` — добавлен `exists_for_session(string $session_id): bool`
- [2026-07-20] `src/AI/ProviderManager.php` — добавлен nullable `LeadRepository` в конструктор; перед вызовом провайдера при существующем лиде инжектится LEED STATUS в инструкции; после ответа страховочно удаляются маркеры `[[WPDSAC_ACTION|lead_form|...]]` и `[[WPDSAC_NAV|...#wpdsac-contact-form...]]`
- [2026-07-20] `src/Plugin.php` — `$leads` проброшен в `ProviderManager` третьим параметром
- [2026-07-20] `CONTEXT.md`, `Plan.md`, `SESSION_CONTEXT.md` — обновлены до версии `0.5.37`

## Key Decisions
- `LeadRepository` в `ProviderManager` — nullable для обратной совместимости
- Инструкция о существующем лиде дополняет (а не заменяет) navigation policy suffix
- Два страховочных regex на post-processing: действие lead_form и навигационный contact marker

## Errors & Resolutions
- PHPCS: double quotes → single quotes в строке LEED STATUS

## Current Status
Коммит `feb29db` запушен. PHP lint чист, PHPUnit 14/14 OK. Версия `0.5.37`.

## Next Steps
Ожидание новых указаний пользователя.
