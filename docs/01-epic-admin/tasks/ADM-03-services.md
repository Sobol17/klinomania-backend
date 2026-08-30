# ADM-03 — Управление услугами

| | |
|---|---|
| **Эпик** | [EP-ADM](../00-epic.md) |
| **Пункт ТЗ** | 1.3 |
| **Оценка** | 8 SP |
| **Зависимости** | — |
| **Use cases** | [UC-3.1 … UC-3.7](../use-cases/UC-ADM-03-services.md) |

## Цель

Администратор ведёт каталог услуг: список, создание, редактирование, удаление, управление
стоимостью, описаниями, изображениями и опциями.

**Подтверждено заказчиком:** загрузка изображения услуги выполняется **из панели** —
администратор выбирает файл в карточке услуги, а не вставляет внешнюю ссылку.
Это отдельно оговорённое требование, а не опция; см. UC-3.5.

## Текущее состояние

`app/Filament/Resources/CleaningServices/CleaningServiceResource.php` — самый развитый ресурс
в проекте. Уже есть редактирование шаблонов чек-листа: четыре вкладки по зонам
(`App\Enums\ChecklistZone`) с репитерами
`Repeater::make('checklistItems_'.$zone->value)->relationship('checklistItems', fn ($q) => $q->where('zone', $zone->value))->orderColumn('sort_order')->reorderableWithDragAndDrop()`.

`app/Filament/Resources/ExtraServices/ExtraServiceResource.php` — отдельный ресурс над
`ServiceOption` со скоупом `group = 'extra'`. Опции групп `room` и `cleaning` в панели
недоступны вообще, хотя именно они формируют цену заказа.

Модель `CleaningService` покрывает всю коммерческую часть: `base_price`, `cleaner_base_earnings`,
`price_per_sqm`, `min_area`, `max_area`, `area_step`, `min_price`, `currency`, `required_cleaners`,
`sort_order`, `is_active`, плюс тексты (`subtitle`, `short_description`, `long_description`,
`cleaners_label`, `duration_label`) и изображения (`image_url` строкой, `gallery` — json, каст `array`).

**Изображения сейчас — внешние URL.** Сидер `database/seeders/ServiceCatalogSeeder.php` пишет
абсолютные адреса вида `https://cdn.klinomania.ru/services/{slug}/hero.jpg`,
`ClientServiceController` отдаёт значение колонки без обработки. В `app/` нет ни одного
использования `FileUpload::` или `Storage::`, симлинк `public/storage` не создан.

**Удаление услуги ограничено на уровне БД:** `cleaning_orders.cleaning_service_id` объявлен
как `->constrained()->restrictOnDelete()`, поэтому услуга со связанными заказами не удалится.

## Объём работ

- [ ] Сгруппировать форму по секциям: основное, тексты, цены и параметры площади, изображения,
      чек-листы. Сейчас поля идут плоским списком.
- [ ] Загрузка изображений: `FileUpload` на `image_url` (одиночный) и `gallery` (множественный,
      с переупорядочиванием) на диск `public`, в БД пишется полный URL — см. допущение 1 эпика.
      В `app/` сейчас **нет ни одного** использования `FileUpload::` или `Storage::` — это
      первая работа с файлами в проекте, диск и валидацию (тип, размер) настраиваем с нуля.
- [ ] `php artisan storage:link` внести в инструкцию по развёртыванию и в `docs/deploy.md`.
- [ ] Relation manager `ServiceOption` со вкладками по группам `room` / `cleaning` / `extra`,
      с полями `code`, `title`, `subtitle`, `price_modifier`, `cleaner_revenue_percent`,
      `checklist_zone`, `is_addon`, `is_default`, `sort_order`, `is_active`.
- [ ] Управление зависимостями опций (`service_option_dependencies`, связь `ServiceOption::allowedWith()`).
- [ ] Действие удаления с проверкой связанных заказов: при их наличии — отказ с внятной
      нотификацией и предложением деактивировать услугу.
- [ ] Переключатель `is_active` прямо в таблице, сортировка по `sort_order` перетаскиванием.
- [ ] Фильтры по активности; колонки: превью изображения, название, базовая цена, число опций, статус.

## Затрагиваемые файлы

- `app/Filament/Resources/CleaningServices/CleaningServiceResource.php`
- `app/Filament/Resources/CleaningServices/Pages/ListCleaningServices.php`
- `app/Filament/Resources/CleaningServices/RelationManagers/OptionsRelationManager.php` — новый
- `app/Filament/Resources/ExtraServices/ExtraServiceResource.php` — решить судьбу: оставить
  как быстрый доступ либо убрать в пользу relation manager
- `config/filesystems.php` — при необходимости
- `docs/deploy.md`, `docs/docker-deploy.md` — шаг `storage:link`

## Критерии приёмки

1. Список услуг показывает превью, название, цену, число опций и статус; есть фильтр по активности
   и сортировка перетаскиванием.
2. Услуга создаётся с заполнением цен, текстов и изображений.
3. Редактирование стоимости, описаний и изображений сохраняется и отражается в публичном API
   (`GET /api/v1/client/services/{id}`).
4. Удаление услуги без заказов проходит; при наличии заказов — отказ с объяснением, данные целы.
5. Изображения загружаются с диска администратора, доступны по публичному URL, галерея
   переупорядочивается.
6. Опции всех трёх групп создаются и правятся из карточки услуги.
7. Деактивированная услуга исчезает из публичного каталога, но остаётся в существующих заказах.

## Тесты

- `tests/Feature/AdminServiceManagementTest.php` — удаление услуги со связанным заказом
  не приводит к 500 и не удаляет запись; деактивированная услуга не попадает в
  `GET /api/v1/client/services`.
- `tests/Feature/ServiceImageUploadTest.php` — `Storage::fake('public')`, загруженный файл
  сохраняется, в `image_url` записан полный URL.

## Риски

- Переход на загрузку файлов меняет содержимое `image_url`. Решение по допущению 1: хранить
  полный URL, контракт API не менять. Отклонение от этого — ломающее изменение для мобильных приложений.
- Существующие записи содержат внешние CDN-адреса; форма загрузки должна корректно отображать
  их как есть и не требовать перезагрузки файла при сохранении.
- Дублирование: `ExtraServiceResource` и новый relation manager редактируют одну таблицу.
  Оставить один источник правды, иначе рассинхрон в UX.
