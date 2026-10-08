# Tasks: Halal Scanner MVP

Дата: 2026-07-14
Фаза: 1, SDD
Статус: черновик для согласования

## 1. Назначение

Этот документ переводит `docs/constitution.md`, `docs/spec.md`, `features/*.feature`, `docs/architecture.md` и `docs/adr/*.md` в план реализации.

До явного `go` от владельца продукта код не пишется. После `go` реализация должна идти через TDD: сначала падающий тест, затем минимальная реализация, затем рефакторинг при зеленых тестах.

## 2. Phase gate

Перед переходом к коду должны быть согласованы:

- `docs/constitution.md`
- `docs/spec.md`
- `features/*.feature`
- `docs/architecture.md`
- `docs/adr/*.md`
- этот `docs/tasks.md`

Статус после завершения этого документа: остановиться и ждать явного `go`.

## 3. Реализационные фазы

### Phase 0. Laravel backend foundation

Цель: создать backend/admin каркас после явного `go`, не смешивая framework setup с доменной логикой.

Задачи:

- 0.1. Создать Laravel 13 backend project.
- 0.2. Подключить Filament 5 admin panel.
- 0.3. Подключить Laravel Boost как dev dependency.
- 0.4. Настроить базовую структуру слоев: Domain, Api, Admin/Filament, Jobs, Database.
- 0.5. Настроить testing stack для TDD.
- 0.6. Настроить code style/linting для PHP.
- 0.7. Зафиксировать, что Filament Resources не содержат rule engine logic.

Definition of Done:

- Laravel project boots locally.
- Filament admin panel доступен для internal users.
- Laravel Boost установлен только как dev dependency.
- Первый пустой domain test запускается.
- Rule engine namespace существует отдельно от Filament Resources.

Связанные документы:

- `docs/adr/0003-backend-admin-stack.md`
- `docs/architecture.md` section 3.0

### Phase A. Domain foundation

Цель: создать базовую доменную модель без UI и без внешних интеграций.

Задачи:

- A1. Создать типы публичных вердиктов: `halal`, `haram`, `mushbooh`, `unknown`.
- A2. Создать внутренние фиқх-классификации, включая `makruh_tanzihi` и `makruh_tahrimi`.
- A3. Создать список MVP reason codes из `docs/spec.md` FR-27.
- A4. Создать модель `VerdictResult` с verdict, reason codes, explanation, madhhab, confidence, sources, last verified date, review flags.
- A5. Создать модели `Product`, `Ingredient`, `Rule`, `Evidence`, `Certificate`, `CertificationBody`, `UserReport`.
  - A5.1. Создать Eloquent models и migrations для MVP-сущностей.
  - A5.2. Создать relationships между продуктами, ингредиентами, evidence, сертификатами и certification bodies.
  - A5.3. Создать factories для тестовых данных MVP.
  - A5.4. Создать Filament Resources для `Product`, `Ingredient`, `Rule`, `Evidence`, `Certificate`, `CertificationBody`, `UserReport`.
  - A5.5. Зафиксировать тестом/структурой, что Filament Resources вызывают domain services и не содержат rule engine logic.
- A6. Добавить stable ids для внутренних enum/value objects.
- A7. Добавить валидацию: результат `halal` не может быть создан без sufficient positive evidence.

Definition of Done:

- Доменные типы покрывают поля из `docs/architecture.md`.
- Публичных verdict-статусов ровно четыре.
- `makruh` не является публичным verdict.
- Есть тесты на невозможность `halal` без sufficient evidence.

Связанные документы:

- `docs/spec.md` FR-01, FR-25, FR-26, FR-27, FR-28
- `docs/adr/0001-public-verdicts-and-internal-fiqh-classification.md`
- `docs/adr/0002-evidence-based-halal-verdicts.md`

### Phase B. Rule engine MVP

Цель: реализовать детерминированный rule engine для проверяемых локальных данных.

Задачи:

- B1. Реализовать evaluation input: product, ingredients, certificates, claims, madhhab, region, source snapshot.
- B2. Реализовать order из `docs/architecture.md` section 8.
- B3. Реализовать conservative aggregation.
- B4. Реализовать `unknown_product`.
- B5. Реализовать `low_ocr_quality`.
- B6. Реализовать known haram ingredient blocking.
- B7. Реализовать `source_conflict`.
- B8. Реализовать `makruh_tanzihi` как caution only with sufficient halal evidence.
- B9. Реализовать `makruh_tahrimi` как cautious `mushbooh` без отдельного confident `haram`.
- B10. Реализовать madhhab-specific rule selection.

Definition of Done:

- Все сценарии из `features/verdict-statuses.feature` проходят как automated tests.
- Все сценарии из `features/makruh-and-madhhab.feature` проходят как automated tests.
- Один и тот же input snapshot дает один и тот же `VerdictResult`.

Связанные документы:

- `features/verdict-statuses.feature`
- `features/makruh-and-madhhab.feature`
- `docs/architecture.md` section 8

### Phase C. Evidence and source registry

Цель: сделать проверяемость источников частью домена, а не UI-текстом.

Задачи:

- C1. Создать модель source type и trust tier.
- C2. Создать модель evidence strength.
- C3. Связать rule facts с evidence.
- C4. Запретить authoritative use пользовательских заявок без review.
- C5. Добавить checked date, expiry/review date и change history.
- C6. Добавить review flags: `requires_editor_review`, `requires_scholar_review`.
- C7. Добавить source aging policy placeholders для сертификатов, ингредиентов и правил.

Definition of Done:

- Любой факт, влияющий на verdict, имеет source metadata.
- User report не может изменить verdict на `halal` без review.
- Религиозно значимые rules могут быть помечены `requires_scholar_review`.

Связанные документы:

- `docs/spec.md` FR-10, FR-11, FR-28
- `features/certificates-and-evidence.feature`
- `docs/architecture.md` sections 5, 7, 10

### Phase D. Certificate registry

Цель: отделить halal-claim от проверенного сертификата.

Задачи:

- D1. Создать модель `CertificationBody`.
- D2. Создать модель `Certificate`.
- D3. Реализовать проверку scope сертификата: product, manufacturer, facility.
- D4. Реализовать срок действия сертификата.
- D5. Реализовать trust status certification body.
- D6. Реализовать `certificate_missing`.
- D7. Реализовать `certificate_untrusted`.
- D8. Реализовать valid trusted certificate как sufficient evidence при отсутствии конфликтов.

Definition of Done:

- Halal-надпись без проверяемого сертификата не дает `halal`.
- Недоверенный орган возвращает cautious result.
- Valid trusted certificate может поддержать `halal`, если нет конфликтов.

Связанные документы:

- `docs/spec.md` FR-29
- `features/certificates-and-evidence.feature`
- `docs/architecture.md` sections 3.6, 4.3, 7

### Phase E. Ingredient and process rules

Цель: покрыть первые спорные ингредиенты и process-dependent кейсы.

Задачи:

- E1. Создать canonical ingredient dictionary.
- E2. Поддержать aliases и E-codes.
- E3. Поддержать origin: `plant`, `animal`, `synthetic`, `microbial`, `mineral`, `unknown`.
- E4. Реализовать gelatin unknown origin -> `mushbooh`.
- E5. Реализовать fish gelatin with trusted evidence -> `halal`.
- E6. Реализовать `istihalah` as process-dependent rule.
- E7. Реализовать `process_unclear`.
- E8. Реализовать alcohol as flavor ingredient -> `haram`.
- E9. Реализовать trace alcohol without approved rule -> `mushbooh`.
- E10. Отметить спорные ingredient rules как `requires_scholar_review`.

Definition of Done:

- Все сценарии из `features/ingredients-and-processes.feature` проходят как automated tests.
- Ни один ingredient name alone не дает `halal`, если rule требует origin/process evidence.
- `istihalah` требует process evidence.

Связанные документы:

- `docs/spec.md` FR-05, FR-25, FR-27, FR-30
- `features/ingredients-and-processes.feature`
- `docs/book-notes-parameters-halal-haram.md`

### Phase F. Golden dataset v1

Цель: создать минимальный набор проверочных кейсов для rule engine и regression tests.

Кандидаты:

- unknown barcode;
- unreadable ingredient OCR;
- pork-derived ingredient;
- conflicting sources;
- `makruh_tanzihi` with sufficient halal evidence;
- `makruh_tahrimi`;
- Hanafi `mushbooh` vs Shafi'i `halal`;
- halal label without certificate;
- untrusted certificate body;
- trusted certificate without conflicts;
- user report without review;
- gelatin unknown origin;
- fish gelatin with trusted evidence;
- `istihalah` claim without process evidence;
- alcohol as flavor ingredient;
- trace alcohol without approved rule.

Definition of Done:

- Каждый golden case связан с BDD-сценарием.
- Каждый golden case имеет ожидаемый verdict, reason codes и explanation expectation.
- Golden dataset можно использовать в unit/integration tests.

Связанные документы:

- `features/*.feature`
- `docs/architecture.md` section 9

### Phase G. Offline snapshot

Цель: подготовить offline-first структуру данных.

Задачи:

- G1. Определить snapshot schema.
- G2. Добавить snapshot version id.
- G3. Добавить generated date.
- G4. Включить core ingredient rules.
- G5. Включить selected local products.
- G6. Включить selected certification bodies.
- G7. Включить source references needed for explanations.
- G8. Проверить размер snapshot относительно лимита 100 MB.
- G9. Подготовить стратегию замены snapshot при обновлении.

Definition of Done:

- Rule engine может работать на snapshot без сети.
- Snapshot содержит source references для explainability.
- Есть проверка размера offline data.

Связанные документы:

- `docs/spec.md` FR-13, FR-14, NFR-03, NFR-08
- `docs/architecture.md` section 9

### Phase H. UI result contract

Цель: определить контракт между rule engine и экраном результата до разработки UI.

Задачи:

- H1. Описать result DTO для UI.
- H2. Включить verdict label.
- H3. Включить explanation.
- H4. Включить reason code translations.
- H5. Включить sources and dates.
- H6. Включить active madhhab.
- H7. Включить caution notes для `makruh` и safety.
- H8. Включить dispute action.
- H9. Включить fallback action для `unknown_product` и `low_ocr_quality`.
- H10. Разделить halal result и boycott result.

Definition of Done:

- UI не должен выводить `haram` для спорных/makruh cases без confident haram evidence.
- UI может объяснить каждый `mushbooh` и `unknown`.
- Halal и boycott отображаются как разные блоки.

Связанные документы:

- `docs/spec.md` FR-10, FR-11, FR-15, FR-20, FR-24, FR-31
- `docs/architecture.md` sections 3.1, 5, 12

### Phase I. Review queue

Цель: подготовить поток исправлений и экспертной проверки.

Задачи:

- I1. Создать типы user reports: wrong product, wrong barcode, OCR issue, verdict dispute, stale source, certificate issue, missing local product.
- I2. Создать review statuses.
- I3. Разделить editor review и scholar review.
- I4. Реализовать pending user report без автоматического изменения authoritative facts.
- I5. Реализовать dispute entry из экрана результата.
- I6. Сохранять source/reason для пересмотра.
- I7. Подготовить audit trail изменений.

Definition of Done:

- Любой verdict можно оспорить в два тапа.
- User report не становится authoritative без review.
- Scholar review required для спорных религиозных правил.

Связанные документы:

- `docs/spec.md` FR-15, FR-16, NFR-10
- `docs/architecture.md` section 3.7

### Phase J. Boycott module boundary

Цель: удержать boycott-модуль изолированным от halal-ядра.

Задачи:

- J1. Описать feature flag/config для boycott-модуля.
- J2. Создать отдельный result contract для boycott.
- J3. Создать поля campaign, tier, source, date, explanation.
- J4. Проверить, что отключение boycott не меняет halal verdict.
- J5. Добавить dispute path для boycott records.

Definition of Done:

- Halal rule engine не зависит от boycott-модуля.
- Отключение boycott не ломает базовый скан.
- Boycott status не может быть перепутан с `haram`.

Связанные документы:

- `docs/spec.md` FR-17, FR-18, FR-19, FR-20
- `docs/constitution.md` principle 7
- `docs/architecture.md` section 3.8

## 4. Initial implementation order after `go`

Рекомендуемый порядок:

1. Phase 0: Laravel backend foundation.
2. Phase A: Domain foundation.
3. Phase F: Golden dataset v1 skeleton.
4. Phase B: Rule engine MVP.
5. Phase C: Evidence and source registry.
6. Phase D: Certificate registry.
7. Phase E: Ingredient and process rules.
8. Phase G: Offline snapshot.
9. Phase H: UI result contract.
10. Phase I: Review queue.
11. Phase J: Boycott module boundary.

Причина: сначала нужен доменный язык и тестовые кейсы, затем rule engine, затем данные и интерфейсные контракты.

## 5. Testing expectations

После `go` каждая реализационная задача должна иметь тест до кода.

Минимальные уровни:

- unit tests for domain value objects;
- unit tests for rule aggregation;
- BDD-backed tests for `features/*.feature`;
- golden dataset regression tests;
- snapshot validation tests;
- UI contract tests before real UI wiring.

Критические regression tests:

- unknown is never halal;
- user claim is never authoritative without review;
- halal label without certificate is not halal;
- `makruh_tahrimi` is not silently plain halal;
- `makruh_tanzihi` needs sufficient halal evidence;
- `istihalah` requires process evidence;
- boycott does not alter halal verdict.

## 6. Open decisions before coding

Нужно решить до или в начале Phase A:

1. Целевой технический стек Android MVP.
2. Формат локального snapshot.
3. Формат golden dataset.
4. Нужно ли выносить rule engine в shared package позже, если mobile offline logic потребует локального исполнения.
5. Кто утверждает religious rules до публичного запуска.
6. Какие certification bodies считать trusted tier 1 для Узбекистана и СНГ.
7. Включаем ли `tayyib`/safety notes в v1 UI или только в data model.
8. Нужна ли account-backed mobile API authentication в MVP или базовый scan остается полностью anonymous.

## 7. Stop condition

После согласования `docs/tasks.md` работа по SDD-фазе завершается.

Следующее действие - ждать явного `go` от владельца продукта перед созданием тестов, структуры проекта или кода.
