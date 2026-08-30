# ADM-04 — Управление заказами

| | |
|---|---|
| **Эпик** | [EP-ADM](../00-epic.md) |
| **Пункт ТЗ** | 1.4 |
| **Оценка** | 13 SP |
| **Зависимости** | [ADM-08](ADM-08-foundation-and-handover.md) — доменные исключения вместо `abort()` |
| **Use cases** | [UC-4.1 … UC-4.9](../use-cases/UC-ADM-04-orders.md) |

## Цель

Администратор видит список заказов с фильтрами, открывает детальную карточку, меняет статус
в рамках допустимых переходов, назначает и снимает клинеров, видит информацию об оплате.

## Текущее состояние

`app/Filament/Resources/CleaningOrders/CleaningOrderResource.php` — форма создания заказа
(клиент, услуга, опции по группам, адрес, дата, комментарий), таблица с колонками
`public_id`, статус, услуга, телефон клиента, телефоны клинеров, сумма, дата, фильтр по статусу,
`recordActions([EditAction::make()])`.

`CreateCleaningOrder::handleRecordCreation()` делегирует создание в
`App\Modules\Orders\Actions\CreateOrder::execute()` с сгенерированным ключом идемпотентности —
**это эталон, которому должны следовать все новые действия**.
`EditCleaningOrder::getHeaderActions()` содержит `Action::make('confirm')` («Подтвердить заявку»),
вызывающий `OrderWorkflow::confirm()` и видимый только при статусе `Processing`;
`afterSave()` синхронизирует `addressSnapshot()`.

Маршрутизация: `CleaningOrder::getRouteKeyName()` возвращает `'public_id'` (ULID).

Связь с клинерами — **пивот, а не колонка**: `cleaning_order_cleaners`
(`cleaning_order_id`, `cleaner_id`, `accepted_at`, `started_at`, `completed_at`, уникальная пара).
Колонка `cleaning_orders.cleaner_id` удалена миграцией
`2026_07_15_000001_add_order_team_workflow.php`. Размер команды — `service->required_cleaners`.

Переходы статусов сосредоточены в `app/Modules/Orders/Actions/OrderWorkflow.php`:

| Метод | Из | В | Условие |
|---|---|---|---|
| `confirm()` | `processing` | `confirmed` | вызывается только из админки, API-маршрута нет |
| `accept($order, $cleaner)` | `confirmed` | `team_formed`, либо сразу `in_progress` при `required_cleaners === 1` | клинер ещё не в команде, команда не укомплектована |
| `start()` | `team_formed` / `in_progress` | `in_progress` | вызывающий назначен на заказ |
| `complete()` | `in_progress` | `awaiting_payment` | клинер начал работу; **все** пункты чек-листа и все доп. работы закрыты |
| `cancel($order, $client)` | `processing` / `confirmed` | `cancelled` | вызывающий — владелец заказа |
| вебхук | `awaiting_payment` | `completed` | `TBankNotificationController::store()`, вне `OrderWorkflow` |

## Ключевые проблемы, которые надо решить в этой задаче

**1. `OrderWorkflow` завязан на HTTP.** Недопустимые переходы завершаются
`abort(response()->json(['message' => ..., 'code' => 'invalid_order_transition'], 409))`.
Вызванный из Filament, такой код выдаст сырой JSON вместо нотификации. Требуется рефакторинг
на доменные исключения (`InvalidOrderTransition`, `ChecklistIncomplete`), которые контроллеры
API мапят в прежние 409-ответы, а панель — в `Notification::danger()`. Существующие ответы API
и коды ошибок при этом **не меняются** — `tests/Feature/OrderWorkflowTest.php` должен остаться зелёным.

**2. Нет админского назначения клинера — подтверждено заказчиком как обязательное.**
`accept()` — операция самого клинера: он сам берёт свободный заказ и получает `accepted_at`.
Администратор назначает клинера принудительно, это другая операция, и без неё ТЗ 1.4 не закрывается.
Нужен `App\Modules\Orders\Actions\AssignCleaner` с теми же гарантиями: `lockForUpdate()`,
проверка лимита `required_cleaners`, уникальность пары, перевод в `team_formed`/`in_progress`
при укомплектовании команды, событие `OrderStatusChanged`.

Отличие от `accept()`, которое надо зафиксировать в коде: при админском назначении
`accepted_at` проставляется по факту назначения, а не по факту согласия клинера.
Если по бизнесу требуется различать «взял сам» и «назначен администратором» —
понадобится дополнительное поле пивота; в текущий скоуп оно не входит.

**3. Отмена администратором.** `cancel()` требует, чтобы вызывающий был клиентом-владельцем.
Нужен отдельный путь для администратора без этой проверки.

## Объём работ

- [ ] Рефакторинг `OrderWorkflow` на доменные исключения (совместно с [ADM-08](ADM-08-foundation-and-handover.md)).
- [ ] `App\Modules\Orders\Actions\AssignCleaner` — назначение и снятие клинера.
      Снятие разрешено **только пока `started_at` в пивоте пуст** (согласовано заказчиком):
      отметки времени — основа учёта заработка и не удаляются задним числом.
- [ ] `OrderWorkflow::cancelByAdmin(CleaningOrder $order)` либо параметризация `cancel()`.
- [ ] Страница `ViewCleaningOrder` (`Infolist`): реквизиты, клиент, адрес из `addressSnapshot()`,
      состав и суммы `lineItems`, команда клинеров с временными метками пивота, чек-лист, оплаты, жалобы.
- [ ] Relation managers: `cleaners` (с `accepted_at`, `started_at`, `completed_at`), `lineItems`,
      `paymentAttempts` (read-only, см. [ADM-07](ADM-07-payments.md)), чек-лист (см. [ADM-05](ADM-05-checklists.md)).
- [ ] Header-действия смены статуса: подтвердить (есть), отменить, вернуть в работу — каждое
      видимо только при допустимом исходном статусе.
- [ ] Действия «Назначить клинера» и «Снять клинера» с выбором из активных клинеров.
- [ ] Фильтры списка: статус (есть), услуга, период `scheduled_at`, клинер, «без назначенных клинеров».
- [ ] Бейджи статусов через `OrderStatus: HasLabel, HasColor` вместо приватного `statusLabel()`.
- [ ] Проверить, что админская смена статуса порождает `OrderStatusChanged` → push клиенту
      (`SendOrderStatusPush` → `OrderStatusPushService`).

## Затрагиваемые файлы

- `app/Modules/Orders/Actions/OrderWorkflow.php`
- `app/Modules/Orders/Actions/AssignCleaner.php` — новый
- `app/Modules/Orders/Exceptions/` — новый каталог
- `app/Modules/Orders/Http/Controllers/ClientOrderController.php`, `app/Modules/CleanerWork/Http/Controllers/CleanerOrderController.php` — обработка исключений
- `app/Filament/Resources/CleaningOrders/CleaningOrderResource.php`
- `app/Filament/Resources/CleaningOrders/Pages/ViewCleaningOrder.php` — новый
- `app/Filament/Resources/CleaningOrders/RelationManagers/` — новый каталог
- `app/Enums/OrderStatus.php`

## Критерии приёмки

1. Список заказов фильтруется по статусу, услуге, периоду и клинеру; статусы отображаются бейджами.
2. Карточка заказа показывает клиента, адрес, состав и суммы, команду клинеров с метками
   принятия, начала и завершения, чек-лист, оплаты и жалобы.
3. Подтверждение заказа переводит `processing → confirmed`; кнопка не видна в других статусах.
4. Отмена администратором доступна из `processing` и `confirmed` и переводит в `cancelled`.
5. Недопустимый переход показывает понятную нотификацию, **не** сырой JSON, статус не меняется.
6. Назначение клинера добавляет запись в `cleaning_order_cleaners`; при укомплектовании команды
   заказ переходит в `team_formed` (или `in_progress` при `required_cleaners = 1`).
7. Назначение сверх `required_cleaners` и повторное назначение того же клинера отклоняются.
8. Снятие клинера удаляет запись пивота и корректно откатывает статус, если команда перестала
   быть укомплектованной.
9. Снятие клинера, у которого проставлен `started_at`, недоступно в интерфейсе и отклоняется
   при прямом вызове.
10. В карточке заказа видны все попытки оплаты с их статусами.
11. Смена статуса администратором доставляет push клиенту.
12. `tests/Feature/OrderWorkflowTest.php` остаётся зелёным без изменений ожиданий по кодам ответов.

## Тесты

- `tests/Feature/AdminOrderManagementTest.php` — назначение клинера через `AssignCleaner`
  (успех, превышение лимита, дубль); снятие клинера с пустым `started_at` проходит и
  откатывает статус в `confirmed`; снятие клинера с заполненным `started_at` отклоняется,
  запись пивота цела; отмена администратором; недопустимый переход бросает доменное
  исключение, а не `HttpException`.
- `tests/Feature/OrderWorkflowTest.php` — прогнать без изменений: контракт API не менялся.
- Проверка `OrderStatusChanged` через `Event::fake()`.

## Риски

- Рефакторинг `OrderWorkflow` затрагивает и клиентский, и клинерский API — регресс возможен
  в обоих. Страховка: существующий `OrderWorkflowTest` не переписывается.
- Снятие клинера после начала работы **запрещено** (согласовано, см. UC-4.7). Проверять надо
  именно `started_at` в пивоте, а не статус заказа: в `team_formed` часть команды может уже
  начать работу, а часть — нет. Проверка только по статусу заказа даст неверное поведение.
- Ручная смена статуса в обход `OrderWorkflow` (например, из `EditAction`) обойдёт все проверки.
  Поле `status` в форме редактирования должно быть недоступно для прямой правки.
