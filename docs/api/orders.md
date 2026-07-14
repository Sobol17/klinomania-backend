# Orders API

## Клиентские заявки

Все endpoints клиентских заявок требуют Bearer token пользователя с ролью `client`.

### Создать заявку

`POST /api/v1/client/orders/checkout`

Request:

```json
{
  "cleaning_service_id": 1,
  "address": "Москва, ул. Примерная, 1",
  "scheduled_at": "2026-07-10T10:00:00+03:00",
  "comment": "Позвонить за 15 минут"
}
```

Response `201`:

```json
{
  "data": {
    "id": 1,
    "client_id": 1,
    "cleaner_id": null,
    "cleaning_service_id": 1,
    "status": "pending",
    "address": "Москва, ул. Примерная, 1",
    "scheduled_at": "2026-07-10T07:00:00.000000Z",
    "comment": "Позвонить за 15 минут",
    "total_price": 3500
  }
}
```

### История заявок клиента

`GET /api/v1/client/orders`

Response `200`:

```json
{
  "data": []
}
```

## Заявки клинера

Все endpoints клинера требуют Bearer token пользователя с ролью `cleaner`.

### Доступные заявки

`GET /api/v1/cleaner/orders/available`

Возвращает заявки со статусом `pending`.

### История выполненных заявок

`GET /api/v1/cleaner/orders/history`

Возвращает завершенные заявки текущего клинера.

### Взять заявку

`POST /api/v1/cleaner/orders/{order}/accept`

Разрешено только для заявки в статусе `pending`.

Новый статус: `accepted`.

### Начать работу

`POST /api/v1/cleaner/orders/{order}/start`

Разрешено только клинеру, который уже взял заявку, и только из статуса `accepted`.

Новый статус: `in_progress`.

### Завершить заявку

`POST /api/v1/cleaner/orders/{order}/complete`

Разрешено только клинеру, который выполняет заявку, и только из статуса `in_progress`.

Новый статус: `completed`.

## Статусы

- `pending` - заявка создана и ожидает клинера.
- `accepted` - клинер взял заявку.
- `in_progress` - клинер приступил к работе.
- `completed` - уборка завершена.
- `cancelled` - заявка отменена.
