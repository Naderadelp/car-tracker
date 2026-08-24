# Tasks: Document Vault

**Input**: Design documents from `specs/002-document-vault/`
**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/document-api.md ✅ quickstart.md ✅

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: User story label — [US1] Upload, [US2] Secure Download, [US3] List, [US4] Delete

## Path Conventions

All source code under `app/` (standard Laravel MVC — codebase migrated from DDD in a prior commit).

---

## Phase 1: Setup (Spatie Media Library)

**Purpose**: Install and configure spatie/laravel-medialibrary before any domain code can use it.

- [x] T001 Install `spatie/laravel-medialibrary` via composer: `composer require spatie/laravel-medialibrary`
- [x] T002 Publish Spatie medialibrary migrations: `php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"`
- [x] T003 Run all pending migrations: `php artisan migrate`
- [x] T004 [P] Confirm `local` disk entry exists in `config/filesystems.php` (default Laravel config already includes it — verify `root` points to `storage_path('app')`)
- [x] T005 [P] Add document routes to `routes/api.php` under `/api/vehicles/{vehicle}/documents`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core domain infrastructure that MUST be complete before ANY user story can be implemented.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T006 [P] Create documents table migration in `database/migrations/` (columns: id, user_id FK→users cascade, vehicle_id FK→vehicles cascade, type enum['vehicle_license','insurance_policy','registration','inspection_certificate','driver_license','finance_contract'], expiry_date date nullable, timestamps)
- [x] T007 [P] Create `Document` model in `app/Models/Document.php` — implement `HasMedia`, use `InteractsWithMedia` and `LogsActivity`; define `const TYPES = [...]` matching migration enum; `$fillable`: user_id, vehicle_id, type, expiry_date; cast expiry_date→date; implement `registerMediaCollections()` creating collection `vehicle_documents` with `->singleFile()->useDisk('local')`; implement `getActivitylogOptions()`
- [x] T008 [P] Create `DocumentRelations` trait in `app/Models/Traits/DocumentRelations.php` — `belongsTo(User::class)`, `belongsTo(Vehicle::class)`; add `use DocumentRelations` to `Document` model
- [x] T009 [P] Create `DocumentRepository` contract interface in `app/Repositories/Contracts/DocumentRepository.php` (extend base `RepositoryInterface`; no additional methods needed for Phase 1)
- [x] T010 Create `DocumentRepositoryEloquent` in `app/Repositories/Eloquent/DocumentRepositoryEloquent.php` (extend `EloquentRepository`, implement `DocumentRepository`; `model()` returns `Document::class`) — depends on T007, T009
- [x] T011 [P] Create `DocumentResource` in `app/Http/Resources/DocumentResource.php` (fields: id, vehicle_id, type, expiry_date→toDateString() or null, has_file as boolean from `$this->hasMedia('vehicle_documents')`, created_at→toISOString(), updated_at→toISOString())
- [x] T012 Create `DocumentServiceProvider` in `app/Providers/DocumentServiceProvider.php` — bind `DocumentRepository::class` → `DocumentRepositoryEloquent::class` in `register()`; depends on T009, T010
- [x] T013 Register `DocumentServiceProvider::class` in `bootstrap/providers.php` — depends on T012
- [x] T014 [P] Create `DocumentPolicy` skeleton in `app/Policies/DocumentPolicy.php` — include `before(User $user, string $ability): ?bool` returning true for super-admin via `$user->isSuperAdmin()`; stub `viewAny`, `create`, `view`, `delete` methods (return false by default for now)
- [x] T015 Register `DocumentPolicy` for `Document` model in `app/Providers/AppServiceProvider.php` boot() via `Gate::policy(Document::class, DocumentPolicy::class)` — depends on T014
- [x] T016 Run `php artisan migrate` to apply the documents table migration — depends on T006

**Checkpoint**: Foundation ready — migrate confirmed, model/repository/resource/policy skeleton all in place. User story implementation can now begin.

---

## Phase 3: User Story 1 — Upload a Vehicle Document (Priority: P1) 🎯 MVP

**Goal**: Authenticated vehicle owner uploads a document file (PDF/JPG/PNG, max 5 MB) linked to their vehicle by type. File stored on private local disk. Returns 201 with DocumentResource.

**Independent Test**: Upload a valid PDF for an owned vehicle → verify 201 and `has_file: true`. Attempt upload to an unowned vehicle → verify 403. Upload `.docx` → verify 422.

- [x] T017 [P] [US1] Create `StoreDocumentRequest` in `app/Http/Requests/Document/StoreDocumentRequest.php` — `authorize()`: resolve `$vehicle` from route (`$this->route('vehicle')`), return `auth()->id() === $vehicle->user_id`; rules: type required string `Rule::in(Document::TYPES)`, expiry_date nullable date `after:today`, document_file required file mimes:pdf,jpg,jpeg,png max:5120
- [x] T018 [P] [US1] Implement `DocumentPolicy::create(User $user, Vehicle $vehicle)` in `app/Policies/DocumentPolicy.php` — return `$user->id === $vehicle->user_id`
- [x] T019 [US1] Create `DocumentController` in `app/Http/Controllers/Api/V1/DocumentController.php` — extend `BaseController`, use `Responder` trait; inject `DocumentRepository` via constructor; implement `store(StoreDocumentRequest $request, Vehicle $vehicle)`: call `$this->authorize('create', $vehicle)`, then `DB::beginTransaction()`, create document via repository forcing `user_id` to `auth()->id()`, call `$document->addMediaFromRequest('document_file')->toMediaCollection('vehicle_documents')`, `DB::commit()`, return `DocumentResource` with 201 status; catch and `DB::rollBack()` on failure — depends on T012, T017, T018
- [x] T020 [US1] Register `POST api/vehicles/{vehicle}/documents` route in `routes/api.php` pointing to `DocumentController@store` under `auth:sanctum` middleware — depends on T019

**Checkpoint**: User Story 1 fully functional. Upload works, ownership enforced, file on private disk.

---

## Phase 4: User Story 2 — Securely Download a Document File (Priority: P1)

**Goal**: Authenticated document owner retrieves the actual file via a streaming endpoint. No public URL generated. Non-owners receive 403.

**Independent Test**: Upload a document, call the file endpoint → verify binary stream returned with correct Content-Type. Call without auth → 401. Call as different user → 403.

- [x] T021 [P] [US2] Implement `DocumentPolicy::view(User $user, Document $document)` in `app/Policies/DocumentPolicy.php` — return `$user->id === $document->user_id`
- [x] T022 [US2] Implement `DocumentController::show(Request $request, Vehicle $vehicle, Document $document)` in `app/Http/Controllers/Api/V1/DocumentController.php` — call `$this->authorize('view', $document)`; abort 404 if `$document->vehicle_id !== $vehicle->id`; `$media = $document->getFirstMedia('vehicle_documents')`; abort 404 with message `'No media file found for this document.'` if null; build `$stream = response()->streamDownload(fn() => print(file_get_contents($media->getPath())), $media->file_name, ['Content-Type' => $media->mime_type])`; call `$this->setApiResponse(fn() => $stream)`; return `$this->response()` — depends on T021
- [x] T023 [US2] Register `GET api/vehicles/{vehicle}/documents/{document}/file` route in `routes/api.php` pointing to `DocumentController@show` under `auth:sanctum` middleware — depends on T022

**Checkpoint**: Secure download fully functional. File streamed privately. Ownership and cross-binding validated.

---

## Phase 5: User Story 3 — List Vehicle Documents (Priority: P2)

**Goal**: Authenticated vehicle owner sees all documents for their vehicle ordered by expiry date (soonest first, no-expiry last).

**Independent Test**: Create 3 documents with different expiry dates + 1 with no expiry → verify list returns them in correct order. Request list for unowned vehicle → 403. Empty vehicle → empty array.

- [x] T024 [P] [US3] Implement `DocumentPolicy::viewAny(User $user, Vehicle $vehicle)` in `app/Policies/DocumentPolicy.php` — return `$user->id === $vehicle->user_id`
- [x] T025 [US3] Implement `DocumentController::index(Request $request, Vehicle $vehicle)` in `app/Http/Controllers/Api/V1/DocumentController.php` — call `$this->authorize('viewAny', [Document::class, $vehicle])`; query documents via repository filtering by vehicle_id, ordered by `ISNULL(expiry_date) ASC, expiry_date ASC` (nulls last); return `DocumentResource::collection($documents)` — depends on T024
- [x] T026 [US3] Register `GET api/vehicles/{vehicle}/documents` route in `routes/api.php` pointing to `DocumentController@index` under `auth:sanctum` middleware — depends on T025

**Checkpoint**: List endpoint functional. Documents ordered correctly, ownership enforced, empty state returns `[]`.

---

## Phase 6: User Story 4 — Delete a Document (Priority: P3)

**Goal**: Authenticated document owner permanently removes the document record and its associated file. Spatie handles physical file deletion automatically on model delete.

**Independent Test**: Upload a document, delete it → verify 200. Confirm file no longer in `storage/app/`. Attempt download after delete → 404. Non-owner delete attempt → 403.

- [x] T027 [P] [US4] Implement `DocumentPolicy::delete(User $user, Document $document)` in `app/Policies/DocumentPolicy.php` — return `$user->id === $document->user_id`
- [x] T028 [US4] Implement `DocumentController::destroy(Request $request, Vehicle $vehicle, Document $document)` in `app/Http/Controllers/Api/V1/DocumentController.php` — call `$this->authorize('delete', $document)`; abort 404 if `$document->vehicle_id !== $vehicle->id`; call `$document->delete()` (Spatie observer auto-deletes media); return `$this->success([], 200, 'Document deleted successfully.')` — depends on T027
- [x] T029 [US4] Register `DELETE api/vehicles/{vehicle}/documents/{document}` route in `routes/api.php` pointing to `DocumentController@destroy` under `auth:sanctum` middleware — depends on T028

**Checkpoint**: All 4 user stories functional. Full CRUD implemented and ownership-gated at every endpoint.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Final hardening and verification across all user stories.

- [ ] T030 [P] Run `php artisan sync:permissions` to register `index-document`, `create-document`, `view-document`, `delete-document` permissions from `DocumentPolicy`
- [ ] T031 [P] Verify no document file path is accessible under `public/` or via `Storage::disk('public')` — manual check and automated assertion
- [ ] T032 Run quickstart.md end-to-end validation: upload → list → download → delete in sequence, including all negative test cases (wrong owner, wrong file type, missing auth)
- [ ] T033 [P] Update Postman collection to add all 4 Document Vault endpoints with `multipart/form-data` examples for upload

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — **BLOCKS all user stories**
- **User Story phases (3–6)**: All depend on Phase 2 completion; can proceed in parallel across stories once Phase 2 is done
- **Polish (Phase 7)**: Depends on all desired user stories complete

### User Story Dependencies

- **US1 Upload (P1)**: Can start after Phase 2 — no dependency on other stories
- **US2 Secure Download (P1)**: Can start after Phase 2 — no dependency on US1 (needs the controller file, reuses foundation)
- **US3 List (P2)**: Can start after Phase 2 — independent of US1 and US2
- **US4 Delete (P3)**: Can start after Phase 2 — independent of all other stories

### Within Each User Story

- Policy method → then controller method → then route registration
- StoreDocumentRequest (T017) can be written in parallel with policy (T018) before controller (T019)

---

## Parallel Opportunities

### Phase 2 — Run together

```
T006 Migration
T007 Document model
T008 DocumentRelations trait
T009 DocumentRepository contract
T011 DocumentResource
T014 DocumentPolicy skeleton
```

### Phase 3 — Run T017 and T018 together, then T019

```
T017 StoreDocumentRequest  ─┐
T018 DocumentPolicy::create ─┤→ T019 Controller store() → T020 Route
```

### Phase 4 — Run T021 first, then T022

```
T021 DocumentPolicy::view → T022 Controller show() → T023 Route
```

---

## Implementation Strategy

### MVP First (US1 + US2 — both P1)

1. Complete Phase 1: Setup (Spatie install + migrate)
2. Complete Phase 2: Foundational (CRITICAL — blocks everything)
3. Complete Phase 3: US1 Upload → test independently
4. Complete Phase 4: US2 Secure Download → test independently
5. **STOP and VALIDATE**: Run quickstart Steps 1–4 (upload + download + ownership checks)
6. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → run `php artisan migrate` → foundation confirmed
2. US1 Upload → verify 201 + private disk storage → MVP upload
3. US2 Secure Download → verify stream + 403 protection → private vault working
4. US3 List → verify ordering + ownership → vault browseable
5. US4 Delete → verify cleanup + 403 protection → full lifecycle complete

---

## Notes

- `[P]` = different files, no incomplete dependencies — safe to parallelize
- `[US1]`–`[US4]` map directly to spec.md user stories
- Each story phase ends with a named Checkpoint — stop and validate before continuing
- The `DocumentController` file is created in T019 (US1); subsequent stories ADD methods to the same file — do not create new controller files
- The `DocumentPolicy` file is created in T014 (skeleton); each story phase fills in one method — coordinate if working in parallel to avoid merge conflicts on the same file
- Spatie auto-deletes physical files when the parent Eloquent model is deleted (via registered observer) — no manual file cleanup needed in `destroy()`
- `ISNULL(expiry_date) ASC, expiry_date ASC` is the MySQL-compatible way to sort NULLs last
