# ADM-02 — Управление пользователями

| | |
|---|---|
| **Эпик** | [EP-ADM](../00-epic.md) |
| **Пункт ТЗ** | 1.2 |
| **Оценка** | 13 SP |
| **Зависимости** | [ADM-08](ADM-08-foundation-and-handover.md) — enum-каркас `HasLabel`/`HasColor` |
| **Use cases** | [UC-2.1 … UC-2.10](../use-cases/UC-ADM-02-users.md) |

## Цель

Администратор видит раздельные списки клиентов и клинеров, заводит и правит учётные записи,
открывает карточку пользователя, меняет его статус, блокирует и восстанавливает доступ.

## Текущее состояние

`app/Filament/Resources/Users/UserResource.php` — минимальный ресурс: форма из пяти полей
(`name`, `phone`, `email`, `password`, `role`), таблица из пяти колонок, страницы List/Create/Edit.
Нет фильтров, нет View-страницы, нет работы с профилями, нет действий.

**Ключевое ограничение: колонки статуса или блокировки у `users` не существует.**
Схема после `2026_07_09_050000_add_klinomania_fields_to_users_table.php`:
`id, name, email, email_verified_at, password, phone, role, remember_token, timestamps`.
Ни `status`, ни `blocked_at`, ни `deleted_at`. Единственный аналог — `cleaner_profiles.is_active`,
и он относится только к клинерам.

Клиент и клинер различаются строковой колонкой `role` (`App\Enums\UserRole`) и наличием
`clientProfile()` / `cleanerProfile()` (оба `HasOne`).

Аутентификация API: клиенты — OTP по SMS (`ClientAuthController::requestCode()` / `verifyCode()`,
таблица `auth_codes`), клинеры — код доступа (`CleanerAuthController::login()`,
`cleaner_profiles.access_code_hash`). Токены — Sanctum.

## Объём работ

### Схема и домен

- [ ] Миграция: `users.status` (string, default `'active'`, индекс) и `users.blocked_at` (timestamp, nullable).
- [ ] `App\Enums\UserStatus`: `Active = 'active'`, `Blocked = 'blocked'`; реализует `HasLabel` и `HasColor`.
- [ ] Каст `status` в `User::casts()`, добавление в `#[Fillable]`.
- [ ] Действия `App\Modules\Identity\Actions\BlockUser` и `UnblockUser`: меняют статус,
      проставляют/снимают `blocked_at` и **отзывают все токены Sanctum** (`$user->tokens()->delete()`).

### Enforcement блокировки

- [ ] `ClientAuthController::verifyCode()` — отказ заблокированному клиенту (403, код `user_blocked`).
- [ ] `CleanerAuthController::login()` — то же для клинера.
- [ ] `User::canAccessPanel()` — дополнительно требовать `UserStatus::Active`.
- [ ] Обновить `docs/api/openapi.yaml` — новый код ответа на обоих эндпоинтах аутентификации.

### Панель

- [ ] Фильтр по роли и вкладки списка (`getTabs()` на `ListUsers`): все / клиенты / клинеры / администраторы.
- [ ] Фильтр по статусу, колонка статуса бейджем, поиск по имени, телефону и email.
- [ ] Страница `ViewUser` (`Infolist`): реквизиты, профиль, статус, дата регистрации.
- [ ] Relation manager заказов: для клиента — `CleaningOrder::client()`, для клинера — `User::cleaningOrders()`
      (пивот `cleaning_order_cleaners`).
- [ ] Форма создания и редактирования с блоком профиля: `ClientProfile` (`name`, `address`,
      `push_notifications_enabled`, `email_marketing_enabled`) и `CleanerProfile` (`name`, `is_active`),
      видимость блока зависит от выбранной роли.
- [ ] Действия «Заблокировать» и «Разблокировать» с подтверждением и нотификацией.
- [ ] Действие «Сбросить код доступа клинера» — генерация нового кода, запись хэша
      в `cleaner_profiles.access_code_hash`, показ кода администратору один раз.

## Затрагиваемые файлы

- `database/migrations/*_add_status_to_users_table.php` — новый
- `app/Enums/UserStatus.php` — новый
- `app/Models/User.php`
- `app/Modules/Identity/Actions/BlockUser.php`, `UnblockUser.php`, `ResetCleanerAccessCode.php` — новые
- `app/Modules/Identity/Http/Controllers/ClientAuthController.php`, `CleanerAuthController.php`
- `app/Filament/Resources/Users/UserResource.php`
- `app/Filament/Resources/Users/Pages/ListUsers.php`, `ViewUser.php` (новый)
- `app/Filament/Resources/Users/RelationManagers/OrdersRelationManager.php` — новый
- `docs/api/openapi.yaml`

## Критерии приёмки

1. В списке пользователей есть вкладки клиентов и клинеров и фильтры по роли и статусу.
2. Учётная запись создаётся и редактируется вместе с профилем соответствующего типа.
3. Карточка пользователя открывается и показывает реквизиты, профиль, статус и его заказы.
4. Статус меняется из списка и из карточки.
5. Блокировка отзывает все токены Sanctum: заблокированный клиент получает 401 по ранее выданному токену.
6. Заблокированный клиент не проходит OTP-вход, заблокированный клинер — вход по коду доступа.
7. Восстановление доступа возвращает статус `active` и снимает `blocked_at`.
8. Сброс кода доступа клинера выдаёт новый код, старый перестаёт работать.
9. `docs/api/openapi.yaml` обновлён.

## Тесты

- `tests/Feature/UserBlockingTest.php` — блокировка отзывает токены; заблокированный клиент
  не проходит `verifyCode`; заблокированный клинер не проходит `login`; разблокировка восстанавливает доступ.
- `tests/Feature/AdminPanelAccessTest.php` — дополнить сценарием заблокированного администратора.
- `tests/Feature/CleanerAccessCodeResetTest.php` — старый код невалиден, новый работает.

## Риски

- **Отзыв токенов обязателен.** Без него блокировка не действует до истечения токена — самый
  вероятный дефект в этой задаче.
- Изменение поведения эндпоинтов аутентификации затрагивает мобильные приложения:
  новый код ошибки нужно согласовать и отразить в OpenAPI.
- У клинера остаётся второй флаг `cleaner_profiles.is_active`. Нужно определить приоритет:
  предлагается считать `users.status` главным, а `is_active` — рабочей доступностью клинера
  для назначения на заказы. Зафиксировать в коде комментарием и в UC-2.6.
