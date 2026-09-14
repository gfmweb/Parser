# AGENTS.md

Краткий гид для агента.

## Запуск
- Prod-стек: `make up` (`docker compose -f docker-compose.yml`)
- Dev-стек + Vite: `make up-dev`
- PHP-сервис: `php`
- Queue: `queue-worker`
- Node WebSocket: `ws-server`
- Vite (только dev): `frontend`

## Тесты
- Backend: `make test-php` → `docker compose exec php ./vendor/bin/pest`
- Frontend: `make test-fe` → Vitest в сервисе `frontend`
- Линт: `make lint` (PHPStan 8 + ESLint + vue-tsc)

## Важно
- Ответы — на русском; коммиты/комментарии — на русском; ветки — English (`feat/...`)
- Prod и `migrate` apply — только по явной просьбе
- Команды PHP/Node — в контейнерах, не на хосте
- PostgreSQL и Redis только в `backend_net` (internal), с хоста недоступны
- Перед PR: code-reviewer + security-performance-auditor
