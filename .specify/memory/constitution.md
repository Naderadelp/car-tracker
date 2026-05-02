<!--
Sync Impact Report
==================
Version change: 1.1.0 → 1.2.0
Modified principles: I, II, III, IV, V, Folder & Module Structure, Development Workflow
Changes:
  - Principle I: Updated repository paths from src/Domain/ to app/Repositories/;
    added spatie() mutation-pattern guidance and $include / $allowedFilters properties
  - Principle II: Updated Form Request paths from src/Domain/{Module}/Http/Requests/
    to app/Http/Requests/{Module}/
  - Principle III: Replaced tai-be Responder trait description with BaseController
    helper-method pattern (success / error / paginated); added StreamedResponse exception note
  - Principle IV: Replaced DDD src/Domain/ structure with standard Laravel app/ layout
  - Principle V: Updated Policy paths; added AuthorizesRequests requirement; simplified
    permission notes to match car-tracker (ownership-based rather than RBAC)
  - Principle VI: Kept; removed obsolete common-export references
  - Folder & Module Structure: Replaced DDD tree with actual app/ tree
  - Development Workflow: Updated step wording to match actual project patterns
Templates requiring updates:
  - .specify/templates/plan-template.md — paths and architecture section should use
    app/ conventions going forward; update on next /speckit-plan run
-->

# Car Tracker Constitution

## Core Principles

### I. Repository Pattern (NON-NEGOTIABLE)

All database access MUST go through a repository. Controllers MUST NOT query Eloquent models
directly. Every domain entity requires:

- A contract interface at `app/Repositories/Contracts/{Entity}Repository.php`
  extending `RepositoryInterface`
- An Eloquent implementation at
  `app/Repositories/Eloquent/{Entity}RepositoryEloquent.php`
  extending `EloquentRepository`
- A service-provider binding in `app/Providers/RepositoryServiceProvider.php`:
  `$this->app->bind(EntityRepository::class, EntityRepositoryEloquent::class)`

**Mutation Pattern**: `EloquentRepository` uses a mutable `$this->model` property typed as
`EloquentBuilder|SpatieQueryBuilder`. Every read operation calls `resetModel()` at the end.
Write operations (`create`, `update`, `delete`, `firstOrCreate`) bypass `$this->model`
entirely and use `app($this->model())` to obtain a fresh model instance.

**`makeModel()` always produces a Builder**: `makeModel()` MUST call `->newQuery()` on the
resolved model instance. Never assign a bare `Model` instance to `$this->model` — Spatie
QueryBuilder's `for()` requires `Builder|Relation|string` and will throw a `TypeError` if
given a Model.

**Eager Loading**: Define `protected array $include = [...]` to auto-load relations in
`makeModel()`. These are applied for every read through `$this->model`.

**Spatie QueryBuilder**: Repositories that support filtered / sorted / included queries
MUST declare the relevant arrays:

```php
protected array $allowedIncludes = [];
protected array $allowedFilters = [];          // partial match
protected array $allowedFiltersExact = [];     // exact match
protected array $allowedFilterScopes = [];     // scope-based
protected array $allowedSorts = [];
protected array $allowedDefaultSorts = [];
```

Call `->spatie()` before `->paginate()` or `->all()` to apply request-driven filters,
includes, and sorts. When a mandatory scope (e.g. `vehicle_id`) must be applied before
Spatie wraps the query, chain `->where('column', $value)->spatie()->paginate()`.

**Spatie v7 variadic API**: `allowedIncludes()`, `allowedFilters()`, and `allowedSorts()`
are variadic — they accept individual arguments, not a plain array. Always spread the
arrays: `$query->allowedIncludes(...$this->allowedIncludes)`. Passing an array directly
throws `TypeError: must be of type AllowedInclude|string, array given`.

### II. Form Request Validation (NON-NEGOTIABLE)

Validation MUST NEVER appear inline inside a controller method (no `$request->validate([...])`).
Every write operation (store / update) MUST use a dedicated Form Request class located at
`app/Http/Requests/{Module}/`. Controllers receive pre-validated data via
`$request->validated()` only.

### III. BaseController Response Methods (NON-NEGOTIABLE)

Every API controller MUST extend `BaseController` (which extends `Controller` and uses the
`Responder` trait). Responses are built with:

- `$this->success($data, $status, $message)` — single-resource or operation responses
- `$this->paginated($paginator, ResourceClass::class)` — paginated collection responses
- `$this->error($message, $status, $errors)` — error responses

Raw `return response()->json(...)` MUST NOT be used in standard controller actions.
Every API endpoint MUST return data through a dedicated API Resource class; raw array returns
are forbidden.

**Exception — Streamed Binary Responses**: Endpoints that serve file downloads (e.g. secure
document streaming) MUST return a `StreamedResponse` directly because `JsonResponse` cannot
carry binary content. This is the only case where bypassing the above helpers is permitted.

### IV. Standard Laravel Folder Structure (NON-NEGOTIABLE)

Code is organized under `app/` following the standard Laravel MVC layout:

```
app/
├── Console/
│   └── Commands/                              # Artisan console commands
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php                     # Base (uses AuthorizesRequests)
│   │   ├── BaseController.php                 # Extends Controller, uses Responder
│   │   ├── Auth/                              # Auth controllers
│   │   └── {Domain}/                          # Domain controllers (no version prefix)
│   ├── Requests/
│   │   └── {Domain}/                          # Form Request classes per domain
│   └── Resources/
│       └── {Domain}/                          # API Resource classes per domain
├── Models/                                    # Eloquent models (flat, no domain nesting)
│   ├── Permission.php                         # Extends Spatie Permission (guard_name: api)
│   ├── Role.php                               # Extends Spatie Role (guard_name: api)
│   └── Traits/
│       ├── HasDefaultRoles.php                # isAdmin(), isSuperUser(), isUser()
│       └── UserRelations.php                  # Eloquent relations for User
├── Policies/                                  # Laravel Policies (flat)
├── Providers/
│   ├── AppServiceProvider.php
│   └── RepositoryServiceProvider.php          # Interface → Eloquent bindings
├── Repositories/
│   ├── Contracts/
│   │   ├── RepositoryInterface.php
│   │   └── {Entity}Repository.php
│   └── Eloquent/
│       ├── EloquentRepository.php             # Base mutation-pattern repository
│       └── {Entity}RepositoryEloquent.php
└── Traits/
    └── Responder.php
```

No business logic MUST be placed in Models beyond Eloquent relations, casts, and fillable
declarations. New entities MUST mirror this structure exactly; deviations require
justification in the plan's Complexity Tracking table.

### V. Authorization via Policies (NON-NEGOTIABLE)

Every controller action that reads or mutates data MUST call `$this->authorize()` before
touching data.

**AuthorizesRequests trait**: `app/Http/Controllers/Controller.php` MUST include
`use Illuminate\Foundation\Auth\Access\AuthorizesRequests` and `use AuthorizesRequests;`.
Without this, `$this->authorize()` is not available in any controller in the application.

**Role-Based Access (spatie/laravel-permission)**: The project uses three system roles:

| Role | Description |
|------|-------------|
| `admin` | Full access — `before()` returns `true`, bypassing all policy checks |
| `super-user` | Elevated access — assign specific permissions as needed |
| `user` | Standard access — ownership-based checks only |

The `User` model MUST use `Spatie\Permission\Traits\HasRoles` and `App\Models\Traits\HasDefaultRoles`.
Set `protected string $guard_name = 'api'` on the User model.

`Permission` and `Role` models MUST extend the Spatie base models and hard-code
`$guard_name = 'api'` so all permission checks use the Sanctum guard.

Every Policy MUST include a `before()` method:

```php
public function before(User $user, string $ability): ?bool
{
    return $user->isAdmin() ? true : null;
}
```

Ownership checks: verify `$user->id === $model->user_id` (or the relevant FK).
Every Policy method MUST explicitly return a `bool`.

After adding a new Policy, register it in `App\Providers\AppServiceProvider` or rely on
Laravel's automatic discovery for standard naming.

**Permission sync**: After any change to models, roles, or custom permissions in
`RolePermissionsSeeder`, run `php artisan sync:permissions`.
CRUD permissions are auto-generated as `{action}-{model}` (e.g., `create-vehicle`).
Custom permissions go in the `$customPermissions` array of `RolePermissionsSeeder`.

### VI. Transactional Writes & Observability

Any write operation with side effects (media upload, relation syncing, external calls) MUST be
wrapped in `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`.

Models that are auditable MUST use the `LogsActivity` trait with `getActivitylogOptions()`
defined explicitly — log attribute coverage MUST NOT rely on framework defaults.

Models using media MUST implement `HasMedia` and use the `InteractsWithMedia` trait.
Media is uploaded via `addMediaFromRequest()->toMediaCollection()`. The Spatie media
collection MUST define `->singleFile()` and `->useDisk('local')` for private storage.
Secure file access MUST return a `StreamedResponse`; public URL generation is forbidden for
private documents.

## API Routing Convention (NON-NEGOTIABLE)

This project does **NOT** use URL versioning. API routes MUST NOT include a `/v1/`, `/v2/`, or
any version prefix segment. All routes are registered directly under the `api` prefix defined
by the framework (e.g., `/api/vehicles/{vehicle}/documents`).

Controllers MUST NOT be namespaced under `Api/V1/` or any versioned namespace. They live
directly under `app/Http/Controllers/` or a domain-specific subfolder without a version
segment.

Any generated spec, plan, contract, or task that references `/api/v1/` or `Api\V1\` violates
this principle and MUST be corrected before implementation begins.

## Folder & Module Structure

This project follows the standard Laravel MVC layout enforced by the folder conventions in
Principle IV. All domain code lives under `app/` with no `src/Domain/` nesting.

Infrastructure abstractions (base repository, base controller, responder trait) are shared
across all domains and MUST NOT be duplicated per domain:
- Base repository: `app/Repositories/Eloquent/EloquentRepository.php`
- Base controller: `app/Http/Controllers/BaseController.php`
- Responder trait: `app/Traits/Responder.php`
- Repository bindings: `app/Providers/RepositoryServiceProvider.php`

## Development Workflow

1. **Define the contract** — create the Repository interface before writing the Eloquent
   implementation.
2. **Bind in provider** — register the interface-to-implementation binding in
   `RepositoryServiceProvider` immediately.
3. **Form Request first** — write the Form Request class before the controller action.
4. **Policy before controller** — define and register the Policy before wiring routes.
5. **Declare allowed filters/sorts** — add `$allowedFilters`, `$allowedSorts`, and related
   arrays to the repository before adding filter support to the controller.
6. **Wrap side-effects** — use DB transactions for any multi-step write with side effects.
7. **Resource for every response** — all API data MUST pass through an API Resource class.
8. **Run `sync:permissions`** — after every new model or custom permission is added to
   `RolePermissionsSeeder`, run `php artisan sync:permissions`.

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

**Version**: 1.3.1 | **Ratified**: 2026-04-30 | **Last Amended**: 2026-05-02
