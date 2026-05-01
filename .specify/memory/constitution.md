<!--
Sync Impact Report
==================
Version change: (initial fill) → 1.0.0
Modified principles: All placeholder tokens replaced with Car Tracker / Laravel architectural rules
Added sections:
  - Core Principles (I–VI)
  - Folder & Module Structure
  - Development Workflow
  - Governance
Removed sections: None (all sourced from template)
Templates requiring updates:
  ✅ .specify/memory/constitution.md — this file (initial fill complete)
  ✅ .specify/templates/plan-template.md — Constitution Check section is a dynamic placeholder;
     aligned; gates will reference these principles when /speckit-plan runs
  ✅ .specify/templates/spec-template.md — requirements structure compatible; no changes needed
  ✅ .specify/templates/tasks-template.md — path conventions note "adjust based on plan.md";
     Domain structure must be applied per-feature in generated tasks.md
Follow-up TODOs:
  - TODO(RATIFICATION_DATE): Using 2026-04-30 (today) as the ratification date; update if the
    actual project start date is known and differs.
-->

# Car Tracker Constitution

## Core Principles

### I. Repository Pattern (NON-NEGOTIABLE)

All database access MUST go through a repository. Controllers MUST NOT query Eloquent models
directly. Every domain entity requires:

- A contract interface at `src/Domain/{Module}/Repositories/Contracts/{Entity}Repository.php`
  extending `RepositoryInterface`
- An Eloquent implementation at
  `src/Domain/{Module}/Repositories/Eloquent/{Entity}RepositoryEloquent.php`
  extending `EloquentRepository`
- A service-provider binding:
  `$this->app->bind(EntityRepository::class, EntityRepositoryEloquent::class)`

Use `->spatie()` before `->paginate()` or `->all()` to apply request-driven filters, includes,
and sorts. Repositories that support export MUST implement `exportHeadings()`,
`exportMapsData()`, and `exportRelations()`.

### II. Form Request Validation (NON-NEGOTIABLE)

Validation MUST NEVER appear inline inside a controller method (no `$request->validate([...])`).
Every write operation (store / update) MUST use a dedicated Form Request class located at
`src/Domain/{Module}/Http/Requests/`. Controllers receive pre-validated data via
`$request->validated()` only.

### III. Responder Trait (NON-NEGOTIABLE)

Every controller MUST use the `Responder` trait. Responses are built with:

- `$this->setData('data', $model)` — set the payload
- `$this->useCollection(SomeResource::class, 'data')` — wrap with an API Resource
- `$this->setApiResponse(fn () => response()->json([...]))` — override the full response
- `return $this->response()` — final return (JSON for API clients, Blade for web)

Raw `return response()->json(...)` MUST NOT be used in standard controller actions.
Every API endpoint MUST return data through a dedicated API Resource class; raw array returns
are forbidden.

### IV. Domain-Driven Folder Structure

Code is organized by domain module under `src/Domain/{Module}/`:

```
src/Domain/{Module}/
├── Entities/
│   ├── {Entity}.php                           # Lean model (no business logic)
│   └── Traits/
│       ├── {Entity}Relations.php              # All Eloquent relations
│       └── {Entity}Attributes.php             # Accessors / mutators
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Policies/
├── Repositories/
│   ├── Contracts/{Entity}Repository.php
│   └── Eloquent/{Entity}RepositoryEloquent.php
└── Providers/{Module}ServiceProvider.php
```

Common/shared infrastructure lives in `src/Common/` and `src/Infrastructure/`.
No business logic MUST be placed outside these boundaries.
New modules MUST mirror this structure exactly; deviations require justification in the plan's
Complexity Tracking table.

### V. Authorization via Policies

Every controller action that reads or mutates data MUST call `$this->authorize()` before
touching data. Policies live at `src/Domain/{Module}/Policies/{Entity}Policy.php` and MUST
include a `before()` method granting super-admin unrestricted access via `isSuperAdmin()`.

Permission naming convention: `{action}-{kebab-case-entity}`
(e.g., `index-broker`, `create-task`, `export-attendance`).

After adding a model or custom permission, `php artisan sync:permissions` MUST be run.
Custom non-CRUD permissions MUST be added to `$customPermissions` in
`src/Domain/User/Database/Seeds/RolePermissionsSeederTableSeeder.php`.

### VI. Transactional Writes & Observability

Any write operation with side effects (media upload, relation syncing, external calls) MUST be
wrapped in `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`.

Models that are auditable MUST use the `LogsActivity` trait with `getActivitylogOptions()`
defined explicitly — log attribute coverage MUST NOT rely on framework defaults.

Models using media MUST implement `HasMedia` and use the `InteractsWithMedia` trait.
Media is uploaded via `addMediaFromRequest()->toMediaCollection()` or the centralized
`POST /media` endpoint; direct filesystem writes are forbidden for user-uploaded content.

## Folder & Module Structure

This project follows Domain-Driven Design (DDD) enforced by the folder conventions in
Principle IV. Infrastructure abstractions (base repository, base controller, common exports,
PDF services) live in `src/Infrastructure/` and `src/Common/` respectively and MUST NOT be
duplicated per domain.

View files for PDF generation live in `resources/views/pdf/` or the module's named view
namespace. PDF services extend the service pattern defined in `src/Domain/GeneratePdf/`.

Excel import/export utilities MUST extend `ImportDataFromExcel` (`src/Common/Services/`) and
`DataExport` (`src/Common/Export/`) respectively. Export controllers MUST be invokable
single-action controllers that queue the export job rather than streaming inline.

## Development Workflow

1. **Define the contract** — create the Repository interface before writing the Eloquent
   implementation.
2. **Bind in provider** — register the interface-to-implementation binding in the module's
   ServiceProvider immediately.
3. **Form Request first** — write the Form Request class before the controller action.
4. **Policy before controller** — define and register the Policy before wiring routes.
5. **Run `sync:permissions`** — after every new model or custom permission is added.
6. **Wrap side-effects** — use DB transactions for any multi-step write with side effects.
7. **Resource for every response** — all API data MUST pass through an API Resource class.

## Governance

This constitution supersedes all other practice documents and README guidance.
Amendments require:

1. A documented rationale explaining why the change is necessary.
2. A version bump following semantic versioning (MAJOR / MINOR / PATCH as defined below).
3. A migration note if existing code must be updated to comply.

**Versioning policy:**
- MAJOR — backward-incompatible removal or redefinition of a principle.
- MINOR — new principle or materially expanded guidance added.
- PATCH — clarifications, wording improvements, typo fixes.

All PRs MUST verify compliance with the principles above before merging.
Complexity violations (deviations from these principles) MUST be justified in the plan's
Complexity Tracking table before approval.
Reference `architecture_patterns.md` in the repository root for concrete code examples
illustrating each principle.

**Version**: 1.0.0 | **Ratified**: 2026-04-30 | **Last Amended**: 2026-04-30
