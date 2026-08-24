# Implementation Plan: Document Vault

**Branch**: `002-document-vault` | **Date**: 2026-05-01 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/002-document-vault/spec.md`

## Summary

Implement the Document Vault module for CarLog — a mobile-first, API-only Laravel application.
The module covers secure upload, private storage, authenticated streaming download, listing, and
permanent deletion of vehicle-linked documents. All files are stored on the private local disk
and served only via an ownership-gated authenticated endpoint; no public URL is ever generated.
Authentication is stateless via Laravel Sanctum tokens. Validation is handled by a dedicated
Form Request. Responses use the Responder trait via a DocumentResource class. The store
operation wraps document creation and media attachment in a single database transaction.

## Technical Context

**Language/Version**: PHP 8.4
**Primary Dependencies**: Laravel 13, Laravel Sanctum, spatie/laravel-medialibrary, Spatie Laravel-Activity-Log
**Storage**: MySQL — `documents`, `media` (Spatie-managed); local filesystem for private file blobs
**Testing**: PHPUnit (Laravel default test suite)
**Target Platform**: Linux server — headless JSON API consumed by a mobile app
**Project Type**: Web service (API-only backend)
**Performance Goals**: Document list response under 2 seconds; upload confirmation under 10 seconds under mobile network conditions
**Constraints**: Stateless API (no sessions/cookies); all document files stored on private local disk only; ownership-gated at every endpoint; single file per document (singleFile collection)
**Scale/Scope**: Single-tenant; Phase 1 scoped to 5 endpoints (index, store, show-file, update, destroy) nested under `/api/vehicles/{vehicle}/documents`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design — all pass.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Repository Pattern | ✅ PASS | `DocumentRepository` contract + `DocumentRepositoryEloquent` implementation required; controller injects via constructor |
| II. Form Request Validation | ✅ PASS | `StoreDocumentRequest` required; no inline validation permitted |
| III. Responder Trait | ⚠️ JUSTIFIED DEVIATION (show) | Standard JSON actions (index, store, update, destroy) use Responder normally. The `show` (secure download) action returns `StreamedResponse` directly — `Responder::response()` is typed as `JsonResponse`, so routing a binary stream through it would throw a `TypeError`. Returning `StreamedResponse` directly from the controller method is the only safe path. See Complexity Tracking. |
| IV. Domain-Driven Folder Structure | ⚠️ JUSTIFIED DEVIATION | Codebase was fully migrated from `src/Domain/` to standard Laravel `app/` structure in a prior commit. This feature follows the established `app/` layout for consistency. See Complexity Tracking. |
| V. Authorization via Policies | ✅ PASS | `DocumentPolicy` required; `before()` returns `true` for `admin` role via `$user->isAdmin()`; `authorize()` called before every data operation |
| VI. Transactional Writes & Observability | ✅ PASS | `store()` wraps `Document::create()` + `addMediaFromRequest()` in `DB::beginTransaction()`; `Document` uses `LogsActivity` and `HasMedia` |

## Project Structure

### Documentation (this feature)

```text
specs/002-document-vault/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── document-api.md  # Phase 1 output — all 4 endpoint contracts
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── DocumentController.php
│   ├── Requests/
│   │   └── Document/
│   │       ├── StoreDocumentRequest.php
│   │       └── UpdateDocumentRequest.php
│   └── Resources/
│       └── DocumentResource.php
├── Models/
│   ├── Document.php
│   └── Traits/
│       └── DocumentRelations.php
├── Policies/
│   └── DocumentPolicy.php
├── Repositories/
│   ├── Contracts/
│   │   └── DocumentRepository.php
│   └── Eloquent/
│       └── DocumentRepositoryEloquent.php
└── Providers/
    └── RepositoryServiceProvider.php   # DocumentRepository binding added here

database/migrations/
└── xxxx_xx_xx_create_documents_table.php

routes/api.php   # vehicles/{vehicle}/documents route group
```

**Structure Decision**: Standard Laravel `app/` layout, consistent with the existing codebase
(post-migration from DDD). No API versioning per constitution — controller is at
`App\Http\Controllers\DocumentController`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| `show` returns `StreamedResponse` directly | `show` must stream a binary file; `Responder::response()` is typed `JsonResponse` so passing a `StreamedResponse` through it throws a `TypeError` | Returning a public URL would violate the private-disk security requirement; there is no safe way to route a binary stream through the Responder |
| `app/` instead of `src/Domain/` | Codebase was already fully migrated from DDD `src/Domain/` to `app/` in a prior commit | Re-introducing `src/Domain/` for one module would create two competing conventions in the same codebase |
