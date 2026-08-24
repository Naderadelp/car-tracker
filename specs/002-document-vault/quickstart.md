# Quickstart: Document Vault

**Feature**: 002-document-vault
**Date**: 2026-05-01

A step-by-step guide to verify the Document Vault module works end-to-end.

---

## Prerequisites

- Laravel app running locally (`php artisan serve` on `http://localhost:8000`)
- Database migrated: `php artisan migrate`
- spatie/laravel-medialibrary installed and its migration published + run
- Storage symlink NOT required (all files are on the private `local` disk)
- A registered user + vehicle from the identity-auth module (you need a `token` and `vehicle_id`)

---

## Step 1 — Upload a document

```bash
curl -X POST http://localhost:8000/api/vehicles/1/documents \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "type=insurance_policy" \
  -F "expiry_date=2027-06-30" \
  -F "document_file=@/path/to/insurance.pdf"
```

**Expected**: HTTP 201, response contains the document record with `has_file: true`.
Copy the `id` value for subsequent requests.

**Verify private storage**: After upload, confirm the file does NOT exist under `public/storage/`.
It should be under `storage/app/` only.

---

## Step 2 — List documents for a vehicle

```bash
curl -X GET http://localhost:8000/api/vehicles/1/documents \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Expected**: HTTP 200, array of documents ordered by `expiry_date` ascending.

---

## Step 3 — Secure file download

```bash
curl -X GET http://localhost:8000/api/vehicles/1/documents/1/file \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output downloaded_document.pdf
```

**Expected**: HTTP 200, file saved to `downloaded_document.pdf`.

**Verify access control**: Repeat the request without the `Authorization` header.
Expected: HTTP 401.

**Verify ownership**: Register a second user and attempt to download the same document.
Expected: HTTP 403.

---

## Step 4 — Attempt invalid upload (wrong owner)

Register a second user, get their token, then try to upload to user 1's vehicle:

```bash
curl -X POST http://localhost:8000/api/vehicles/1/documents \
  -H "Authorization: Bearer SECOND_USER_TOKEN" \
  -H "Accept: application/json" \
  -F "type=registration" \
  -F "document_file=@/path/to/doc.pdf"
```

**Expected**: HTTP 403 Forbidden.

---

## Step 5 — Attempt invalid file type

```bash
curl -X POST http://localhost:8000/api/vehicles/1/documents \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "type=registration" \
  -F "document_file=@/path/to/document.docx"
```

**Expected**: HTTP 422 with `errors.document_file` validation message.

---

## Step 6 — Update a document (change type and replace file)

```bash
curl -X PUT http://localhost:8000/api/vehicles/1/documents/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "type=registration" \
  -F "expiry_date=2028-01-01" \
  -F "document_file=@/path/to/new_registration.pdf"
```

**Expected**: HTTP 200, response contains the updated document with the new type and `has_file: true`.

**Verify old file is gone**: Check `storage/app/private/` — the previous file should no longer exist.

**Partial update (fields only, no file)**:

```bash
curl -X PUT http://localhost:8000/api/vehicles/1/documents/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -F "expiry_date=2029-06-30"
```

**Expected**: HTTP 200, only `expiry_date` changes; file and type remain unchanged.

---

## Step 7 — Delete a document

```bash
curl -X DELETE http://localhost:8000/api/vehicles/1/documents/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Expected**: HTTP 200 with deletion confirmation.

**Verify file cleanup**: Check that the file no longer exists under `storage/app/`.

**Verify record gone**: Attempt to download the deleted document.
Expected: HTTP 404.

---

## Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| 500 on upload | Spatie migration not run | `php artisan vendor:publish --tag="medialibrary-migrations" && php artisan migrate` |
| File accessible via public URL | Wrong disk configured | Ensure `->useDisk('local')` on the collection, not `->useDisk('public')` |
| 404 on file download | No media attached (store failed silently) | Check `media` table; ensure transaction is committing |
| `Class DocumentServiceProvider not found` | Provider not registered | Add `DocumentServiceProvider::class` to `bootstrap/providers.php` |
