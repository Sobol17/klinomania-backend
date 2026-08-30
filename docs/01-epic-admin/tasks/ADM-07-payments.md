# ADM-07 — Управление оплатами

| | |
|---|---|
| **Эпик** | [EP-ADM](../00-epic.md) |
| **Пункт ТЗ** | 1.7 |
| **Оценка** | 5 SP |
| **Зависимости** | [ADM-08](ADM-08-foundation-and-handover.md) — enum-каркас |
| **Use cases** | [UC-7.1 … UC-7.5](../use-cases/UC-ADM-07-payments.md) |

## Цель

Администратор видит список оплат, статус каждой оплаты и её привязку к конкретному заказу.

**Раздел read-only — подтверждено заказчиком.** Ручное создание, редактирование и удаление
записей об оплате из панели не предусмотрены; см. согласованное решение 4 в [эпике](../00-epic.md).
Ручная отметка наличной или офлайн-оплаты, если она когда-либо понадобится, — отдельная
задача ADM-07b вне этого эпика.

## Текущее состояние

Оплаты — это `payment_attempts`, модель `App\Models\PaymentAttempt`. Filament-ресурса нет.

Колонки: `cleaning_order_id`, `provider` (32, индекс), `external_order_id` (50, unique),
`provider_payment_id` (unique, nullable), `amount` (unsigned int, **в копейках**), `currency`,
`payment_url` (text), `expires_at`, `status` (32, индекс), `provider_status` (32, nullable),
`error_code` (32, nullable), `error_message` (text, nullable), `confirmed_at`.

Провайдер один — **T-Bank**, конфигурация в `config/services.php` под ключом `tbank`.

Создание — `app/Modules/Payments/Actions/CreateTBankPayment::execute(CleaningOrder $order)`:
блокирует заказ, требует статус `awaiting_payment` (иначе 409 `invalid_order_transition`),
переиспользует непросроченную попытку в статусе `pending` с готовой ссылкой, иначе создаёт
новую с `provider = 'tbank'`, `external_order_id = 'pay_'.Str::ulid()`, `amount = total_price * 100`,
`status = 'creating'`; после успешного `HttpTBankGateway::initialize()` записывает
`provider_payment_id`, `payment_url`, `provider_status` и переводит в `pending`;
при `TBankGatewayException` — в `failed` с `error_message`.

Обновление — `app/Modules/Payments/Http/Controllers/TBankNotificationController::store()`:
сверяет подпись через `TBankToken::make()` с `hash_equals` (403), сверяет `TerminalKey` (403),
находит попытку по `external_order_id` **и** сумме (404), в транзакции ставит `confirmed`
(с `confirmed_at`) либо `failed`, и если оплата подтверждена, а заказ в `awaiting_payment` —
переводит заказ в `completed` и диспатчит `OrderStatusChanged`.

**Статусы — голые строковые литералы** (`creating`, `pending`, `confirmed`, `failed`),
enum-класса нет.

## Объём работ

- [ ] `App\Enums\PaymentStatus`: `Creating`, `Pending`, `Confirmed`, `Failed`;
      реализует `HasLabel` и `HasColor`. Заменить литералы в `CreateTBankPayment`
      и `TBankNotificationController`, добавить каст в `PaymentAttempt::casts()`.
- [ ] `PaymentAttemptResource` в режиме только чтения: `canCreate()`, `canEdit()`, `canDelete()`
      возвращают `false`.
- [ ] Список: номер заказа (`order.public_id`) со ссылкой, клиент, сумма (**делить на 100**),
      валюта, провайдер, статус бейджем, дата создания, `confirmed_at`.
- [ ] Фильтры: статус, провайдер, период создания, период подтверждения, «только ошибочные».
- [ ] Поиск по `external_order_id`, `provider_payment_id` и `public_id` заказа.
- [ ] Страница просмотра оплаты: все реквизиты провайдера, `provider_status`, `error_code`,
      `error_message`, срок действия ссылки, переход к заказу.
- [ ] Relation manager `paymentAttempts` в карточке заказа (read-only), сортировка по убыванию даты.
- [ ] Скрыть или замаскировать `payment_url`: это действующая платёжная ссылка.

## Затрагиваемые файлы

- `app/Enums/PaymentStatus.php` — новый
- `app/Models/PaymentAttempt.php` — каст статуса
- `app/Modules/Payments/Actions/CreateTBankPayment.php`
- `app/Modules/Payments/Http/Controllers/TBankNotificationController.php`
- `app/Filament/Resources/PaymentAttempts/**` — новый
- `app/Filament/Resources/CleaningOrders/RelationManagers/PaymentAttemptsRelationManager.php` — новый

## Критерии приёмки

1. Раздел оплат доступен из навигации, показывает все попытки оплаты с фильтрами и поиском.
2. Сумма отображается в рублях, а не в копейках.
3. Статус отображается бейджем с русской подписью и цветом.
4. Карточка оплаты показывает реквизиты провайдера и, при неуспехе, `error_code` и `error_message`.
5. Из оплаты открывается заказ, из заказа виден список его оплат — привязка прослеживается в обе стороны.
6. Создание, редактирование и удаление оплат из панели недоступны — кнопок нет, прямой переход
   по URL даёт 403.
7. `tests/Feature/TBankPaymentTest.php` остаётся зелёным после замены литералов на enum.

## Тесты

- `tests/Feature/TBankPaymentTest.php` — существующий, прогнать после введения enum.
- `tests/Feature/AdminPaymentReadOnlyTest.php` — страницы создания и редактирования
  недоступны; список отдаёт записи; администратор не может изменить статус попытки.

## Риски

- **Замена строковых литералов на enum трогает боевой платёжный тракт.** Менять надо
  одновременно в создании и в вебхуке; расхождение приведёт к тому, что подтверждённые платежи
  перестанут распознаваться. Обязательный прогон `TBankPaymentTest`.
- Сумма в копейках — типичный источник ошибки отображения. Зафиксировано в критерии 2.
- `payment_url` — рабочая платёжная ссылка, показывать её в открытом виде в списке не следует.
- Read-only-режим подтверждён заказчиком, трактовка ТЗ 1.7 закрыта. Ограничение должно
  опираться на `PaymentAttemptPolicy`, а не только на скрытие кнопок: иначе прямой переход
  по URL редактирования обойдёт запрет.
