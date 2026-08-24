# Research: Document Vault

**Feature**: 002-document-vault
**Date**: 2026-05-01
**Phase**: 0 — Outline & Research

---

## R-001: Private Disk Storage with spatie/laravel-medialibrary

**Decision**: Use `->useDisk('local')` on the media collection definition. The `local` disk in Laravel maps to `storage/app` and has no publicly accessible URL by design. Never use `->useDisk('public')` for document files.

**Rationale**: The `public` disk writes files under `storage/app/public` and they are symlinked to `public/storage`, making them directly accessible via HTTP. The `local` disk has no such symlink and is unreachable without explicit application routing. This is the correct choice for private document storage.

**Implementation pattern**:
```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('vehicle_documents')
         ->singleFile()
         ->useDisk('local');
}
```

**Alternatives considered**:
- S3 with pre-signed URLs: not available in the current stack (no AWS credentials configured); out of scope for Phase 1
- Custom encrypted storage: unnecessary overhead when the local disk already prevents public access

---

## R-002: Streaming Files from the Private Disk

**Decision**: Use `response()->streamDownload()` wrapping a closure that reads the file content from the media model's local path. The media model provides `$media->getPath()` which returns the absolute filesystem path on the local disk.

**Rationale**: `response()->file($path)` (X-Sendfile / direct file response) is simpler but requires the web server to have read access to `storage/app` which varies by deployment. `streamDownload` with a closure is portable and works on all environments without server-level configuration.

**Implementation pattern**:
```php
$media = $document->getFirstMedia('vehicle_documents');
$streamedResponse = response()->streamDownload(
    fn () => print(file_get_contents($media->getPath())),
    $media->file_name,
    ['Content-Type' => $media->mime_type]
);
$this->setApiResponse(fn () => $streamedResponse);
return $this->response();
```

**Responder trait compliance**: Using `setApiResponse(fn () => $streamedResponse)` then `return $this->response()` satisfies Principle III while returning a binary file stream. This is the correct override mechanism documented in the constitution.

**Alternatives considered**:
- Return a temporary signed URL: impossible with the local disk (no URL generation)
- `Storage::disk('local')->download($path)`: equivalent but less control over headers

---

## R-003: Nested Route Model Binding and Cross-Binding Validation

**Decision**: Use Laravel's implicit route model binding for both `{vehicle}` and `{document}`. Add explicit cross-binding validation in each controller action to ensure the `$document` belongs to the `$vehicle` in the URL, not just to the authenticated user.

**Rationale**: Without cross-binding validation, a user could craft a request to `/vehicles/1/documents/99` where document 99 belongs to vehicle 2 but the same user. Laravel's default binding does not enforce this relationship automatically.

**Validation pattern per action**:
```
// index, store:    abort_if($vehicle->user_id !== auth()->id(), 403)
// show, destroy:   abort_if($document->vehicle_id !== $vehicle->id, 404)
//                  abort_if($document->user_id !== auth()->id(), 403)
```

**Alternatives considered**:
- Scope binding via `Route::scopeBindings()`: automatically scopes `{document}` to `{vehicle}` but requires `documents` to be defined as a relationship on `Vehicle`; valid but less explicit about the 403 vs 404 distinction
- Policy-only authorization: DocumentPolicy handles ownership; cross-binding is still needed because the policy alone does not validate the vehicle-document association

---

## R-004: Document Type Enum — Database vs Application Level

**Decision**: Use a database-level MySQL `ENUM` column for `type`. Define the allowed values array as a constant on the `Document` model and reference it from both the migration and the `StoreDocumentRequest` validation rule (`Rule::in(Document::TYPES)`).

**Rationale**: A database-level enum enforces integrity at the storage layer. Defining the values as a model constant (single source of truth) prevents drift between the migration and the validation rule.

**Allowed values**: `vehicle_license`, `insurance_policy`, `registration`, `inspection_certificate`, `driver_license`, `finance_contract`

**Alternatives considered**:
- Separate `document_types` lookup table: unnecessary overhead for a fixed, small enum set that will not change frequently
- Application-only string with check constraint: MySQL `ENUM` is cleaner and enforces at DB level without a separate migration for a check constraint

---

## R-005: Transaction Scope for Store Operation

**Decision**: Wrap both `Document::create()` and `$document->addMediaFromRequest('document_file')->toMediaCollection('vehicle_documents')` inside a single `DB::beginTransaction()` block with rollback on failure.

**Rationale**: If the document record is created but the media attachment fails (disk write error, mime validation failure inside Spatie), the orphaned database record must be rolled back. Spatie's `addMediaFromRequest()` throws on failure, so the `catch` block and `DB::rollBack()` will correctly clean up.

**Note**: Spatie does not automatically roll back physical files on transaction rollback. If the file is written to disk before the exception, it remains. This is acceptable for Phase 1 (the file is unreferenced without the media record). A cleanup job can be scheduled in a future phase if needed.

**Alternatives considered**:
- No transaction: creates orphaned Document records on media failure — violates Principle VI
- Queued media processing: adds complexity not required at this scale

---

## R-006: DocumentPolicy Structure

**Decision**: Implement `DocumentPolicy` with `before()` (super-admin bypass via `isSuperAdmin()`), `viewAny()` (vehicle owner check), `create()` (vehicle owner check), `view()` (document owner check), and `delete()` (document owner check). Register via `DocumentServiceProvider`.

**Permission names** (per constitution kebab-case convention):
- `index-document`
- `create-document`
- `view-document`
- `delete-document`

**Rationale**: Follows Principle V exactly. The `before()` method ensures super-admin access is never blocked by ownership checks.

**Alternatives considered**:
- Inline `abort_if()` only: simpler but violates Principle V which requires `$this->authorize()` before data access
