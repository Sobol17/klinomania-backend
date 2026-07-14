# Auth API

## Клиент

Клиент авторизуется в два этапа по номеру телефона.

### Запрос кода

`POST /api/v1/client/auth/request-code`

Request:

```json
{
  "phone": "+79990000001"
}
```

Response `200`:

```json
{
  "message": "Code sent."
}
```

Пользователь не создается на этом этапе. Создается только запись OTP в `auth_codes`.

### Проверка кода

`POST /api/v1/client/auth/verify-code`

Request:

```json
{
  "phone": "+79990000001",
  "code": "4829"
}
```

Response `200`:

```json
{
  "token": "1|plain-text-token",
  "user": {
    "id": 1,
    "name": null,
    "email": null,
    "phone": "+79990000001",
    "role": "client"
  }
}
```

Пользователь с ролью `client` создается только после успешного `verify-code`. Также создается `clientProfile`.

## Клинер

Клинер создается в админке. Для входа используется телефон и 6-значный код.

`POST /api/v1/cleaner/auth/login`

Request:

```json
{
  "phone": "+79990000003",
  "code": "111111"
}
```

Response `200`:

```json
{
  "token": "2|plain-text-token",
  "user": {
    "id": 2,
    "name": "Cleaner",
    "phone": "+79990000003",
    "role": "cleaner"
  }
}
```

## Dev-заглушки

Клиентский OTP по умолчанию генерируется случайно и отправляется через SMS. Для локальной разработки можно явно включить временный клиентский код:

- клиентский OTP при `KLINOMANIA_CLIENT_OTP_STUB_ENABLED=true`: `1111`;
- код клинера: `111111`.

Настройки находятся в `config/klinomania.php` и `.env.example`:

```dotenv
KLINOMANIA_CLIENT_OTP_STUB_ENABLED=false
KLINOMANIA_CLIENT_OTP_STUB_CODE=1111
KLINOMANIA_CLEANER_CODE_STUB_ENABLED=true
KLINOMANIA_CLEANER_CODE_STUB_CODE=111111
```

Перед production заглушка клиентского OTP должна быть отключена, а отправка SMS должна идти через Notisend-реализацию `SmsGateway`.
