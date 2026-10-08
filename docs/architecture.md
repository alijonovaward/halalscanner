# Архитектура Halal Scanner

Дата: 2026-07-14
Фаза: 1, SDD
Статус: черновик для согласования

## 1. Назначение

Этот документ описывает архитектурные границы Halal Scanner на уровне продукта и домена. Он не выбирает конкретный Android-фреймворк. Backend/admin stack выбран отдельным решением ADR 0003: Laravel 13, Filament 5 и Laravel Boost как dev-only инструмент. Задача этого документа - связать `docs/constitution.md`, `docs/spec.md` и `features/*.feature` с ADR, задачами и кодом.

Главный архитектурный принцип: halal-вердикт должен быть проверяемым, осторожным, мазхабно-контекстным и объяснимым. Система не должна повышать результат до `halal` без достаточного основания.

## 2. Контекст системы

MVP состоит из мобильного клиента, локального офлайн-набора данных и обновляемого источника данных. Backend в MVP может быть минимальным, но доменная модель должна сразу поддерживать редакторскую проверку, религиозную экспертизу и обновление источников.

Основные внешние источники данных:

- локальные сертификационные органы;
- религиозно утвержденные правила;
- ручная редакторская база товаров и ингредиентов;
- данные производителей и упаковки;
- пользовательские заявки;
- открытые источники;
- boycott-источники, если модуль включен.

Пользовательские заявки и claims производителя не являются authoritative-источником без проверки.

## 3. Модули

### 3.0 Backend and admin stack

Backend MVP строится на Laravel 13.

Admin panel строится на Filament 5. Filament используется как internal admin/review interface, а не как место реализации halal rule engine.

Laravel Boost используется только после `go` как development-only Laravel-aware tooling. Boost не является production runtime dependency.

Backend должен иметь отдельные слои:

- domain layer for halal verdict logic;
- persistence layer for database-backed models;
- API layer for mobile clients;
- Filament admin layer for editor/scholar workflows;
- queue/scheduler layer for imports, reviews, snapshot generation, and source freshness checks.

### 3.1 Mobile app

Отвечает за:

- сканирование штрихкода;
- фотографирование и OCR состава;
- показ результата;
- выбор мазхаба;
- работу без аккаунта;
- offline-first поведение;
- отправку исправления или заявки.

Клиент не должен иметь скрытой логики, которая противоречит rule engine. Если клиент показывает fallback или кешированный результат, он должен сохранять источник, дату и объяснение.

### 3.2 Barcode lookup

Отвечает за поиск продукта по штрихкоду в локальных и обновленных данных.

Результаты:

- найден продукт с проверенными данными;
- найден продукт, но данных недостаточно;
- продукт неизвестен.

Если продукт неизвестен, модуль возвращает `unknown_product` и предлагает fallback на состав.

### 3.3 Ingredient parser

Отвечает за превращение состава в нормализованный список ингредиентов и добавок.

Минимальные обязанности:

- хранить исходный текст состава;
- хранить confidence OCR;
- выделять ингредиенты и E-коды;
- сохранять язык исходного текста;
- отмечать низкое качество распознавания.

Если качество распознавания ниже порога публичного результата, дальнейший rule engine не должен выдавать `halal`.

### 3.4 Rule engine

Центральный доменный модуль halal-ядра.

Вход:

- продукт;
- список ингредиентов;
- сертификаты и claims;
- выбранный мазхаб;
- локальный регион;
- доступные источники и даты проверки.

Выход:

- публичный verdict: `halal`, `haram`, `mushbooh`, `unknown`;
- reason codes;
- explanation;
- confidence;
- sources;
- last verification date;
- internal fiqh classifications;
- flags for scholar/editor review.

Rule engine должен быть детерминированным: один и тот же входной snapshot данных дает один и тот же результат.

### 3.5 Evidence and source registry

Отвечает за хранение источников, уровня доверия и истории изменений.

Каждый факт, влияющий на вердикт, должен ссылаться на источник. Если источник отсутствует, факт не может быть основанием для `halal`.

### 3.6 Certificate registry

Отвечает за:

- органы сертификации;
- сертификаты;
- срок действия;
- область действия сертификата;
- связь сертификата с продуктом, брендом, заводом или поставщиком;
- статус доверия к органу.

Halal-claim на упаковке без проверенного сертификата не должен давать `halal`.

### 3.7 Review queue

Отвечает за:

- пользовательские заявки;
- оспаривание вердикта;
- спорные ингредиенты;
- устаревшие источники;
- сомнительные сертификаты;
- правила, требующие религиозной проверки.

Review queue должна различать editor review и scholar review. Редактор может проверить источник или упаковку, но религиозно значимое правило требует scholar review.

Filament является основным UI для этой очереди: tables, filters, forms, infolists, actions, notifications, and widgets.

### 3.8 Boycott module

Отключаемый модуль. Он не участвует в вычислении halal-вердикта.

Если включен, он возвращает отдельный результат:

- campaign;
- tier;
- source;
- date;
- explanation;
- dispute action.

Отключение boycott-модуля не должно менять halal-ядро.

## 4. Поток проверки

### 4.1 Проверка по штрихкоду

1. Пользователь сканирует штрихкод.
2. Barcode lookup ищет продукт в локальном snapshot.
3. Если продукт найден, rule engine оценивает verified facts.
4. Если продукта нет, возвращается `unknown` с reason code `unknown_product`.
5. UI предлагает fallback на проверку состава.
6. Если доступна сеть, приложение может проверить обновления, но базовый результат не должен зависеть от сети.

### 4.2 Проверка по составу

1. Пользователь фотографирует состав или вводит его вручную.
2. Ingredient parser извлекает текст и confidence.
3. Если confidence ниже порога, возвращается `unknown` с reason code `low_ocr_quality`.
4. Нормализованные ингредиенты передаются в rule engine.
5. Rule engine применяет правила по выбранному мазхабу.
6. Итоговый результат содержит verdict, reason codes, explanation, sources и дату.

### 4.3 Проверка сертификата

1. Система распознает halal-claim или сертификат.
2. Certificate registry проверяет орган, срок действия, область действия и связь с продуктом.
3. Если сертификат не найден, возвращается `mushbooh` или `unknown` с `certificate_missing`.
4. Если орган недоверенный, возвращается `mushbooh` с `certificate_untrusted`.
5. Если сертификат валиден и нет конфликтов, он может быть доказательством для `halal`.

## 5. Модель вердикта

Публичный verdict:

- `halal`
- `haram`
- `mushbooh`
- `unknown`

Внутренние поля:

- `fiqh_classification`
- `madhhab`
- `reason_codes`
- `confidence`
- `evidence_strength`
- `sources`
- `last_verified_at`
- `requires_scholar_review`
- `requires_editor_review`
- `safety_notes`
- `boycott_result`

`makruh_tanzihi` и `makruh_tahrimi` не являются публичными verdict-статусами MVP. Они хранятся как `fiqh_classification` или reason code и влияют на explanation.

## 6. Reason codes

Минимальный набор MVP:

- `unknown_product`
- `low_ocr_quality`
- `unknown_origin`
- `animal_origin_unclear`
- `process_unclear`
- `certificate_missing`
- `certificate_untrusted`
- `cross_contamination_risk`
- `madhhab_disagreement`
- `makruh_tanzihi`
- `makruh_tahrimi`
- `trace_amount_unclear`
- `source_conflict`

Reason code должен быть:

- машинно-читаемым;
- переводимым на русский и узбекский;
- связанным с BDD-сценарием;
- связанным с будущим тестом rule engine;
- понятным пользователю через explanation.

## 7. Evidence model

Основные сущности:

### Product

- barcode;
- name;
- brand;
- manufacturer;
- country;
- ingredients text;
- normalized ingredients;
- certificates;
- sources;
- last verified date.

### Ingredient

- canonical name;
- aliases;
- E-code;
- origin: `plant`, `animal`, `synthetic`, `microbial`, `mineral`, `unknown`;
- animal species, if known;
- process flags;
- additive category;
- source references.

### Rule

- scope: product, ingredient, additive, certificate, process, slaughter, region;
- madhhab;
- public verdict effect;
- internal fiqh classification;
- reason code;
- required evidence;
- explanation template;
- review status.

### Evidence

- source;
- source type;
- trust tier;
- observed fact;
- date checked;
- expiry or review date;
- reviewer;
- change history.

### Certificate

- issuing body;
- certificate id;
- product or facility scope;
- valid from;
- valid until;
- recognized by;
- source document;
- verification status.

### User report

- report type;
- user-provided evidence;
- product reference;
- submitted at;
- review status;
- reviewer decision.

## 8. Rule evaluation order

Recommended order for MVP:

1. Reject unusable input: unreadable OCR, missing product, missing ingredients.
2. Apply explicit high-confidence haram evidence.
3. Apply source conflict checks.
4. Apply certificate checks.
5. Apply ingredient origin checks.
6. Apply process-dependent rules such as `istihalah`.
7. Apply madhhab-specific rules.
8. Apply makruh classifications.
9. Attach safety notes separately from religious verdict.
10. Compose final verdict using conservative aggregation.

Conservative aggregation:

- confirmed `haram` blocks `halal`;
- unresolved source conflict returns `mushbooh`;
- missing required evidence returns `mushbooh` or `unknown`;
- unknown product returns `unknown`;
- `halal` requires sufficient positive evidence for the selected madhhab;
- `makruh_tahrimi` should not become confident `haram` without stronger evidence, but should not be silently downgraded to plain `halal`;
- `makruh_tanzihi` can be shown as caution only when other sufficient halal evidence exists.

## 9. Offline-first data

The mobile app should use a versioned local data snapshot.

Snapshot contents:

- core ingredient rules;
- selected local products;
- local aliases and languages;
- trusted source metadata;
- selected certification bodies;
- explanation translations;
- golden dataset cases.

Snapshot constraints:

- must fit within the MVP offline data limit of 100 MB;
- must include a version id and generated date;
- must preserve source references needed for result explanations;
- must be replaceable through network updates.

## 10. Data freshness

Different data types age differently:

- religious principles and approved rules age slowly but still need review history;
- certificates expire on their own validity dates;
- product ingredients and packaging claims can change frequently;
- boycott records are separate and may require faster refresh;
- user reports are pending until reviewed.

The UI should show enough freshness information for the user to understand whether the result is current or old.

## 11. Privacy

MVP halal scanning must work without account.

Default behavior:

- no geolocation required;
- no contacts access;
- camera only for scanning/OCR;
- scan history optional or privacy-controlled;
- user reports should collect minimal required data.

## 12. Internationalization

MVP must support Russian and Uzbek for:

- public verdict labels;
- reason explanations;
- source labels;
- correction flow;
- warning and fallback text.

Internal ids remain stable English-like codes such as `certificate_missing` and `makruh_tahrimi`.

## 13. Traceability matrix

Initial mapping:

- `features/verdict-statuses.feature`: FR-01, FR-02, FR-04, FR-05, FR-12, FR-25, FR-27.
- `features/makruh-and-madhhab.feature`: FR-06, FR-08, FR-26, FR-27, NFR-12.
- `features/certificates-and-evidence.feature`: FR-10, FR-11, FR-28, FR-29.
- `features/ingredients-and-processes.feature`: FR-05, FR-25, FR-27, FR-30.

Future tasks must preserve this chain: constitution -> spec -> features -> architecture/ADR -> tasks -> tests -> code.

## 14. Open architecture decisions

These require ADR:

1. Certificate trust and halal-claim handling.
2. `Istihalah` process evidence.
3. Offline snapshot strategy.
4. Boycott module isolation.
5. Mobile API authentication, if/when account-backed features are introduced.
