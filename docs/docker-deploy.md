# Docker deploy guide

Подробный гайд описывает production-деплой Shale Tickets на VPS через Docker Compose. Базовый сценарий: один сервер, приложение Laravel 13, PostgreSQL, PHP-FPM, Nginx внутри compose и внешний HTTPS reverse proxy или балансировщик перед контейнером `nginx`.

## Что уже есть в проекте

В репозитории настроены:

- `Dockerfile` с multi-stage сборкой:
    - `base` - PHP 8.4 FPM Alpine с расширениями для Laravel и PostgreSQL;
    - `assets` - сборка Vite-ассетов через Node 22;
    - `app` - production-образ приложения с `composer install --no-dev`;
    - `nginx` - Nginx-образ со статикой из `public`.
- `docker-compose.yml` с сервисами:
    - `app` - PHP-FPM контейнер приложения;
    - `worker` - Laravel queue worker;
    - `nginx` - HTTP-вход в приложение на порту `80`;
    - `postgres` - PostgreSQL 17;
    - volumes `app_storage`, `postgres_data`.
- `.env.production.example` - пример production-переменных.
- `docker/nginx/default.conf` - Nginx-конфигурация для Laravel front controller.

`app_storage` монтируется в `app`, `worker` и `nginx`. Это важно для публичных загрузок: Laravel сохраняет файлы в `storage/app/public`, а Nginx отдает их через symlink `public/storage`, созданный в `nginx`-образе.

## Предварительные требования

На VPS должны быть установлены:

- Docker Engine;
- Docker Compose plugin;
- Git;
- доступ по SSH;
- домен, направленный на IP сервера;
- HTTPS reverse proxy перед compose-проектом.

Reverse proxy может быть внешним Nginx на хосте, Caddy, Traefik или балансировщик у провайдера. В текущем `docker-compose.yml` контейнер `nginx` публикует только HTTP:

```yaml
ports:
    - "80:80"
```

TLS-терминацию лучше держать снаружи compose-проекта. Контейнер приложения должен получать уже обычный HTTP-трафик от reverse proxy, а публичный `APP_URL` при этом должен быть `https://...`.

## Подготовка сервера

Создайте директорию для приложения:

```bash
sudo mkdir -p /var/www/shale-tickets
sudo chown "$USER":"$USER" /var/www/shale-tickets
cd /var/www/shale-tickets
```

Склонируйте репозиторий:

```bash
git clone <repository-url> .
```

Переключитесь на нужную ветку или tag:

```bash
git checkout main
```

Для production лучше деплоить зафиксированный tag или commit, а не случайное состояние ветки.

## Production env

Создайте `.env` из production-примера:

```bash
cp .env.production.example .env
```

Заполните обязательные значения:

```env
APP_NAME="Shale Tickets"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=shale_tickets
DB_USERNAME=postgres
DB_PASSWORD=postgres

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"

PAYMENT_GATEWAY=tbank
TBANK_API_URL=https://securepay.tinkoff.ru
TBANK_TERMINAL_KEY=
TBANK_PASSWORD=
TBANK_SUCCESS_URL="${APP_URL}/payment/success"
TBANK_FAIL_URL="${APP_URL}/payment/fail"
TBANK_NOTIFICATION_URL="${APP_URL}/payments/tbank/notifications"
```

Критичные правила:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_URL` должен быть публичным HTTPS URL без завершающего slash;
- `DB_HOST=postgres`, потому что приложение подключается к compose-сервису PostgreSQL;
- `PAYMENT_GATEWAY=tbank` для реальных платежей;
- `TBANK_NOTIFICATION_URL` должен быть доступен из интернета по HTTPS;
- секреты Т-Банка, SMTP и пароль базы не коммитятся в git.

Если `APP_KEY` еще не создан, сгенерируйте его после первого build:

```bash
docker compose run --rm app php artisan key:generate --show
```

Скопируйте выведенное значение в `.env`:

```env
APP_KEY=base64:...
```

Не запускайте production без `APP_KEY`: шифрование cookies, сессий и других данных Laravel будет работать некорректно.

## Первый build

Соберите production-образы:

```bash
docker compose build
```

Что происходит при сборке:

- Composer ставит PHP-зависимости без dev-пакетов;
- Node ставит frontend-зависимости через `npm ci`;
- Vite собирает ассеты в `public/build`;
- в итоговый app-образ попадает оптимизированный autoload;
- storage и bootstrap cache получают права для пользователя `www-data`.

Если сборка падает на `npm ci`, проверьте, что `package-lock.json` соответствует `package.json`. Если падает Composer, проверьте `composer.lock` и доступ к пакетам.

## Первый запуск

Поднимите контейнеры:

```bash
docker compose up -d
```

Проверьте состояние:

```bash
docker compose ps
```

Ожидаемый минимум:

- `app` в состоянии running;
- `worker` в состоянии running;
- `nginx` в состоянии running;
- `postgres` в состоянии running;

Если контейнер перезапускается, смотрите логи:

```bash
docker compose logs --tail=100 app
docker compose logs --tail=100 worker
docker compose logs --tail=100 nginx
docker compose logs --tail=100 postgres
```

## Миграции и первичная инициализация

После запуска выполните миграции:

```bash
docker compose exec app php artisan migrate --force
```

Создайте symlink для публичных файлов:

```bash
docker compose exec app php artisan storage:link
```

Оптимизируйте Laravel cache:

```bash
docker compose exec app php artisan optimize
```

Если нужно заполнить базовые данные и создать локального администратора из seeders:

```bash
docker compose exec app php artisan db:seed --force
```

Перед запуском `db:seed --force` на production проверьте, что seeders не перетрут реальные данные и соответствуют текущему окружению.

## Reverse proxy и HTTPS

Текущий compose публикует приложение на `http://server-ip:80`. Для production настройте HTTPS перед ним.

Пример внешнего Nginx на хосте:

```nginx
server {
    listen 80;
    server_name example.com www.example.com;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name example.com www.example.com;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

Если внешний reverse proxy тоже слушает порт `80`, измените порт compose-сервиса `nginx`, например:

```yaml
ports:
    - "127.0.0.1:8080:80"
```

Тогда `proxy_pass` должен указывать на `http://127.0.0.1:8080`.

## Очередь

Сервис `worker` запускает:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Проверка логов worker:

```bash
docker compose logs --tail=100 worker
```

Перезапуск worker после деплоя:

```bash
docker compose exec app php artisan queue:restart
docker compose restart worker
```

`queue:restart` просит worker корректно завершиться после текущей job. `docker compose restart worker` полезен, если нужно принудительно перечитать образ и env.

## Планировщик Laravel

В текущем `docker-compose.yml` отдельного scheduler-сервиса нет. Если в проекте появятся scheduled tasks, добавьте сервис:

```yaml
scheduler:
    build:
        context: .
        target: app
    restart: unless-stopped
    command: php artisan schedule:work
    env_file:
        - .env
    depends_on:
        - app
        - postgres
    volumes:
        - app_storage:/var/www/html/storage
```

До появления scheduled tasks этот сервис не обязателен.

## Обновление приложения

Типовой деплой новой версии:

```bash
cd /var/www/shale-tickets
git fetch --all --tags
git checkout <tag-or-commit>
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app php artisan queue:restart
docker compose restart worker
```

Если менялись `.env` или секреты:

```bash
docker compose up -d --force-recreate
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
docker compose restart worker
```

Порядок важен: сначала новая версия кода и контейнеры, затем миграции, затем оптимизация и перезапуск worker.

## Откат версии

Если новая версия не прошла smoke-check:

```bash
git checkout <previous-tag-or-commit>
docker compose build
docker compose up -d
docker compose exec app php artisan optimize
docker compose exec app php artisan queue:restart
docker compose restart worker
```

Откат кода не всегда откатывает базу. Если новая миграция изменила структуру данных, решение об откате БД принимайте отдельно: через backup restore или вручную подготовленную down-миграцию.

## Backup

Минимум для production:

- регулярный backup PostgreSQL;
- backup `.env`;
- backup volume `app_storage`, если там есть пользовательские загрузки или публичные файлы;
- проверка восстановления на отдельной среде.

Пример дампа PostgreSQL:

```bash
docker compose exec -T postgres pg_dump -U shale shale_tickets > backup-$(date +%F).sql
```

Восстановление в пустую базу:

```bash
docker compose exec -T postgres psql -U shale shale_tickets < backup-2026-05-30.sql
```

Храните backup вне сервера приложения. Пароль базы и `.env` должны храниться в защищенном месте.

## Логи и диагностика

Логи контейнеров:

```bash
docker compose logs -f app
docker compose logs -f worker
docker compose logs -f nginx
```

Laravel-логи внутри volume:

```bash
docker compose exec app ls -la storage/logs
docker compose exec app tail -n 100 storage/logs/laravel.log
```

Проверка подключения к базе:

```bash
docker compose exec app php artisan tinker
```

В tinker:

```php
DB::select('select 1');
```

Проверка маршрутов:

```bash
docker compose exec app php artisan route:list
```

Проверка конфигурации:

```bash
docker compose exec app php artisan about
```

## Smoke-check после деплоя

После каждого production-деплоя проверьте:

- главная страница открывается по HTTPS;
- страницы экскурсий открываются и используют production-ассеты из `public/build`;
- форма бронирования создает booking без ошибок;
- `/admin` недоступен обычному пользователю;
- `/admin` доступен пользователю с `is_admin=true`;
- SMTP отправляет письма;
- worker обрабатывает очередь;
- Т-Банк может достучаться до `TBANK_NOTIFICATION_URL`;
- успешное уведомление Т-Банка переводит booking в подтвержденное состояние и отправляет email клиенту.

## Частые проблемы

### Приложение не подключается к PostgreSQL

Проверьте `.env`:

```env
DB_HOST=postgres
DB_PORT=5432
```

Внутри compose нельзя использовать `127.0.0.1` для PostgreSQL, потому что это будет localhost контейнера `app`, а не сервис базы.

### Не открываются CSS и JS

Проверьте, что сборка дошла до этапа `npm run build`, а в Nginx-образ попала директория `public/build`:

```bash
docker compose exec app ls -la public/build
```

После изменения frontend-кода нужен новый `docker compose build`.

### Ошибка `No application encryption key has been specified`

Сгенерируйте ключ:

```bash
docker compose run --rm app php artisan key:generate --show
```

Запишите значение в `.env` как `APP_KEY=base64:...`, затем пересоздайте контейнеры:

```bash
docker compose up -d --force-recreate
```

### Не работают сессии

Если `SESSION_DRIVER=database`, таблица `sessions` должна существовать. Обычно она создается миграциями. Проверьте миграции:

```bash
docker compose exec app php artisan migrate:status
```

Для простой диагностики можно временно использовать файловые сессии:

```env
SESSION_DRIVER=file
```

После изменения `.env` пересоздайте контейнеры и сбросьте optimize cache.

### Очередь не обрабатывает job

Проверьте `QUEUE_CONNECTION` и worker:

```bash
docker compose ps worker
docker compose logs --tail=100 worker
docker compose exec app php artisan queue:failed
```

После исправления причины можно повторить failed jobs:

```bash
docker compose exec app php artisan queue:retry all
```

### Т-Банк не присылает уведомления

Проверьте:

- `APP_URL` начинается с `https://`;
- `TBANK_NOTIFICATION_URL` публично доступен;
- reverse proxy пробрасывает запросы на приложение;
- route `/payments/tbank/notifications` есть в `php artisan route:list`;
- в логах `app` нет ошибок проверки token/signature;
- terminal key и password соответствуют окружению Т-Банка.

## Минимальный production checklist

Перед открытием продаж:

- `.env` заполнен production-значениями;
- `APP_KEY` создан и сохранен;
- `APP_DEBUG=false`;
- домен ведет на сервер;
- HTTPS работает;
- миграции выполнены;
- `storage:link` выполнен;
- worker работает;
- SMTP проверен;
- Т-Банк notification URL проверен;
- backup PostgreSQL настроен;
- smoke-check пройден.
