# Parser — отзывы Яндекс Карт

Монорепо: Laravel 13 (`backend/`), Vue 3 SPA (`frontend/`), Node.js WebSocket (`ws-server/`), PostgreSQL 16, Redis 7, Nginx. Nginx — единственная внешняя точка входа (порты 80/443). PostgreSQL и Redis живут только во внутренней сети `backend_net` и с хоста недоступны.

## 1. Быстрый старт (Docker)

Команды ниже рассчитаны на разработчика с базовым опытом Docker. `make install` обращается к уже запущенным контейнерам (`docker compose exec`), поэтому сначала поднимаем стек, затем ставим зависимости и накатываем схему.

```bash
git clone <URL-репозитория> Parser
cd Parser
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
make up
make install
docker compose exec php php artisan key:generate
make migrate
make seed
```

Откройте в браузере [http://localhost](http://localhost).

Вход: `admin@test.com` / `password`.

Что делает каждая команда:

| Команда | Зачем |
|---|---|
| `git clone …` | Скачивает исходники проекта на диск. |
| `cd Parser` | Переходит в каталог репозитория. |
| `cp .env.example .env` | Создаёт корневой файл переменных для Docker Compose (имена сервисов, пароли Redis/Postgres, секрет WS). |
| `cp backend/.env.example backend/.env` | Создаёт конфиг Laravel внутри контейнера `php`. |
| `cp frontend/.env.example frontend/.env` | Создаёт адреса API и WebSocket для Vite/сборки фронтенда. |
| `make up` | Собирает образы и поднимает Nginx, PHP-FPM, PostgreSQL, Redis, `queue-worker`, `ws-server`. Генерирует локальный TLS-сертификат, если его ещё нет. |
| `make install` | Ставит PHP-зависимости (`composer`) и npm-пакеты фронтенда/`ws-server` **в контейнерах**, не на хосте. |
| `php artisan key:generate` | Записывает `APP_KEY` — без него Laravel не шифрует сессии и не должен выходить в сеть. |
| `make migrate` | Создаёт таблицы в PostgreSQL (пользователи, организации, отзывы, снимки, задачи парсинга). |
| `make seed` | Добавляет демо-пользователя `admin@test.com` с паролем `password`. |

Локальная разработка с горячей перезагрузкой Vue (Vite на порту 5173):

```bash
make up-dev
```

Nginx в этом режиме проксирует SPA на Vite, API — на PHP-FPM, сокеты — на `ws-server`.

Продакшен-режим (`make up`) отдаёт уже собранный `frontend/dist`. Если каталог пустой, соберите фронт:

```bash
docker compose -f docker-compose.yml -f docker-compose.override.yml run --rm --no-deps frontend npm run build
```

Остановка: `make down`. Полезные цели: `make ps`, `make logs`, `make test`, `make lint`.

**Продакшен.** В `backend/.env` и корневом `.env` выставьте `APP_ENV=production`, `APP_DEBUG=false`, уникальный `APP_KEY`, `WS_INTERNAL_SECRET` из 32 случайных символов (не `changeme`). Пример: `openssl rand -hex 16`.

## 2. Справочник переменных окружения

Таблица покрывает `backend/.env.example` и `frontend/.env.example`. Корневой `.env.example` нужен Docker Compose (те же `DB_*`, `REDIS_*`, `WS_*`); его смысл совпадает со строками ниже.

### backend/.env.example

| Переменная | Описание | Значение по умолчанию (если есть) |
|---|---|---|
| `APP_NAME` | Имя приложения в логах и письмах | `Yandex Reviews` |
| `APP_ENV` | Окружение Laravel (`local` / `production`) | `local` |
| `APP_KEY` | Ключ шифрования приложения Laravel | — |
| `APP_DEBUG` | Подробные ошибки; в продакшене должно быть `false` | `true` |
| `APP_URL` | Публичный URL приложения | `http://localhost` |
| `APP_LOCALE` | Основная локаль Laravel | `en` |
| `APP_FALLBACK_LOCALE` | Запасная локаль, если перевода нет | `en` |
| `APP_FAKER_LOCALE` | Локаль генератора тестовых данных | `en_US` |
| `APP_MAINTENANCE_DRIVER` | Драйвер режима обслуживания | `file` |
| `BCRYPT_ROUNDS` | Число раундов хеширования паролей | `12` |
| `LOG_CHANNEL` | Канал логирования по умолчанию | `stack` |
| `LOG_STACK` | Каналы внутри `stack` | `single` |
| `LOG_DEPRECATIONS_CHANNEL` | Куда писать предупреждения об устаревшем API | `null` |
| `LOG_LEVEL` | Минимальный уровень логов | `debug` |
| `DB_CONNECTION` | Драйвер БД | `pgsql` |
| `DB_HOST` | Хост PostgreSQL (имя сервиса в Docker) | `postgres` |
| `DB_PORT` | Порт PostgreSQL | `5432` |
| `DB_DATABASE` | Имя базы данных | `yandex_reviews` |
| `DB_USERNAME` | Пользователь БД | `app` |
| `DB_PASSWORD` | Пароль БД | `secret` |
| `SESSION_DRIVER` | Где хранить сессии | `cookie` |
| `SESSION_LIFETIME` | Время жизни сессии в минутах | `120` |
| `SESSION_ENCRYPT` | Шифровать ли данные сессии | `false` |
| `SESSION_PATH` | Path cookie сессии | `/` |
| `SESSION_DOMAIN` | Domain cookie сессии | `localhost` |
| `BROADCAST_CONNECTION` | Драйвер широковещательных событий | `log` |
| `FILESYSTEM_DISK` | Диск файлового хранилища | `local` |
| `QUEUE_CONNECTION` | Очередь фоновых задач | `redis` |
| `CACHE_STORE` | Хранилище кэша | `redis` |
| `REDIS_CLIENT` | PHP-клиент Redis | `predis` |
| `REDIS_HOST` | Хост Redis (имя сервиса в Docker) | `redis` |
| `REDIS_PORT` | Порт Redis | `6379` |
| `REDIS_PASSWORD` | Пароль Redis | `secret` |
| `MAIL_MAILER` | Транспорт почты (в dev пишется в лог) | `log` |
| `MAIL_SCHEME` | Схема SMTP (`smtp` / `null`) | `null` |
| `MAIL_HOST` | Хост почтового сервера | `127.0.0.1` |
| `MAIL_PORT` | Порт почтового сервера | `2525` |
| `MAIL_USERNAME` | Логин SMTP | `null` |
| `MAIL_PASSWORD` | Пароль SMTP | `null` |
| `MAIL_FROM_ADDRESS` | Адрес отправителя | `hello@example.com` |
| `MAIL_FROM_NAME` | Имя отправителя | `${APP_NAME}` |
| `SANCTUM_STATEFUL_DOMAINS` | Домены SPA, которым разрешены cookie Sanctum | `localhost,localhost:5173` |
| `SANCTUM_EXPIRATION` | Срок жизни API-токена Sanctum в минутах | `10080` (7 суток) |
| `FRONTEND_URL` | URL фронтенда (CORS / редиректы) | `http://localhost:5173` |
| `WS_SERVER_URL` | Базовый URL Node.js WS-сервера для внутренних HTTP-уведомлений | `http://ws-server:6001` |
| `WS_INTERNAL_SECRET` | Общий секрет Laravel ↔ WS (`X-Internal-Secret`); в продакшене 32 случайных символа | `changeme` |
| `PROXY_LIST` | Зарезервировано: список прокси через запятую для будущего пула | пусто |
| `REDIS_QUEUE_RETRY_AFTER` | Через сколько секунд Redis считает джобу зависшей (`retry_after` > timeout воркера) | `300` |
| `AWS_ACCESS_KEY_ID` | Ключ AWS (штатный шаблон Laravel, не используется парсером) | — |
| `AWS_SECRET_ACCESS_KEY` | Секрет AWS | — |
| `AWS_DEFAULT_REGION` | Регион AWS | `us-east-1` |
| `AWS_BUCKET` | Имя S3-бакета | — |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Path-style endpoint для S3 | `false` |

### frontend/.env.example

| Переменная | Описание | Значение по умолчанию (если есть) |
|---|---|---|
| `VITE_API_URL` | Базовый URL REST API для Axios | `http://localhost/api` |
| `VITE_WS_URL` | URL WebSocket для прогресса парсинга | `ws://localhost/ws` |

В Docker Compose для Vite задаются относительные пути `VITE_API_URL=/api` и `VITE_WS_URL=/ws`, чтобы браузер ходил через Nginx, а не напрямую в контейнеры.

## 3. Обзор архитектуры

```text
[Browser] → [Nginx] → [PHP-FPM / Laravel]
                   → [WS Server / Node.js]
                         ↑
               [Queue Worker] → [Redis Queue]
                   ↑
               [PostgreSQL]
```

Как идут данные:

1. Браузер открывает SPA. Nginx отдаёт статику (или проксирует Vite в dev) и маршрутизирует `/api/` на PHP-FPM, `/ws` — на Node.js.
2. Пользователь логинится; Laravel выдаёт токен Sanctum. Дальше все `/api/*` кроме `/login` требуют `Authorization: Bearer`.
3. Создание организации пишет строку в PostgreSQL и кладёт `ParseOrganizationJob` в Redis.
4. Контейнер `queue-worker` забирает задачу, ходит в внутренний JSON API Яндекс Карт, сохраняет отзывы и снимки.
5. По ходу парсинга воркер шлёт прогресс на `ws-server` (`POST /internal/progress` с заголовком `X-Internal-Secret`). Браузер подписан на канал `parse.{organizationId}` и рисует прогресс-бар без перезагрузки.
6. Список отзывов отдаётся пагинацией по 50 записей; смена страницы — SPA-навигация (`?page=`).

Кто за что отвечает:

- **Nginx** — балансировщик/прокси, отдаёт статику и маршрутизирует запросы.
- **Laravel (PHP-FPM)** — бизнес-логика, контроллеры, сервисы, работа с БД.
- **WS Server (Node.js)** — real-time обновления прогресса для фронтенда.
- **Queue Worker (Laravel)** — фоновые задачи парсинга, устойчивые к сбоям.
- **Redis** — очередь задач и временное хранение статусов.
- **PostgreSQL** — основное хранилище данных (отзывы, организации, снимки).

## 4. Подход к парсингу и антидетекция

**Выбранный подход: JSON-стейт из HTML карточки отзывов.**

### Почему не headless-браузер (Puppeteer/Playwright)

Нужен Chrome в контейнере: это сотни мегабайт RAM (~300 МБ и больше), медленный холодный старт, сложный Docker-слой. Яндекс всё равно умеет отличать автоматизацию. Для пакетной загрузки сотен отзывов это лишняя тяжесть.

### Почему HTML-стейт, а не XHR API

Раньше карточка подгружала отзывы через XHR (`/maps/api/business/fetchpointinfo`, `/maps/api/business/reviews`). Эти эндпоинты без одноразового `csrfToken` и подписи `s` отвечают `{"csrfToken":"..."}` без `data` — парсер падал с `Structure changed: missing field data`.

Страница `/maps/org/{id}/reviews/?page=N` по-прежнему отдаёт тот же JSON внутри `<script>` (SSR). Клиент качает HTML, достаёт стейт, DOM карточки не разбираем. На странице до 50 отзывов, мета организации лежит в `stack[0].results.items[0]`.

### Риски

Вёрстка и форма стейта могут смениться без анонса. Поэтому парсер явно падает с `SourceChangedException`, а не «тихо» возвращает пустой список.

### Стратегия антибана

- Ротация `User-Agent` из пула реальных Chrome UA (Windows / macOS / Linux).
- Случайная пауза 0.5–1.5 с между страницами отзывов (`usleep`) — имитация человеческой прокрутки.
- Пакетная выгрузка: страница по 50 отзывов (`?page=N`).
- Экспоненциальная отсрочка при HTTP 429 и 5xx: пауза `2^попытка` секунд между повторами (2 с, затем 4 с), всего до 3 попыток HTTP.
- Если после трёх 429 ответ всё ещё «слишком много запросов», задача падает с `parse_error = "Rate limited"`.
- Для 50 организаций задачи ставятся в Redis и разъезжаются по времени; горизонтально добавляются контейнеры `queue-worker`.
- В перспективе — пул прокси через `PROXY_LIST` (переменная уже зарезервирована в `.env.example`).

## 5. Как парсер понимает, что сломался

Каждое чтение поля JSON идёт через проверку типа: `?? null`, затем `is_array` / `is_string` / `is_numeric`. «Просто взять `$data['reviews'][0]['id']`» нельзя — отсутствующий ключ должен стать контролируемым сбоем.

Если ожидаемого поля нет, маппер бросает `SourceChangedException` с именем поля (`reviews[].reviewId`, `stack.0.results.items.0.ratingData.ratingValue` и т.д.).

Исключение ловит `ParseOrganizationJob`:

- в лог пишется ошибка и **обрезанный** сырой JSON (первые 1000 символов), чтобы не раздувать диск;
- организация получает `parse_status = failed`;
- в `parse_error` попадает текст вида `Structure changed: missing field reviews[].id`.

На этот тип исключения можно повесить алерт (Slack / почта) — в `config/logging.php` и `config/services.php` уже есть точки для Slack/mail, достаточно подключить канал.

На дашборде карточка и страница организации показывают бейдж **«Ошибка парсера»** и текст `parse_error`.

## 6. Масштабирование и фоновая обработка

Все парсинги идут через Laravel Queue Jobs, брокер — Redis. Контейнер `queue-worker` выполняет:

```bash
php artisan queue:work --sleep=1 --tries=3 --backoff=60 --timeout=240
```

У самой джобы тоже `$tries = 3`, `$backoff = 60` и `$timeout = 240`: после транзиентного сбоя Laravel подождёт 60 секунд и повторит попытку (до трёх раз). Смена схемы JSON (`SourceChangedException`) и `Rate limited` **не** ретраятся — сразу `failed`. `REDIS_QUEUE_RETRY_AFTER=300`, чтобы воркер не отдал ту же джобу второму процессу, пока первый ещё парсит.

Прогресс уходит на Node.js WS-сервер обычным HTTP; клиенты видят полосу «Загрузка отзывов: 127 / 580» в реальном времени.

Оценка на 50 организаций × ~600 отзывов: около 50 задач по примерно 2 минуты (паузы 0.5–1.5 с на страницу по 10 отзывов). Задачи не стартуют все в одном процессе — их разносит очередь.

Горизонтальное масштабирование: `docker compose up --scale queue-worker=3` (код менять не нужно). PHP-FPM и `ws-server` масштабируются отдельно; узкое место при росте — лимиты Яндекса, не CPU.

## 7. Идемпотентность и история изменений

В таблице `reviews` уникальность `(organization_id, yandex_review_id)`. Повторный парсинг делает `INSERT … ON CONFLICT DO UPDATE` (`upsert`): дубликаты не появляются, текст и оценка обновляются.

После каждого успешного прогона `organization_snapshots` хранит полный снимок рейтинга/числа отзывов и JSON-поле `diff`. Снимки смотрят так: `GET /api/organizations/{id}/snapshots` (сортировка по `snapshot_at`).

`diff` содержит только изменившиеся поля, например:

```json
{ "rating": { "from": 4.7, "to": 4.8 } }
```

Первый снимок имеет пустой `diff` (`[]`): сравнивать ещё не с чем.

## 8. Что добавил бы при большем времени

- Реальная обработка сессионных кук Яндекса (часть карточек может их требовать).
- Пул прокси через `PROXY_LIST`.
- Админ-панель со всеми задачами парсинга по всем пользователям.
- Webhook по завершении парсинга (`done` / `failed`).
- Кэш ответов Яндекса с настраиваемым TTL (сейчас слоя кэша нет — каждый парсинг ходит в сеть).
- E2E-тесты на Playwright (логин → добавление URL → прогресс → страница отзывов).
- CI/CD на GitHub Actions (`make lint` + `make test` в Docker).

## 9. Чек-лист готовности к продакшену

### Безопасность

- [x] `APP_KEY` задаётся через `php artisan key:generate` (в `.env.example` пустой — так и должно быть).
- [x] Для продакшена `APP_DEBUG=false` (в примере для локальной разработки стоит `true`; это описано в §1).
- [x] `WS_INTERNAL_SECRET` — сменить `changeme` на 32 случайных символа.
- [x] У токенов Sanctum есть срок жизни: `SANCTUM_EXPIRATION=10080` минут.
- [x] Все API-маршруты требуют `auth:sanctum`, кроме `POST /api/login`.
- [x] `OrganizationPolicy` отдаёт чужие организации только владельцу (403).

### Производительность

- [x] Индекс `reviews (organization_id, reviewed_at DESC)`.
- [x] Индекс по `organizations.user_id` (внешний ключ + префикс уникального `(user_id, yandex_url)`).
- [x] Пагинация отзывов — `LIMIT/OFFSET` по 50, не вся таблица.
- [x] Парсер качает отзывы страницами по 10, не по одному HTTP на отзыв.

### Надёжность

- [x] Воркер: `--tries=3 --backoff=60 --timeout=240`, `retry_after=300`.
- [x] `HttpWsNotifier` глотает сетевые сбои и пишет warning в лог.
- [x] Экспоненциальная пауза на 429/5xx; после трёх 429 — `Rate limited`.
- [x] DTO (`ReviewDTO`, `OrganizationDataDTO`, `ParseProgressDTO`) объявлены как `readonly`.

### Качество кода

- [x] PHPStan 8: `make lint`.
- [x] `vue-tsc --noEmit` / `npm run type-check`.
- [x] TypeScript `strict` во фронтенде и `ws-server`.
- [x] В `.ts`/`.vue` нет `any`.
- [x] Во всех PHP-файлах приложения `declare(strict_types=1)`.
- [x] Контроллеры тонкие: валидация в FormRequest, доступ в Policy, работа в сервисах/репозиториях.

### Тесты

- [x] `make test` — Pest + Vitest фронтенда + тесты `ws-server`.
- [x] Повторный parse не создаёт дубли отзывов.
- [x] Логин / выход / 401 без токена / 403 на чужую организацию.
- [x] Юнит-тесты URL и JSON-маппера, включая `SourceChangedException`.
