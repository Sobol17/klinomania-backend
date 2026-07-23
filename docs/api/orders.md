# Orders API

## Клиентские заявки

Все endpoints клиентских заявок требуют Bearer token пользователя с ролью `client`.

### Создать заявку

`POST /api/v1/client/orders`

Request:

```json
{
  "service_id": "standard",
  "room_option_id": "room-2",
  "cleaning_option_id": "support",
  "extra_option_ids": ["fridge-inside"],
  "address": {"full_address": "Москва, ул. Примерная, 1"},
  "scheduled_at": "2026-07-10T10:00:00+03:00",
  "comment": "Позвонить за 15 минут"
}
```

Response `201`:

```json
{
  "data": {
    "id": "01J2QM1R7H7YV9JH1KACD6ZK3R",
    "status": "processing",
    "status_label": "В обработке",
    "scheduled_at": "2026-07-10T07:00:00Z",
    "total_price": 9100,
    "currency": "RUB",
    "service": {"id": "standard", "title": "Базовый минимум"}
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

### Детали заявки клиента

`GET /api/v1/client/orders/{public_id}`

Возвращает заявку вместе с услугой, назначенными клинерами, зафиксированным
адресом (`address`) и строками расчёта (`line_items`). Формат `line_items` и
`extra_options` совпадает с деталями заявки клинера, но внутренние начисления
клинерам в клиентский API не передаются. `{public_id}` — публичный ULID заявки
из списка, а не числовой ID базы данных.

Endpoint доступен только владельцу заявки. Для заявки другого клиента backend
возвращает `403`, для неизвестного `public_id` — `404`.

### Отменить заявку

`POST /api/v1/client/orders/{public_id}/cancel`

Клиент может отменить только свою заявку в статусе `processing` или `confirmed`.

### Получить ссылку на оплату Т-Банка

`POST /api/v1/client/orders/{public_id}/payment`

Доступно только владельцу заявки в статусе `awaiting_payment`. Backend создаёт операцию в Т-Банке и возвращает готовую ссылку на платёжную форму. Повторный запрос до истечения срока возвращает ту же активную ссылку.

Response `200`:

```json
{
  "data": {
    "id": "pay_01J2QM1R7H7YV9JH1KACD6ZK3R",
    "payment_url": "https://securepay.tinkoff.ru/new/...",
    "expires_at": "2026-07-19T12:00:00Z",
    "status": "pending"
  }
}
```

Т-Банк отправляет статус напрямую на `POST /api/v1/payments/tbank/notifications`. Этот endpoint не предназначен для мобильного клиента: backend проверяет `Token`, терминал и сумму операции. Только валидное уведомление с `Success=true` и `Status=CONFIRMED` переводит заявку в `completed`; возврат пользователя из платёжной формы на статус заявки не влияет.

Правила регистрации устройства и payload статусных уведомлений приведены в
`push-notifications.md`.

## Заявки клинера

Все endpoints клинера требуют Bearer token пользователя с ролью `cleaner`.

### Доступные заявки

`GET /api/v1/cleaner/orders/available`

Возвращает подтверждённые заявки с незаполненной командой.

`cleaner_earnings` — ожидаемая доля текущего клинера в целых рублях после присоединения к заявке: начисление команды делится на текущее число участников плюс один.

### Мои заявки

`GET /api/v1/cleaner/orders`

Возвращает заявки текущего клинера после подтверждения, в которых он состоит в команде: `team_formed`, `in_progress`, `awaiting_payment` и `completed`.

В каждой заявке `cleaner_earnings` — доля текущего клинера в целых рублях, рассчитанная из сохранённых при оформлении начислений и числа назначенных клинеров.

### Детали заявки

`GET /api/v1/cleaner/orders/{public_id}`

Требует Bearer token пользователя с ролью `cleaner`. `{public_id}` — публичный ULID заявки из списка, а не числовой ID базы данных.

Доступен только клинеру, включённому в команду заявки. Для остальных клинеров backend возвращает `403 forbidden`.

Response `200`:

```json
{
  "data": {
    "id": "01J2QM1R7H7YV9JH1KACD6ZK3R",
    "status": "in_progress",
    "status_label": "В работе",
    "scheduled_at": "2026-07-20T11:00:00Z",
    "total_price": 9400,
    "cleaner_earnings": 2800,
    "currency": "RUB",
    "service": {
      "id": "standard",
      "title": "Базовый минимум"
    },
    "address": {
      "full_address": "Москва, Зеленоград, улица Ленина, 1",
      "entrance": "11",
      "floor": "44",
      "apartment": "44",
      "intercom": "44",
      "comment": "Позвонить за 15 минут"
    },
    "line_items": [
      {
        "kind": "base",
        "option_id": null,
        "title": "Базовый минимум",
        "amount": 7700
      },
      {
        "kind": "extra_option",
        "option_id": "fridge-inside",
        "title": "Холодильник внутри",
        "amount": 800
      },
      {
        "kind": "extra_option",
        "option_id": "oven-inside",
        "title": "Духовка внутри",
        "amount": 900
      }
    ],
    "extra_options": [
      {
        "kind": "extra_option",
        "option_id": "fridge-inside",
        "title": "Холодильник внутри",
        "amount": 800
      },
      {
        "kind": "extra_option",
        "option_id": "oven-inside",
        "title": "Духовка внутри",
        "amount": 900
      }
    ]
  }
}
```

- `line_items` содержит все зафиксированные строки цены: базовую услугу, выбранные параметры и дополнительные работы.
- `extra_options` — подмножество `line_items` только с `kind: extra_option`; используйте его для отображения выбранных допуслуг.
- `amount` — цена строки в целых рублях.
- `cleaner_earnings` — доля текущего клинера в целых рублях; у уже созданных до появления начислений строк она считается равной `0`.

### История выполненных заявок

`GET /api/v1/cleaner/orders/history`

Возвращает заявки текущего клинера, по которым работа завершена: `awaiting_payment` и `completed`.

Каждая заявка содержит `cleaner_earnings` — долю текущего клинера в целых рублях.

### Взять заявку

`POST /api/v1/cleaner/orders/{order}/accept`

Разрешено только для заявки в статусе `confirmed`, пока команда не заполнена.

После набора требуемого количества клинеров новый статус: `team_formed`.

### Начать работу

`POST /api/v1/cleaner/orders/{order}/start`

Разрешено только участнику сформированной команды.

Новый статус: `in_progress`.

### Завершить заявку

`POST /api/v1/cleaner/orders/{order}/complete`

Разрешено только клинеру, который выполняет заявку, и только из статуса `in_progress`.

Новый статус: `awaiting_payment`.

## Статусы

- `processing` - заявка находится в обработке модератора.
- `confirmed` - заявка подтверждена и видна клинерам.
- `team_formed` - требуемая команда клинеров сформирована.
- `in_progress` - первый участник команды приступил к работе.
- `awaiting_payment` - работа клинеров завершена, клиенту доступна оплата.
- `completed` - оплата подтверждена платёжным провайдером; жизненный цикл заявки завершён.
- `cancelled` - клиент отменил заявку до формирования команды.
