# ADM-06 — Управление жалобами (новый домен)

| | |
|---|---|
| **Эпик** | [EP-ADM](../00-epic.md) |
| **Пункт ТЗ** | 1.6 |
| **Оценка** | 13 SP |
| **Зависимости** | [ADM-08](ADM-08-foundation-and-handover.md) — enum-каркас |
| **Use cases** | [UC-6.1 … UC-6.6](../use-cases/UC-ADM-06-complaints.md) |

## Цель

Клиент подаёт жалобу по заказу, администратор видит список жалоб, открывает жалобу по конкретному
заказу и меняет статус её обработки.

**Правило допустимости (согласовано заказчиком).** Жалоба принимается только когда заявка
выполняется или уже выполнена. Разрешённые статусы заказа: `in_progress`, `awaiting_payment`,
`completed`. Отклоняется в `processing`, `confirmed` и `team_formed` — работа ещё не начата,
жаловаться не на что; и в `cancelled` — заявка не выполнялась.

## Текущее состояние

**Домена не существует.** Поиск по `complaint|жалоб|отзыв|review` не даёт совпадений
ни в `app/`, ни в `database/`, ни в `routes/`, ни в `docs/`. Нет таблицы, модели, enum, эндпоинтов,
уведомлений и интерфейса.

Соответственно, задача — полный вертикальный срез: БД → домен → API → уведомления → админка → OpenAPI.
Объём подтверждён заказчиком.

Ориентиры для реализации в существующем коде:

- структура модуля — `app/Modules/Orders/` (`Actions`, `Http/Controllers`);
- email администраторам — `app/Modules/Notifications/Notifications/NewOrderCreatedNotification.php`
  (`ShouldQueue`, `tries = 3`, `backoff = [60, 300]`, `afterCommit()`, ссылка на ресурс через
  `CleaningOrderResource::getUrl('edit', ...)`) и слушатель
  `app/Modules/Notifications/Listeners/NotifyAdminsAboutNewOrder.php` (выборка `User` с ролью
  `Admin` и непустым email), зарегистрированный в `AppServiceProvider::boot()`;
- тест-образец — `tests/Feature/NewOrderEmailNotificationTest.php`.

## Объём работ

### Схема и домен

- [ ] Миграция `complaints`:
      `id`, `cleaning_order_id` (FK, cascade), `client_id` (FK на `users`, cascade),
      `status` (string, default `'new'`, индекс), `subject` (string), `message` (text),
      `admin_comment` (text, nullable), `resolved_at` (timestamp, nullable),
      `resolved_by` (FK на `users`, nullOnDelete), `timestamps`.
- [ ] `App\Enums\ComplaintStatus`: `New = 'new'`, `InProgress = 'in_progress'`,
      `Resolved = 'resolved'`, `Rejected = 'rejected'`; реализует `HasLabel` и `HasColor`.
- [ ] Модель `App\Models\Complaint` в стиле проекта (атрибут `#[Fillable([...])]`, каст `status`),
      связи `order()`, `client()`, `resolvedBy()`; в `CleaningOrder` — `complaints(): HasMany`.
- [ ] Модуль `app/Modules/Complaints/`:
      `Actions\CreateComplaint::execute(User $client, CleaningOrder $order, array $input): Complaint`,
      `Actions\ChangeComplaintStatus::execute(Complaint $complaint, ComplaintStatus $status, ?User $admin, ?string $comment): Complaint`.
- [ ] Событие `ComplaintCreated` (`ShouldDispatchAfterCommit`, по образцу `OrderCreated`).

### API

- [ ] `POST /api/v1/client/orders/{order}/complaints` — подача жалобы. Правила: заказ принадлежит
      вызывающему клиенту; статус заказа входит в `in_progress`, `awaiting_payment`, `completed`
      (иначе 409 с кодом `complaint_not_allowed`); `subject` и `message` обязательны.
- [ ] Проверку допустимого статуса вынести в один метод (например,
      `OrderStatus::allowsComplaint(): bool`), чтобы правило не разъезжалось между
      валидацией запроса, действием и интерфейсом панели.
- [ ] `GET /api/v1/client/complaints` — список жалоб клиента.
- [ ] Ограничение частоты подачи (по образцу лимитера `order-checkout` в `AppServiceProvider`).
- [ ] **Обновить `docs/api/openapi.yaml` в том же коммите** и русскоязычные документы в `docs/api/` —
      требование `AGENTS.md`.

### Уведомления

- [ ] `NewComplaintNotification` + слушатель `NotifyAdminsAboutNewComplaint` по образцу
      уведомления о новом заказе, со ссылкой на `ComplaintResource::getUrl(...)`.
- [ ] Регистрация слушателя в `AppServiceProvider::boot()`.

### Панель

- [ ] `ComplaintResource`: список (номер заказа, клиент, тема, статус бейджем, дата),
      фильтры по статусу и периоду, поиск; страница просмотра; редактирование только
      `admin_comment` и статуса.
- [ ] Действия смены статуса: «Взять в работу», «Решена», «Отклонена» — через `ChangeComplaintStatus`,
      с обязательным комментарием при решении и отклонении.
- [ ] Relation manager жалоб в карточке заказа + бейдж со счётчиком открытых жалоб
      в навигации (`getNavigationBadge()`).

## Затрагиваемые файлы

- `database/migrations/*_create_complaints_table.php` — новый
- `app/Enums/ComplaintStatus.php`, `app/Models/Complaint.php` — новые
- `app/Modules/Complaints/**` — новый модуль
- `app/Modules/Notifications/Notifications/NewComplaintNotification.php`,
  `app/Modules/Notifications/Listeners/NotifyAdminsAboutNewComplaint.php` — новые
- `app/Providers/AppServiceProvider.php`
- `routes/api.php`
- `app/Filament/Resources/Complaints/**` — новый
- `app/Models/CleaningOrder.php`
- `docs/api/openapi.yaml`, `docs/api/*.md`
- `database/factories/ComplaintFactory.php` — новый

## Критерии приёмки

1. Клиент подаёт жалобу по своему заказу; чужой заказ даёт 403, несуществующий — 404.
2. Жалоба на заказ в статусе `in_progress`, `awaiting_payment` или `completed` принимается;
   на заказ в `processing`, `confirmed`, `team_formed` или `cancelled` — отклоняется с 409
   и кодом `complaint_not_allowed`.
3. Жалоба сохраняется со статусом `new` и привязкой к заказу и клиенту.
4. Администраторы получают email о новой жалобе со ссылкой прямо на жалобу в панели.
5. Список жалоб в панели фильтруется по статусу и периоду, ищется по номеру заказа и клиенту.
6. Жалоба открывается из карточки заказа и из общего списка.
7. Статус меняется на `in_progress`, `resolved`, `rejected`; при решении и отклонении
   комментарий администратора обязателен, пишутся `resolved_at` и `resolved_by`.
8. В навигации виден счётчик жалоб в статусах `new` и `in_progress`.
9. `docs/api/openapi.yaml` описывает оба новых эндпоинта, включая коды ошибок.

## Тесты

- `tests/Feature/ComplaintApiTest.php` — подача по своему заказу, отказ по чужому,
  валидация, ограничение частоты, список жалоб клиента.
- `tests/Feature/ComplaintOrderStatusRuleTest.php` — датасет по всем семи статусам
  `OrderStatus`: `in_progress`, `awaiting_payment`, `completed` принимаются;
  `processing`, `confirmed`, `team_formed`, `cancelled` дают 409 `complaint_not_allowed`.
- `tests/Feature/ComplaintNotificationTest.php` — по образцу `NewOrderEmailNotificationTest`:
  `Notification::fake()`, `assertSentTo` администраторам, `assertNotSentTo` клиенту,
  проверка `toMail()` на тему и `actionUrl`.
- `tests/Feature/ComplaintWorkflowTest.php` — переходы статусов, обязательность комментария,
  запись `resolved_by` и `resolved_at`.

## Риски

- **Затрагивает мобильные приложения.** Контракт эндпоинтов нужно согласовать с командой
  мобильной разработки до реализации, иначе переделка.
- Правило допустимости статуса согласовано (см. «Цель»). Осталось допущение: число жалоб
  по одному заказу не ограничено, ограничена только частота подачи. Если заказчик захочет
  «одна жалоба на заказ» — это уникальный индекс и дополнительный код ответа.
- Уведомления идут в очередь (`ShouldQueue`); на стенде должен быть запущен воркер,
  иначе письма не уйдут. Внести в инструкцию по развёртыванию.
- Модерация содержимого жалоб и вложения (фото) в скоуп не входят — при необходимости отдельная задача.
