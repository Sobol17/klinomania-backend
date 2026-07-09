# Deploy

Первый production-деплой рассчитан на VPS + Docker Compose.

Подробный пошаговый гайд: [Docker deploy guide](docker-deploy.md).

## Подготовка

1. Настроить домен и HTTPS на уровне reverse proxy или внешнего балансировщика.
2. Скопировать `.env.production.example` в `.env`.
3. Заполнить `APP_KEY`, PostgreSQL credentials, SMTP.

## Запуск

```bash
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan optimize
docker compose exec app php artisan db:seed --force
```

## Smoke-check

- Главная страница открывается.
- `/admin` доступен только пользователю с `is_admin=true`.
- Worker запущен и обрабатывает очередь.
