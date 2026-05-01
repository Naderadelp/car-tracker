# Backend Patterns Reference

A practical reference for how this Laravel application handles core concerns.

---

## Table of Contents

1. [Entities (Models)](#1-entities-models)
2. [Relations](#2-relations)
3. [Repository Contract](#3-repository-contract)
4. [Eloquent Repository](#4-eloquent-repository)
5. [Controllers](#5-controllers)
6. [Responses & Resources](#6-responses--resources)
7. [Permissions & Policies](#7-permissions--policies)
8. [sync:permissions Command](#8-syncpermissions-command)
9. [Media](#9-media)
10. [Excel Export / Import](#10-excel-export--import)
11. [PDF Generation](#11-pdf-generation)

---

## 1. Entities (Models)

**Location:** `src/Domain/{Module}/Entities/`

Models are lean. Business logic is split into **Relations** and **Attributes** traits.

```php
class GoogleExcelSheet extends Model
{
    use GoogleExcelSheetRelations, GoogleExcelSheetAttributes, SoftDeletes;

    public static $logAttributes = ['*'];
    protected static $logName = 'GoogleExcelSheet';

    protected $fillable = [
        'sheet_name', 'spread_sheet_id', 'sheet_id',
        'lead_source_id', 'lead_channel_id', 'project_id', 'lead_stage_id',
    ];

    protected $table = 'google_excel_sheets';

    // Binds this model to a repository for route model binding
    protected $routeRepoBinding = GoogleExcelSheetRepository::class;
}
```

**Standard traits used on models:**

| Trait | Purpose |
|-------|---------|
| `SoftDeletes` | Soft-delete support |
| `HasFactory` | Factory support |
| `LogsActivity` | Spatie activity log |
| `InteractsWithMedia` | Spatie MediaLibrary |
| `NodeTrait` | Nested set (tree) structures |

**Casts go in a `casts()` method:**

```php
protected function casts(): array
{
    return [
        'started_at' => 'datetime:Y-m-d H:i',
        'is_active'  => 'boolean',
    ];
}
```

**Activity logging:**

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()->logOnly(['*', 'custom_properties.*']);
}
```

**Global scopes and boot hooks:**

```php
protected static function booted(): void
{
    static::addGlobalScope(new DisplayAllTasks());

    static::creating(function ($model) {
        $model->reference_number = static::generateReferenceNumber();
    });
}
```

---

## 2. Relations

**Location:** `src/Domain/{Module}/Entities/Traits/{Entity}Relations.php`

Relations live in a dedicated trait to keep the model file short.

```php
trait GoogleExcelSheetRelations
{
    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GoogleExcelSheetEntry::class);
    }
}
```

**Many-to-many with pivot data:**

```php
public function handlers(): BelongsToMany
{
    return $this->belongsToMany(
        User::class,
        'complaint_category_handlers',
        'category_id',
        'user_id'
    )->withPivot('is_primary')->withTimestamps();
}
```

**Query scopes (defined on the model):**

```php
public function scopeOpen(Builder $query): Builder
{
    return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
}

public function scopeOverdue(Builder $query): Builder
{
    return $query->where('due_date', '<', now())
                 ->whereNotIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
}
```

---

## 3. Repository Contract

**Location:** `src/Domain/{Module}/Repositories/Contracts/{Entity}Repository.php`

Each domain defines a contract interface that extends the base `RepositoryInterface`. Typically empty — it just tags the implementation.

```php
// src/Domain/Broker/Repositories/Contracts/BrokerRepository.php
interface BrokerRepository extends RepositoryInterface
{
    // Add domain-specific method signatures here if needed
}
```

The base `RepositoryInterface` (`src/Infrastructure/l5/Contracts/RepositoryInterface.php`) declares:

```
all(), find(), findWhere(), findWhereFirst(), findWhereIn(), findWhereBetween(),
paginate(), create(), update(), delete(), with(), scopeQuery(),
lists(), pluck(), sync(), orderBy(), whereHas(), withCount(),
hidden(), visible(), skipPresenter(), firstOrNew(), firstOrCreate()
```

Bind the contract to the implementation in the domain's service provider:

```php
$this->app->bind(BrokerRepository::class, BrokerRepositoryEloquent::class);
```

---

## 4. Eloquent Repository

**Base class:** `src/Infrastructure/AbstractRepositories/EloquentRepository.php`  
**Location:** `src/Domain/{Module}/Repositories/Eloquent/{Entity}RepositoryEloquent.php`

### Minimal structure

```php
class BrokerRepositoryEloquent extends EloquentRepository implements BrokerRepository
{
    public function model(): string
    {
        return Broker::class;
    }
}
```

### Spatie QueryBuilder integration

The `EloquentRepository` exposes static properties that map directly to Spatie QueryBuilder's allowed-* arrays. Declare them on the concrete repository:

```php
class BrokerRepositoryEloquent extends EloquentRepository implements BrokerRepository
{
    // Relations the API caller can eager-load via ?include=
    protected $allowedIncludes = ['percentages', 'mobiles', 'defaultMobile', 'media', 'requester', 'franchises'];

    // Partial (LIKE) text filters via ?filter[name]=
    protected $allowedFilters = ['mobiles.mobile', 'name', 'identification_number', 'contact_mail'];

    // Exact-match filters; supports dot-notation for relation columns
    protected $allowedFiltersExact = [
        'id',
        'status',
        'user.franchise_id',   // filters on a relation column
        'user.department_id',
    ];

    // Model scope names callable as filters via ?filter[createdBetween]=
    protected $allowedFilterScopes = ['createdBetween', 'attendanceBetween'];

    // Sortable fields (?sort=-created_at); prefix with - for DESC default
    protected $allowedSorts    = ['name', 'created_at'];
    protected $allowedDefaultSorts = ['-id', '-created_at'];

    public function model(): string
    {
        return Broker::class;
    }
}
```

### The `spatie()` method

Call `->spatie()` on the repository to apply all request-driven filters, includes, and sorts before calling `->all()` or `->paginate()`.

```php
// In a controller
$results = $this->brokerRepository->spatie()->paginate(paginationPerPage());

// Pass explicit params instead of reading from the request (e.g. in an export job)
$results = $this->brokerRepository->spatie($queryParams);
```

**Query parameters consumed by `spatie()`:**

| Parameter | Example | Effect |
|-----------|---------|--------|
| `include` | `?include=requester,defaultMobile` | Eager-loads relations |
| `filter[name]` | `?filter[name]=John` | Partial LIKE search |
| `filter[status]` | `?filter[status]=active` | Exact match (if in `allowedFiltersExact`) |
| `filter_op[status]` | `?filter_op[status]=equals` | Override operator per field |
| `sort` | `?sort=-created_at,name` | Sort (- = DESC) |
| `filter_groups` | JSON array | AND/OR filter groups (see below) |

**Supported `filter_op` operators:**
`equals`, `not_equals`, `contains`, `not_contains`, `greater_than`, `greater_than_or_equal`, `less_than`, `less_than_or_equal`, `between`, `not_between`, `is_null`, `is_not_null`, `in`, `not_in`, `before`, `after`, `today`, `this_week`, `this_month`

**Filter groups (AND/OR logic):**

```json
filter_groups: [
  {
    "operator": "AND",
    "filters": [
      { "name": "status",     "value": "active",                 "filter_operator": "equals"  },
      { "name": "created_at", "value": "2025-01-01,2025-12-31",  "filter_operator": "between" }
    ]
  }
]
```

### Export methods

Implement these three methods on every repository that supports export:

```php
// Column headers row
public function exportHeadings(): array
{
    return ['#ID', 'Name', 'Default Mobile', 'Email', 'Status'];
}

// Map one Eloquent row to an array of cell values
public function exportMapsData($row): array
{
    return [
        $row->id,
        $row->name,
        $row->defaultMobile?->mobile,
        $row->contact_mail,
        $row->status,
    ];
}

// Relations to eager-load on the export query (prevents N+1)
public function exportRelations(): array
{
    return ['defaultMobile', 'requester'];
}

// Optional: extra query scopes applied only during export
public function exportExtraScopes($query)
{
    return $query->addCheckValues()->firstCheckinPerDay();
}
```

### Typical controller usage

```php
public function index(Request $request): JsonResponse
{
    $this->authorize('viewAny', Broker::class);

    $brokers = $this->brokerRepository->spatie()->paginate(paginationPerPage());

    $this->setData('data', $brokers);
    $this->useCollection(BrokerResource::class, 'data');

    return $this->response();
}
```

---

## 5. Controllers

**Location:** `src/Domain/{Module}/Http/Controllers/`

Controllers inject a **repository** through the constructor and use the `Responder` trait.

```php
class BrokerController extends Controller
{
    use Responder;

    public function __construct(protected BrokerRepository $brokerRepository) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Broker::class);

        $brokers = $this->brokerRepository->spatie()->paginate(paginationPerPage());

        $this->setData('data', $brokers);
        $this->useCollection(BrokerResource::class, 'data');

        return $this->response();
    }

    public function store(BrokerStoreFormRequest $request): JsonResponse
    {
        $broker = $this->brokerRepository->create($request->validated());

        $this->setData('data', $broker);
        $this->useCollection(BrokerResource::class, 'data');

        return $this->response();
    }

    public function update(BrokerUpdateFormRequest $request, int $id): JsonResponse
    {
        $broker = $this->brokerRepository->update($request->validated(), $id);

        $this->setData('data', $broker);
        $this->useCollection(BrokerResource::class, 'data');

        return $this->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('delete', $this->brokerRepository->find($id));

        $this->brokerRepository->delete($id);

        $this->setApiResponse(fn () => response()->json(['message' => 'Deleted'], 200));

        return $this->response();
    }
}
```

**With DB transaction and media upload:**

```php
public function store(CaliberStoreFormRequest $request): JsonResponse
{
    try {
        DB::beginTransaction();

        $record = $this->repository->create($request->validated());

        if ($request->hasFile('file')) {
            $record->addMediaFromRequest('file')->toMediaCollection('cv_calibers');
        }

        DB::commit();
    } catch (Exception $e) {
        DB::rollBack();
        $this->setApiResponse(fn () => response()->json(['error' => $e->getMessage()], 422));
    }

    $this->setData('data', $record);
    $this->useCollection(CaliberResource::class, 'data');

    return $this->response();
}
```

**Invokable (single-action) controllers:**

```php
class ExportBrokerController extends Controller
{
    public function __construct(protected BrokerRepository $brokerRepository) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('export', Broker::class);

        $path = 'exports/' . time() . '_' . auth()->id() . '.xlsx';

        (new DataExport(BrokerRepositoryEloquent::class, auth()->user(), $path, 'Brokers'))
            ->setQueryParams($request->input())
            ->queue($path, 'public');

        return response()->json(['message' => 'Export started'], 200);
    }
}
```

**Rules:**
- Always use Form Request classes — never inline `validate()` in a controller.
- Always `authorize()` before touching data.
- Wrap writes with side-effects in `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`.

---

## 6. Responses & Resources

**Location:** `src/Domain/{Module}/Http/Resources/`

### API Resources

```php
class BrokerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'status'                 => $this->status,
            'identification_number'  => $this->identification_number,

            // Include only when the count was added via withCount()
            'deals_count' => $this->when(isset($this->deals_count), $this->deals_count),

            // Include only when the relation was eager-loaded (prevents N+1)
            'default_mobile' => $this->whenLoaded('defaultMobile', fn () => [
                'id'     => $this->defaultMobile->id,
                'mobile' => $this->defaultMobile->mobile,
            ]),

            // Nested single resource
            'requester' => new UserResource($this->whenLoaded('requester')),

            // Nested collection
            'franchises' => FranchiseResource::collection($this->whenLoaded('franchises')),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

**Key helpers:**

| Helper | Use case |
|--------|---------|
| `$this->when($condition, $value)` | Include field only when condition is true |
| `$this->whenLoaded('relation', ...)` | Include relation only when eager-loaded |
| `new SomeResource($this->whenLoaded('rel'))` | Nested single resource |
| `SomeResource::collection($this->whenLoaded('rel'))` | Nested collection |
| `->toISOString()` | Standard ISO date format |

### Responder Trait

Controllers use the `Responder` trait to return either an API JSON response or a Blade view from the same method.

```php
// Set data for both contexts
$this->setData('data', $model);

// Wrap with a resource class
$this->useCollection(SomeResource::class, 'data');

// Override the entire API response manually
$this->setApiResponse(fn () => response()->json([...], 201));

// Returns JSON if the request expects JSON, otherwise returns a Blade view
return $this->response();
```

---

## 7. Permissions & Policies

**Location:** `src/Domain/{Module}/Policies/`  
**Package:** Spatie Laravel-Permission

### Policy structure

```php
class BrokerPolicy
{
    use HandlesAuthorization;

    // Super-admin bypasses all checks
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('index-broker');
    }

    public function view(User $user, Broker $broker): bool
    {
        // Global permission OR resource ownership
        return $user->hasPermissionTo('show-broker')
            || $broker->agent_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-broker');
    }

    public function update(User $user, Broker $broker): bool
    {
        return $user->hasPermissionTo('update-broker')
            || $broker->agent_id === $user->id;
    }

    public function delete(User $user, Broker $broker): bool
    {
        return $user->hasPermissionTo('delete-broker');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export-broker');
    }
}
```

### Using policies in controllers

```php
$this->authorize('viewAny', Broker::class);   // on a collection
$this->authorize('update', $broker);           // on an instance
```

**Permission naming convention:** `{action}-{kebab-case-entity}`  
Examples: `index-broker`, `create-task`, `export-attendance`

---

## 8. sync:permissions Command

**Command:** `php artisan sync:permissions`  
**File:** `src/Common/Commands/Console/SyncPermissionsAndRoles.php`  
**Seeder:** `src/Domain/User/Database/Seeds/RolePermissionsSeederTableSeeder.php`

Run this command any time you add a new model or a new custom permission to make them available in the system.

```bash
php artisan sync:permissions
```

### What it does

1. Clears the Spatie permission cache.
2. **Creates CRUD permissions** for every model in the `$models` array — generates 7 permissions per model using the pattern `{action}-{model-name}`:
   - `index-{model}`, `show-{model}`, `create-{model}`, `edit-{model}`, `destroy-{model}`, `force-delete-{model}`, `restore-{model}`
3. **Creates custom permissions** from the `$customPermissions` array (one-off permissions that don't follow the CRUD pattern).
4. **Creates default roles**: `super-admin`, `sales`, `team-leader`.
5. **Assigns all permissions to `super-admin`** automatically.

All creates use `firstOrCreate` — re-running the command is safe and idempotent.

---

### Adding a new model's CRUD permissions

Add the kebab-case model name to the `$models` array in the seeder, then run the command:

```php
// src/Domain/User/Database/Seeds/RolePermissionsSeederTableSeeder.php

private $models = [
    // ... existing models ...
    'project-tender',   // ← add your new model here
];
```

This generates:
```
index-project-tender
show-project-tender
create-project-tender
edit-project-tender
destroy-project-tender
force-delete-project-tender
restore-project-tender
```

Then run:

```bash
php artisan sync:permissions
```

---

### Adding a custom (non-CRUD) permission

Add the permission string to the `$customPermissions` array, then run the command:

```php
private $customPermissions = [
    // ... existing permissions ...
    'export-project-tender',
    'import-project-tender',
];
```

---

### Using a new permission in a Policy

After running `sync:permissions`, reference the string in your Policy:

```php
public function viewAny(User $user): bool
{
    return $user->hasPermissionTo('index-project-tender');
}

public function create(User $user): bool
{
    return $user->hasPermissionTo('create-project-tender');
}

// Custom permission
public function export(User $user): bool
{
    return $user->hasPermissionTo('export-project-tender');
}
```

---

## 9. Media

**Location:** `src/Domain/Media/`  
**Package:** Spatie MediaLibrary

### Enable media on a model

```php
class Task extends Model implements HasMedia
{
    use InteractsWithMedia;
}
```

### Uploading files in a controller

```php
// Single file
$model->addMediaFromRequest('file')->toMediaCollection('documents');

// Multiple files (e.g. attachments[0][file], attachments[1][file])
foreach ($request->input('attachments', []) as $index => $attachment) {
    $model->addMediaFromRequest("attachments.{$index}.file")
          ->toMediaCollection('task');
}
```

### Centralized upload endpoint

`POST /media` — handled by `CreateMediaController` (invokable).

| Field | Type | Description |
|-------|------|-------------|
| `model_type` | string | e.g. `"task"`, `"lead"` |
| `model_id` | integer | ID of the target model |
| `file` | file | The uploaded file |
| `collection` | string | Media collection name |

Supported model types are configured in `config/media-library.php` under `supported_models`.

### Retrieving media

```php
$model->getMedia('documents');                         // All in collection
$model->getFirstMediaUrl('avatar');                    // URL of first file
$model->getMedia('pdf-images', ['section' => 'hero']); // Filter by custom properties
```

---

## 10. Excel Export / Import

### Export

**Location:** `src/Common/Export/DataExport.php`  
**Package:** Maatwebsite Laravel-Excel

#### Step 1 — implement export methods on the repository

```php
public function exportHeadings(): array
{
    return ['#ID', 'Name', 'Mobile', 'Status'];
}

public function exportMapsData($row): array
{
    return [
        $row->id,
        $row->name,
        $row->defaultMobile?->mobile,
        $row->status,
    ];
}

public function exportRelations(): array
{
    return ['defaultMobile', 'requester'];
}

// Optional — additional query scopes applied only during export
public function exportExtraScopes($query)
{
    return $query->someScope();
}
```

#### Step 2 — dispatch from an invokable controller

```php
class ExportBrokerController extends Controller
{
    public function __construct(protected BrokerRepository $brokerRepository) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('export', Broker::class);

        $path = 'exports/' . time() . '_' . auth()->id() . '.xlsx';

        (new DataExport(BrokerRepositoryEloquent::class, auth()->user(), $path, 'Brokers'))
            ->setQueryParams($request->input())  // forwards all active filters/sorts/includes
            ->queue($path, 'public');

        return response()->json(['message' => 'Export started'], 200);
    }
}
```

**What `DataExport` does internally:**
1. Calls `repository->spatie($queryParams)` to apply all filters from the request.
2. Eager-loads `exportRelations()` on the query.
3. Calls `exportExtraScopes()` if it exists.
4. Streams rows in chunks of 500, mapping each via `exportMapsData()`.
5. Tracks progress via `ExportLog` and broadcasts percentage updates.
6. Marks the log as completed and notifies the user when done.

---

### Import

**Location:** `src/Common/Services/ImportExcel/ImportDataFromExcel.php`  
**Package:** FastExcel (reading), Maatwebsite (structure)

Extend the abstract class and implement the required interfaces:

```php
class ImportLeads extends ImportDataFromExcel implements HasValidationRules
{
    public function model(): string
    {
        return Lead::class;
    }

    public function rules(array $row): array
    {
        return [
            'name'  => ['required', 'string'],
            'phone' => ['required', 'unique:leads,phone'],
        ];
    }
}
```

**Available contracts:**

| Interface | Purpose |
|-----------|---------|
| `HasValidationRules` | Per-row validation rules |
| `HasAuthorizationCheck` | Per-row authorization |

**Processing pipeline (handled by the base class):**
1. Read file with FastExcel into a lazy collection.
2. For each row: validate → authorize (optional) → fill model → fire `ModelCreating` → save → fire `ModelCreated`.
3. Collect errors and write them to a downloadable error file.
4. Create an `ImportLog` record with status and error count.

---

## 11. PDF Generation

**Location:** `src/Domain/GeneratePdf/`  
**Package:** Spatie Laravel-PDF (Browsershot)

### Service pattern

```php
class MarqContractPdfService
{
    private function build(array $data): PdfBuilder
    {
        return Pdf::view('pdf.contract-marq', $data)
                  ->withBrowsershot(fn ($bs) => $bs->noSandbox());
    }

    public function download(array $data, string $filename): StreamedResponse
    {
        return $this->build($data)->download($filename);
    }

    public function inline(array $data, string $filename): StreamedResponse
    {
        return $this->build($data)->inline($filename);
    }

    public function save(array $data, string $path): void
    {
        $this->build($data)->save($path);
    }

    public function base64(array $data): string
    {
        return $this->build($data)->base64();
    }
}
```

### Image-section PDFs (unit launch documents)

```php
public function generatePDF(GeneratePdfFormRequest $request): JsonResponse
{
    $model = $request->model_type::findOrFail($request->model_id);

    $sections = collect($request->pdf_sections)->map(
        fn ($section) => $model->getMedia('pdf-images', ['section-name' => $section])
    );

    $pdf = Pdf::loadView('generate_pdf::generate-pdf', ['sections' => $sections]);

    $path = "unit-pdf/{$model->id}.pdf";
    Storage::put("public/{$path}", $pdf->output());

    return response()->json(['download_link' => asset("storage/{$path}")]);
}
```

**Notes:**
- PDF Blade views live in `resources/views/pdf/` or the module's view namespace.
- Use inline styles — external CSS is unreliable in headless Chromium.
