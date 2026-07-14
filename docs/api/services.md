# Services and checkout API

Все endpoint'ы ниже требуют Sanctum token пользователя с ролью `client`.

- `GET /api/v1/client/home-summary` — число активных заявок и статус ближайшей.
- `GET /api/v1/client/services` — активный каталог в `sort_order`; `id` — стабильный slug.
- `GET /api/v1/client/services/{serviceId}` — карточка услуги, правила цены и варианты конфигурации. Выключенная услуга возвращает `404 service_not_found`.
- `POST /api/v1/client/service-quotes` — рассчитывает и сохраняет quote на 15 минут. Цена, площадь и совместимость опций проверяются на сервере.
- `POST /api/v1/client/orders` — создаёт заказ из quote. Заголовок `Idempotency-Key` обязателен и должен быть UUID v4; ключ уникален в пределах клиента.

Заказ не принимает цену, услугу или options напрямую. Он использует неизменяемый снимок quote, а также сохраняет снимки адреса и строк цены. Повтор с тем же idempotency key и тем же quote возвращает исходный заказ; с другим quote — `409 idempotency_key_conflict`.

Тела запросов и ответов, включая схемы `Service`, `ServiceQuote` и `CreateOrderRequest`, зафиксированы в [OpenAPI contract](openapi.yaml).
