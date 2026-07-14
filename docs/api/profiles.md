# Profiles API

## Профиль клиента

`GET /api/v1/client/profile`

Требует Bearer token пользователя с ролью `client`.

Response `200`:

```json
{
  "data": {
    "id": 1,
    "name": null,
    "email": null,
    "phone": "+79990000001",
    "role": "client",
      "client_profile": {
        "id": 1,
        "user_id": 1,
        "name": null,
        "address": null,
        "push_notifications_enabled": false,
        "email_marketing_enabled": false
      }
    }
  }
```

### Редактирование профиля клиента

`PATCH /api/v1/client/profile`

Требует Bearer token пользователя с ролью `client`. Все поля необязательные; переданные поля обновляются частично. Телефон через этот endpoint не меняется.

Request:

```json
{
  "name": "Иван",
  "email": "ivan@example.com",
  "address": "Москва, ул. Тверская, 1",
  "push_notifications_enabled": true,
  "email_marketing_enabled": false
}
```

Response `200`:

```json
{
  "data": {
    "id": 1,
    "name": "Иван",
    "email": "ivan@example.com",
    "phone": "+79990000001",
    "role": "client",
    "client_profile": {
      "id": 1,
      "user_id": 1,
      "name": "Иван",
      "address": "Москва, ул. Тверская, 1",
      "push_notifications_enabled": true,
      "email_marketing_enabled": false
    }
  }
}
```

## Профиль клинера

`GET /api/v1/cleaner/profile`

Требует Bearer token пользователя с ролью `cleaner`.

Response `200`:

```json
{
  "data": {
    "id": 2,
    "name": "Cleaner",
    "email": null,
    "phone": "+79990000003",
    "role": "cleaner",
    "cleaner_profile": {
      "id": 1,
      "user_id": 2,
      "name": null,
      "is_active": true
    }
  }
}
```

`access_code_hash` не возвращается в API.
