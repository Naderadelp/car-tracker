# Implementation Plan: CSV Import for Reference Data

**Branch**: `009-csv-import-reference-data` | **Date**: 2026-08-18 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/009-csv-import-reference-data/spec.md`

## Summary

Add CSV import to the six reference-data resources in the Filament admin panel
(brands, car models, items, services, service centres, fuel prices) using Filament's
built-in `ImportAction`, with per-column validation mirroring the existing Form
Requests, a headers-example table in the modal, a downloadable sample file, and
background processing with failure reporting.

Most of this ships with `filament/actions` v5.7.6 already installed. The work is
declaring six `Importer` classes, wiring the action onto six List pages, publishing
two migrations, closing an authorization gap, and adding a queue worker to deploy.

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 13.26
**Primary Dependencies**: `filament/filament` ^5.7 (panel + actions), `league/csv`
(parsing, transitive via filament/actions), spatie/laravel-permission (`api` guard)
**Storage**: PostgreSQL. Two new tables (`imports`, `failed_import_rows`) from
published Filament migrations. No changes to the six target tables.
**Testing**: PHPUnit against sqlite in-memory. Existing suite is 70 tests; feature
tests live in `tests/Feature/Filament/`.
**Target Platform**: Laravel Forge (Linux). Admin panel is browser-facing; the
mobile API is unaffected.
**Project Type**: Laravel monolith — REST API plus a Filament admin panel
**Performance Goals**: 1,000-row file imports without blocking the browser or
timing out (SC-003). Filament chunks at 100 rows per queued job by default.
**Constraints**: Must not alter `routes/api.php`, controllers, API Resources or Form
Requests — the mobile client depends on them. Import reuses their validation rules
by reference, not by duplication.
**Scale/Scope**: 6 importer classes, 6 List-page edits, 2 published migrations, 1
authorization map entry, 1 deploy daemon.

## Key Research Findings

Two findings from reading `vendor/filament/actions/` materially shape the work.

**1. The sample download and per-column examples already exist.** `ImportColumn`
provides `example()`, `examples()` and `exampleHeader()`
(`vendor/filament/actions/src/Imports/ImportColumn.php:138,145,159`), and
`ImportAction` registers a `downloadExample` modal action that builds the sample CSV
from those declarations (`ImportAction.php:356-403`). FR-009 requires no
sample-generation code — only that each column declares an example.

**2. `modalDescription()` is already occupied by that download link.**

```php
// vendor/filament/actions/src/ImportAction.php:83
$this->modalDescription(fn (ImportAction $action): Htmlable => $action->getModalAction('downloadExample'));
```

Calling `->modalDescription(...)` to add the headers table would **silently remove
the Download Sample link**. The headers-example table must go in `modalContent()`
(`Concerns/CanOpenModal.php:290`), which renders above the form schema. The default
`modalWidth('xl')` will need widening to fit a table.

Also noted: `Concerns/CanImportRecords.php` does not exist in v5 — everything is
inlined into `ImportAction.php`. `Importer` has only two abstract methods,
`getColumns()` and `getCompletedNotificationBody()`.

## Constitution Check

*GATE: passed before Phase 0 and re-checked after Phase 1.*

| Principle | Compliance |
|---|---|
| **I. Repository Pattern (non-negotiable)** | ⚠️ Deviation — see Complexity Tracking. Filament resources and importers query Eloquent directly. |
| **II. Form Request validation** | ⚠️ Partial — importers declare `rules()` per column rather than using a Form Request class. Rules are sourced from the existing requests so there is one source of truth in intent, though not in code. |
| **III. BaseController response methods** | ✅ N/A — no API controllers are touched. |
| **IV. Standard Laravel folder structure** | ✅ New `app/Filament/Imports/` follows the panel's existing `app/Filament/*` convention. |
| **V. Authorization via Policies** | ✅ Closed by this feature. `import` is added to `AuthorizesWithApiPermissions::permissionActionMap()`; see below. |

**Authorization gap this feature must close (FR-012).** `ImportAction` performs no
authorization of its own — the library says so explicitly at `Imports/Importer.php:18-24`.
`AuthorizesWithApiPermissions::permissionActionMap()` (lines 38–54) has no `import`
entry, so the check falls through to Filament's helper, finds no `import()` method on
any policy, and — because `strictAuthorization()` is never enabled in
`AdminPanelProvider` — returns `Response::allow()`. **The ability currently fails
open.** Mapping `'import' => 'create'` reuses the existing `create-{subject}`
permissions and requires no seeder change.

## Project Structure

### Documentation (this feature)

```text
specs/009-csv-import-reference-data/
├── plan.md              # This file
├── spec.md              # Feature specification
└── tasks.md             # Phase 2 output (/speckit-tasks — not created by /speckit-plan)
```

### Source Code (repository root)

```text
database/migrations/
├── ####_##_##_######_create_imports_table.php             # published
└── ####_##_##_######_create_failed_import_rows_table.php  # published

app/Filament/
├── Imports/                                    # NEW directory
│   ├── BrandImporter.php
│   ├── CarModelImporter.php
│   ├── ItemImporter.php
│   ├── ServiceImporter.php
│   ├── ServiceCenterImporter.php
│   └── FuelPriceImporter.php
├── Concerns/
│   └── AuthorizesWithApiPermissions.php        # + 'import' => 'create'
└── Resources/
    ├── Brands/Pages/ListBrands.php             # + ImportAction in getHeaderActions()
    ├── CarModels/Pages/ListCarModels.php       # (same, ×6)
    ├── Items/Pages/ListItems.php
    ├── Services/Pages/ListServices.php
    ├── ServiceCenters/Pages/ListServiceCenters.php
    └── FuelPrices/Pages/ListFuelPrices.php

resources/views/filament/imports/
└── headers-example.blade.php                   # NEW — modalContent() table

tests/Feature/Filament/
└── ImportTest.php                              # NEW
```

**Structure Decision**: Single Laravel application. Importers get their own
`app/Filament/Imports/` directory, matching the panel's existing convention of
`app/Filament/{Resources,Concerns,Support}/`. Nothing under `app/Http/`, `routes/`
or `app/Repositories/` is touched.

## Implementation Sequence

**Phase A — foundation (blocks everything)**
1. `php artisan vendor:publish --tag=filament-actions-migrations` then `migrate`.
2. Add `'import' => 'create'` to `AuthorizesWithApiPermissions::permissionActionMap()`.

**Phase B — one vertical slice (User Story 1, P1)**
3. `BrandImporter` — the simplest table (single `name` column, unique). Prove the
   whole path end to end: upload → queue → rows created → completion notification.
4. `ImportAction::make()->importer(BrandImporter::class)` on `ListBrands`, with an
   explicit `->authorize()` since header actions do not route through the resource's
   static `getAuthorizationResponse()` automatically.

**Phase C — remaining importers (User Story 1)**
5. `ItemImporter`, `FuelPriceImporter` — flat tables. `FuelPriceImporter` validates
   `type` against `FuelPriceForm::TYPES` rather than redeclaring the enum, following
   house style where `FuelPricesTable` already reuses that constant.
6. `CarModelImporter`, `ServiceImporter`, `ServiceCenterImporter` — these resolve a
   related record by name via `ImportColumn::relationship()` (FR-010), and carry
   cross-field rules (`close_at` after `open_at`; car model unique on
   name + brand + year).

**Phase D — format guidance (User Story 2, P2)**
7. Declare `example()` on every column across all six importers.
8. Build `headers-example.blade.php` and attach via `modalContent()` — **not**
   `modalDescription()`. Widen the modal.

**Phase E — failure recovery (User Story 3, P3)**
9. Verify the failed-rows CSV download and completion counts. Largely free; needs
   confirmation rather than construction.

**Phase F — operations**
10. Forge daemon: `php artisan queue:work --queue=default`. Add `queue:restart` to
    the deploy script. Document in the deploy checklist.
11. Tests in `tests/Feature/Filament/ImportTest.php`: a valid file imports; a mixed
    file imports the valid rows and records the rest; a non-admin cannot reach the
    action; the downloaded sample re-imports cleanly.

Phases B and C map to User Story 1, D to Story 2, E to Story 3 — each independently
demonstrable, per the spec's prioritization.

## Verification

- `php artisan test` — full suite green (70 existing + new import tests).
- Manual: on each of the six screens, open Import, confirm the headers table and
  Download Sample are both present, download the sample, re-upload it unmodified,
  confirm rows are created.
- Negative: log in as a `user`-role account and confirm the Import button is absent
  and the action is unreachable.
- Operational: stop the queue worker, start an import, and confirm the behaviour is
  understood (file accepted, nothing processed) — this is the FR-013 failure mode and
  must be observed at least once so it is recognisable in production.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Filament importers query Eloquent directly, bypassing the repository layer (Principle I) | `Importer::resolveRecord()` and `ImportColumn::relationship()` are framework extension points with fixed signatures that construct and persist models themselves. Routing them through repositories would mean reimplementing Filament's import pipeline. | Already accepted for the admin panel as a whole in feature 008's follow-on — every `app/Filament/Resources/*` class binds `$model` directly. Import does not introduce the deviation, it inherits it. The API layer remains fully repository-backed. |
| Validation declared per `ImportColumn` rather than in a Form Request (Principle II) | `ImportColumn::rules()` is how Filament validates rows; there is no Form Request in the import path. | Instantiating the existing Form Requests outside an HTTP request is fragile — several use `$this->route(...)` for unique-ignore rules, which is null off-request. Mitigation: each importer carries a comment naming the Form Request its rules mirror, so divergence is visible in review. |
