# API Errors

Laravel возвращает JSON для запросов `api/*`. Текущий формат ошибок соответствует стандартному Laravel response.

## Validation Error

HTTP `422`:

```json
{
  "message": "The phone field is required.",
  "errors": {
    "phone": [
      "The phone field is required."
    ]
  }
}
```

## Unauthorized

HTTP `401`:

```json
{
  "message": "Unauthenticated."
}
```

## Forbidden

HTTP `403`:

```json
{
  "message": "This action is unauthorized."
}
```

## Conflict

HTTP `409` используется для недопустимых переходов статусов заявки, например попытка начать заявку, которую клинер не взял.

```json
{
  "message": "Conflict"
}
```

## Целевой формат

Позже проект должен перейти к единому доменному формату ошибок:

```json
{
  "message": "Invalid code.",
  "code": "auth.invalid_code",
  "errors": {}
}
```

До внедрения отдельного error layer в OpenAPI документируется текущий Laravel-compatible формат.
