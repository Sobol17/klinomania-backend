# ADM-01 — Авторизация и доступ администратора

| | |
|---|---|
| **Эпик** | [EP-ADM](../00-epic.md) |
| **Пункт ТЗ** | 1.1 |
| **Оценка** | 3 SP |
| **Зависимости** | UC-1.5 требует `users.status` из [ADM-02](ADM-02-users.md) |
| **Use cases** | [UC-1.1 … UC-1.6](../use-cases/UC-ADM-01-admin-auth.md) |

## Цель

Администратор входит в панель по логину и паролю через браузер; никто, кроме активного
администратора, попасть в панель не может.

## Текущее состояние

Пункт закрыт примерно на 80 %:

- `app/Providers/Filament/AdminPanelProvider.php` — панель `id('admin')`, `path('admin')`,
  включён `->login()`, гвард по умолчанию `web` (в `config/auth.php` он единственный);
- `app/Models/User.php` — `User implements FilamentUser`, `canAccessPanel(Panel $panel): bool`
  возвращает `$this->role === UserRole::Admin`;
- `database/seeders/AdminSeeder.php` создаёт администратора из `config/klinomania.php`
  (`KLINOMANIA_ADMIN_EMAIL`, `KLINOMANIA_ADMIN_PASSWORD`), покрыт `tests/Feature/AdminSeederTest.php`;
- `authMiddleware([Authenticate::class])` уже отсекает неавторизованных.

Чего не хватает: ограничения на подбор пароля, проверки блокировки, явного сообщения
об отказе неадминистратору и тестов на сам факт ограничения доступа.

## Объём работ

- [ ] Ограничить попытки входа на форме `/admin/login` (RateLimiter, по email + IP).
- [ ] Расширить `User::canAccessPanel()`: помимо роли `Admin` требовать `status === UserStatus::Active`
      (после ADM-02; до этого — только роль).
- [ ] Проверить и зафиксировать поведение при отказе: пользователь с ролью `client`/`cleaner`
      и верным паролем получает ошибку авторизации, а не пустую панель.
- [ ] Проверить выход из панели и инвалидацию сессии.
- [ ] Задать `->brandName()` и, при наличии, логотип — панель сдаётся заказчику.
- [ ] Тесты Pest на все шесть сценариев.

## Затрагиваемые файлы

- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Models/User.php` — `canAccessPanel()`
- `app/Providers/AppServiceProvider.php` — регистрация лимитера рядом с существующим `order-checkout`
- `tests/Feature/AdminPanelAccessTest.php` — новый

## Критерии приёмки

1. Администратор входит на `/admin/login` по email и паролю и попадает на дашборд.
2. Пользователь с ролью `client` или `cleaner` с верным паролем в панель не попадает.
3. Неавторизованный запрос к любому URL панели редиректится на `/admin/login`.
4. Выход завершает сессию; возврат «назад» в браузере панель не открывает.
5. Заблокированный администратор войти не может (после ADM-02).
6. Серия неверных паролей приводит к временной блокировке попыток входа.
7. `composer test` зелёный.

## Тесты

`tests/Feature/AdminPanelAccessTest.php` (Pest, `uses(RefreshDatabase::class)` — глобально он
не включён, см. `tests/Pest.php`):

- вход администратора → 200 на `/admin`;
- `canAccessPanel()` возвращает `false` для `client` и `cleaner`;
- гость на `/admin` → редирект на `/admin/login`;
- заблокированный администратор → отказ;
- превышение лимита попыток → 429 или сообщение о блокировке.

## Риски

- Порядок с ADM-02: UC-1.5 нельзя закрыть до появления `users.status`. Задачу можно сдать
  без него, но тогда UC-1.5 переезжает в ADM-02.
- Гвард `web` общий с публичной частью; отдельный `admin`-гвард не заводим — единственная
  сессионная поверхность в проекте и есть панель.
