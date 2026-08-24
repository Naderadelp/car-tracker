# API Contracts: Document Vault

**Feature**: 002-document-vault
**Date**: 2026-05-01
**Base URL**: `/api/vehicles/{vehicle}`
**Authentication**: All endpoints require `Authorization: Bearer {token}` (Sanctum)

---

## GET /api/vehicles/{vehicle}/documents

**Access**: Authenticated vehicle owner
**Purpose**: List all documents for a specific vehicle, paginated. Ordered by expiry date ascending (soonest expiring first; documents with no expiry date appear last).

**Query parameters** (all optional):
| Parameter | Description | Example |
|-----------|-------------|---------|
| `filter[type]` | Filter by document type (exact match) | `?filter[type]=insurance_policy` |
| `sort` | Sort field — `created_at` or `-created_at` | `?sort=-created_at` |
| `page` | Page number | `?page=2` |
| `per_page` | Results per page (default 15) | `?per_page=10` |

### Response — 200 OK

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "vehicle_id": 3,
      "type": "insurance_policy",
      "expiry_date": "2026-12-31",
      "has_file": true,
      "created_at": "2026-05-01T10:00:00.000000Z",
      "updated_at": "2026-05-01T10:00:00.000000Z"
    },
    {
      "id": 2,
      "vehicle_id": 3,
      "type": "vehicle_license",
      "expiry_date": "2027-06-15",
      "has_file": true,
      "created_at": "2026-05-01T11:00:00.000000Z",
      "updated_at": "2026-05-01T11:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 2,
    "last_page": 1
  }
}
```

### Response — 403 Forbidden (user does not own vehicle)

```json
{
  "message": "This action is unauthorized."
}
```

---

## POST /api/vehicles/{vehicle}/documents

**Access**: Authenticated vehicle owner
**Purpose**: Upload a new document file linked to the vehicle. Replaces any existing file for the same document record.
**Content-Type**: `multipart/form-data`

### Request

| Field           | Type   | Rules                                                                                    |
|-----------------|--------|------------------------------------------------------------------------------------------|
| `type`          | string | required — one of: `vehicle_license`, `insurance_policy`, `registration`, `inspection_certificate`, `driver_license`, `finance_contract` |
| `expiry_date`   | string | nullable, date format `YYYY-MM-DD`, must be after today                                 |
| `document_file` | file   | required, mimes: pdf, jpg, jpeg, png, max 5120 KB (5 MB)                                |

### Response — 201 Created

```json
{
  "data": {
    "id": 1,
    "vehicle_id": 3,
    "type": "insurance_policy",
    "expiry_date": "2026-12-31",
    "has_file": true,
    "created_at": "2026-05-01T10:00:00.000000Z",
    "updated_at": "2026-05-01T10:00:00.000000Z"
  },
  "message": "Document uploaded successfully."
}
```

### Response — 403 Forbidden (user does not own vehicle)

```json
{
  "message": "This action is unauthorized."
}
```

### Response — 422 Unprocessable Entity (validation failure)

```json
{
  "message": "The type field is required. (and 1 more error)",
  "errors": {
    "type": ["The type field is required."],
    "document_file": ["The document file must not be greater than 5120 kilobytes."]
  }
}
```

---

## GET /api/vehicles/{vehicle}/documents/{document}/file

**Access**: Authenticated owner of both the vehicle and the document
**Purpose**: Stream the document file directly to the client. Returns the raw file binary — no public URL is generated at any point.

### Response — 200 OK

Binary file stream with headers:

```
Content-Type: application/pdf   (or image/jpeg, image/png)
Content-Disposition: attachment; filename="insurance_policy.pdf"
```

The response body is the raw file content. The mobile client should save or render it directly.

### Response — 403 Forbidden (user does not own document or vehicle)

```json
{
  "message": "This action is unauthorized."
}
```

### Response — 404 Not Found (no file attached or document-vehicle mismatch)

```json
{
  "message": "No media file found for this document."
}
```

---

## PUT /api/vehicles/{vehicle}/documents/{document}

**Access**: Authenticated owner of the document
**Purpose**: Update a document's type, expiry date, or replace its file. All fields are optional — only provided fields are updated. Replacing the file explicitly clears the existing file before storing the new one.
**Content-Type**: `multipart/form-data`

### Request

| Field           | Type   | Rules                                                                                    |
|-----------------|--------|------------------------------------------------------------------------------------------|
| `type`          | string | optional — one of: `vehicle_license`, `insurance_policy`, `registration`, `inspection_certificate`, `driver_license`, `finance_contract` |
| `expiry_date`   | string | optional, nullable, date format `YYYY-MM-DD`, must be after today                      |
| `document_file` | file   | optional, mimes: pdf, jpg, jpeg, png, max 5120 KB (5 MB)                                |

### Response — 200 OK

```json
{
  "data": {
    "id": 1,
    "vehicle_id": 3,
    "type": "registration",
    "expiry_date": "2027-03-15",
    "has_file": true,
    "created_at": "2026-05-01T10:00:00.000000Z",
    "updated_at": "2026-05-01T12:00:00.000000Z"
  },
  "message": "Document updated successfully."
}
```

### Response — 403 Forbidden (user does not own document)

```json
{
  "message": "This action is unauthorized."
}
```

### Response — 404 Not Found (document does not belong to vehicle)

```json
{
  "message": "Not Found."
}
```

### Response — 422 Unprocessable Entity (validation failure)

```json
{
  "message": "The expiry date must be a date after today.",
  "errors": {
    "expiry_date": ["The expiry date must be a date after today."]
  }
}
```

---

## DELETE /api/vehicles/{vehicle}/documents/{document}

**Access**: Authenticated owner of the document
**Purpose**: Permanently delete the document record and its associated file. This operation is irreversible.

### Response — 200 OK

```json
{
  "message": "Document deleted successfully."
}
```

### Response — 403 Forbidden (user does not own document)

```json
{
  "message": "This action is unauthorized."
}
```

---

## Document Type Reference

| API Value                | Display Label              |
|--------------------------|----------------------------|
| `vehicle_license`        | Vehicle License            |
| `insurance_policy`       | Insurance Policy           |
| `registration`           | Registration               |
| `inspection_certificate` | Inspection Certificate     |
| `driver_license`         | Driver License             |
| `finance_contract`       | Finance Contract           |
