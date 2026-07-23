# Production-деплой через Docker Compose

Этот документ описывает деплой текущего Klinomania Backend (Laravel 13, PHP 8.4) на один VPS. На хосте уже работает внешний Nginx, который завершает HTTPS и проксирует запросы на `127.0.0.1:8080`.

Схема запуска:

```text
Интернет → внешний Nginx VPS (HTTPS) → 127.0.0.1:8080
                                         ↓
Docker Compose: web → app
                queue, scheduler, postgres
```

В проекте используется PostgreSQL с базой `klinomania_backend`. Cache, sessions и очередь работают через database-драйверы Laravel. PostgreSQL доступен только внутри compose-сети. Единственный опубликованный контейнерный порт — `127.0.0.1:8080` сервиса `web`.

## Состав

- `app` — Laravel и PHP-FPM;
- `web` — внутренний Nginx, отдающий `public/` и передающий PHP-запросы в `app:9000`;
- `queue` — `php artisan queue:work`;
- `scheduler` — `php artisan schedule:work`;
- `postgres` — PostgreSQL 17, база `klinomania_backend`.

Образ приложения собирается из [Dockerfile](../Dockerfile), сервисы описаны в [docker-compose.yaml](../docker-compose.yaml), конфигурация внутреннего Nginx находится в [docker/nginx/default.conf](../docker/nginx/default.conf).

## 1. Подготовка проекта

На VPS:

```bash
sudo mkdir -p /var/www/klinomania-backend
sudo chown "$USER":"$USER" /var/www/klinomania-backend
git clone <repository-url> /var/www/klinomania-backend
cd /var/www/klinomania-backend
cp .env.example .env
```

Если Docker еще не установлен, выполните шаги из [гайда подготовки VPS](vps-setup.md).

## 2. Production `.env`

Минимально измените следующие переменные:

```dotenv
APP_NAME=Klinomania
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.example.com

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=klinomania_backend
DB_USERNAME=klinomania
DB_PASSWORD=<long-random-password>

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

KLINOMANIA_CLIENT_OTP_STUB_ENABLED=false
KLINOMANIA_CLEANER_CODE_STUB_ENABLED=false
KLINOMANIA_ADMIN_EMAIL=<admin-email>
KLINOMANIA_ADMIN_PASSWORD=<long-random-password>

NOTISEND_PROJECT=klinomania
NOTISEND_API_KEY=<notisend-api-key>

TBANK_TERMINAL_KEY=<terminal-key>
TBANK_PASSWORD=<terminal-password>
TBANK_NOTIFICATION_URL=${APP_URL}/api/v1/payments/tbank/notifications
```

`docker-compose.yaml` принудительно задает контейнерное имя `postgres` и database-драйверы Laravel. Значения `DB_DATABASE`, `DB_USERNAME` и `DB_PASSWORD` берутся из `.env` одновременно приложением и контейнером PostgreSQL.

Сгенерируйте надежные пароли любым доступным password manager. `.env` и Firebase service-account JSON нельзя коммитить.

Положите Firebase service-account JSON в корень проекта на сервере под именем
`firebase.json`. `docker-compose.yaml` монтирует его в PHP-контейнеры read-only
как `/run/secrets/firebase.json`. В `.env` укажите контейнерный путь:

```dotenv
FIREBASE_CREDENTIALS_PATH=/run/secrets/firebase.json
```

## 3. Сборка и первый запуск

Сначала соберите образы:

```bash
docker compose build
```

Создайте `APP_KEY` без запуска постоянных сервисов:

```bash
docker compose run --rm app php artisan key:generate --show
```

Скопируйте выведенное значение `base64:...` в `APP_KEY` файла `.env`. При первом запуске сначала поднимите только PostgreSQL и PHP-FPM:

```bash
docker compose up -d postgres app
```

Дождитесь состояния `healthy` у `app` и `postgres`. Затем выполните миграции, создающие в том числе таблицы `cache`, `sessions`, `jobs`, `job_batches` и `failed_jobs`, и прогрейте production-кеши:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

Только после миграций запустите `web`, очередь и планировщик:

```bash
docker compose up -d
docker compose ps
```

Если весь стек уже был запущен до миграций и `queue` непрерывно пишет `relation "cache" does not exist`, исправьте состояние без пересборки образов:

```bash
docker compose stop queue scheduler
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose start queue scheduler
```

Symlink `public/storage` уже создается при сборке образа. Общий volume `app_storage` подключен к PHP-сервисам на запись и к `web` только на чтение.

Seeders не запускаются автоматически. Для первичного заполнения каталога и создания администратора сначала проверьте значения `KLINOMANIA_ADMIN_EMAIL` и `KLINOMANIA_ADMIN_PASSWORD`, затем явно выполните:

```bash
docker compose exec app php artisan db:seed --force
```

## 4. Внешний Nginx на VPS

Compose уже публикует внутренний Nginx так:

```yaml
ports:
  - "127.0.0.1:8080:80"
```

Порт недоступен извне напрямую. Пример HTTPS server block внешнего Nginx:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.example.com;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name api.example.com;

    ssl_certificate /etc/letsencrypt/live/api.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.example.com/privkey.pem;

    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Проверьте и перечитайте конфигурацию:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Локальная проверка до настройки DNS/HTTPS:

```bash
curl -H 'Host: api.example.com' http://127.0.0.1:8080/up
```

Ожидаемый ответ — HTTP 200.

## 5. Обычный деплой обновления

```bash
cd /var/www/klinomania-backend
git fetch --all --tags
git checkout <tag-or-commit>
docker compose build
docker compose up -d --remove-orphans
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app php artisan queue:restart
```

`queue` ограничен `--max-time=3600`, поэтому периодически перезапускается политикой `unless-stopped` и освобождает накопленную процессом память. После нового образа `docker compose up -d` пересоздает изменившиеся сервисы.

Если изменился только `.env`:

```bash
docker compose up -d --force-recreate
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
```

### Полная очистка очереди

Следующие команды безвозвратно удаляют все ожидающие и failed jobs, после чего
перезапускают queue worker:

```bash
docker compose stop queue
docker compose exec app php artisan queue:clear database --queue=default --force
docker compose exec app php artisan queue:flush
docker compose up -d --force-recreate queue
```

## 6. Логи и диагностика

```bash
docker compose ps
docker compose logs --tail=100 app
docker compose logs --tail=100 web
docker compose logs --tail=100 queue
docker compose logs --tail=100 scheduler
docker compose logs --tail=100 postgres
```

Полезные проверки Laravel:

```bash
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
docker compose exec app php artisan route:list
docker compose exec app php artisan queue:failed
docker compose exec app php artisan schedule:list
```

Если приложение не подключается к БД, убедитесь, что внутри контейнеров используются `DB_HOST=postgres`, `DB_DATABASE=klinomania_backend`. `127.0.0.1` внутри `app` указывает на сам контейнер, а не на PostgreSQL.

Если очередь не работает, проверьте `QUEUE_CONNECTION=database`, состояние `queue`, подключение к PostgreSQL и наличие таблиц `jobs` и `failed_jobs`. После исправления failed jobs можно повторить командой:

```bash
docker compose exec app php artisan queue:retry all
```

### Docker Hub отвечает `429 Too Many Requests`

Ошибка вида:

```text
failed to resolve source metadata for docker.io/library/php:8.3-fpm-bookworm:
unexpected status ... 429 Too Many Requests
```

возникает до выполнения инструкций Dockerfile: Docker Hub ограничил запросы с IP сервера. Это не означает, что тег PHP отсутствует. Авторизуйтесь на VPS в Docker Hub, используя логин и access token вместо пароля:

```bash
docker login -u <docker-hub-username>
```

После успешного `Login Succeeded` повторите сборку с одним параллельным заданием:

```bash
docker compose --parallel 1 build
docker compose up -d
```

Если Docker Hub по-прежнему возвращает короткий `429`, сработал общий anti-abuse limit для IP. Подождите указанный в ответе `Retry-After` интервал и повторите команду. Не используйте `--pull` и `--no-cache` при таком повторе: они создают лишние обращения и не исправляют ограничение registry.

## 7. Резервное копирование PostgreSQL

Создание дампа на хосте:

```bash
mkdir -p backups
docker compose exec -T postgres pg_dump \
    -U klinomania \
    -d klinomania_backend \
    --format=custom > "backups/klinomania_backend-$(date +%F-%H%M).dump"
```

Восстановление заменяет данные и требует отдельного maintenance-плана. Не проверяйте restore впервые на production: регулярно восстанавливайте дамп на отдельном окружении. Кроме БД сохраняйте `.env`, Firebase credentials и volume `app_storage`; копии храните вне VPS.

## 8. Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`;
- созданы уникальные `APP_KEY`, `DB_PASSWORD` и пароль администратора;
- dev OTP-коды отключены;
- наружу открыт только внешний Nginx, Docker слушает `127.0.0.1:8080`;
- миграции выполнены, `/up` отвечает HTTP 200;
- `app`, `web`, `queue`, `scheduler`, `postgres` запущены;
- `/admin` доступен только пользователю с ролью `Admin`;
- проверены Notisend, Firebase и callback Т-Банка;
- настроены и протестированы резервные копии.
