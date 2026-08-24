# Data Model: Document Vault

**Feature**: 002-document-vault
**Date**: 2026-05-01

---

## Entities

### Document

**Table**: `documents`
**Module**: `app/Models/Document.php`
**Soft Deletes**: No
**Activity Log**: Yes (`LogsActivity`)
**Media**: Yes (`HasMedia`, `InteractsWithMedia` — collection: `vehicle_documents`)

| Column         | Type          | Modifiers                                    | Description                        |
|----------------|---------------|----------------------------------------------|------------------------------------|
| `id`           | bigint(20)    | unsigned, PK, auto-increment                 |                                    |
| `user_id`      | bigint(20)    | unsigned, FK → `users(id)`, cascade delete   | Owning user                        |
| `vehicle_id`   | bigint(20)    | unsigned, FK → `vehicles(id)`, cascade delete| Owning vehicle                     |
| `type`         | enum          | not null — see allowed values below          | Document category                  |
| `expiry_date`  | date          | nullable                                     | Optional document expiry date      |
| `created_at`   | timestamp     | nullable                                     |                                    |
| `updated_at`   | timestamp     | nullable                                     |                                    |

**Enum values for `type`**:
- `vehicle_license`
- `insurance_policy`
- `registration`
- `inspection_certificate`
- `driver_license`
- `finance_contract`

**Relationships**:
- `belongsTo(User::class)` — document is owned by one user
- `belongsTo(Vehicle::class)` — document is linked to one vehicle

**Casts**:
- `expiry_date` → `date`

**Fillable**: `user_id`, `vehicle_id`, `type`, `expiry_date`

**Validation rules (store)**:
- `type`: required, string, `Rule::in(Document::TYPES)`
- `expiry_date`: nullable, date, `after:today`
- `document_file`: required, file, mimes:pdf,jpg,jpeg,png, max:5120

**Media collection — `vehicle_documents`**:
- Disk: `local` (private — no public URL)
- `->singleFile()` — one file per document record; re-uploading replaces existing

---

### Media (Spatie-managed)

**Table**: `media`
**Module**: Framework (spatie/laravel-medialibrary — not domain-owned)

| Column             | Type         | Description                                |
|--------------------|--------------|--------------------------------------------|
| `id`               | bigint       | PK                                         |
| `model_type`       | varchar(255) | Polymorphic type (e.g., `App\Models\Document`) |
| `model_id`         | bigint       | Polymorphic ID (Document ID)               |
| `collection_name`  | varchar(255) | `vehicle_documents`                        |
| `name`             | varchar(255) | Original file name without extension       |
| `file_name`        | varchar(255) | Full file name with extension              |
| `mime_type`        | varchar(255) | e.g., `application/pdf`, `image/jpeg`      |
| `disk`             | varchar(255) | `local`                                    |
| `size`             | bigint       | File size in bytes                         |
| `manipulations`    | json         | Spatie image manipulation config           |
| `custom_properties`| json         | Reserved for future metadata               |
| `generated_conversions` | json   | Derived file versions (e.g., thumbnails)   |
| `order_column`     | int          | Ordering within collection                 |
| `created_at`       | timestamp    |                                            |
| `updated_at`       | timestamp    |                                            |

---

## State Transitions

### Store flow

```
[GET /api/vehicles/{vehicle}/documents]
  → Authenticate (auth:sanctum)
  → Authorize (DocumentPolicy::viewAny → vehicle owner check)
  → $documentRepository->where('vehicle_id', $vehicle->id)->spatie()->paginate()
      → Applies NULLS LAST ordering (expiry_date ascending, non-expiring last)
      → Supports filter[type] and sort=created_at via Spatie QueryBuilder
  → DocumentResource collection + pagination meta → 200 OK
```

---

### Store flow

```
[POST /api/vehicles/{vehicle}/documents]
  → Authenticate (auth:sanctum)
  → Authorize (DocumentPolicy::create → vehicle owner check)
  → Validate (StoreDocumentRequest)
  → DB::beginTransaction()
    → Document::create([user_id, vehicle_id, type, expiry_date])
    → $document->addMediaFromRequest('document_file')
                ->toMediaCollection('vehicle_documents')  ← writes to local disk
  → DB::commit() → DocumentResource → 201 Created
  → DB::rollBack() on any failure → 422 / 500
```

### Secure download flow

```
[GET /api/vehicles/{vehicle}/documents/{document}/file]
  → Authenticate (auth:sanctum)
  → Authorize (DocumentPolicy::view → document owner + vehicle cross-check)
  → $media = $document->getFirstMedia('vehicle_documents')
  → Abort 404 if no media attached
  → response()->streamDownload(fn() => print(file_get_contents($media->getPath())), $media->file_name)
  → setApiResponse(fn() => $streamedResponse)
  → return $this->response()   ← binary stream, never a public URL
```

### Update flow

```
[PUT /api/vehicles/{vehicle}/documents/{document}]
  → Authenticate (auth:sanctum)
  → Authorize (DocumentPolicy::update → document owner check)
  → Validate (UpdateDocumentRequest — all fields optional)
  → Abort 404 if document does not belong to vehicle
  → DB::beginTransaction()
    → Document::update(only provided fields from [type, expiry_date])
    → If document_file present:
        → $document->clearMediaCollection('vehicle_documents')  ← removes old file
        → $document->addMediaFromRequest('document_file')
                    ->toMediaCollection('vehicle_documents')     ← stores new file
  → DB::commit() → DocumentResource → 200 OK
  → DB::rollBack() on any failure → 422 / 500
```

### Delete flow

```
[DELETE /api/vehicles/{vehicle}/documents/{document}]
  → Authenticate (auth:sanctum)
  → Authorize (DocumentPolicy::delete → document owner check)
  → Abort 404 if document does not belong to vehicle
  → $documentRepository->delete($document->id)
      → findOrFail($id) + $record->delete()   ← Spatie observer auto-deletes associated media files
  → 200 OK
```

---

## Required Migrations

1. `create_documents_table` — new migration for this feature
2. `create_media_table` — Spatie migration (publish via `php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"`)
