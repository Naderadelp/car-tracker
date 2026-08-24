# Feature Specification: Document Vault

**Feature Branch**: `002-document-vault`
**Created**: 2026-05-01
**Status**: Draft
**Input**: User description: CarLog Document Vault — secure storage, retrieval, and management of vehicle-related paperwork

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Upload a Vehicle Document (Priority: P1)

A vehicle owner uploads a document file (such as an insurance policy or vehicle registration) and links it to one of their registered vehicles. They select the document type, optionally enter an expiry date, and attach the file. The system stores the file privately and confirms the upload.

**Why this priority**: Uploading is the foundational action — without it, no document exists to view, download, or manage. All other stories depend on this working correctly.

**Independent Test**: Can be fully tested by uploading a valid PDF for a vehicle the authenticated user owns and verifying the document record is created with the correct type and expiry date.

**Acceptance Scenarios**:

1. **Given** an authenticated user who owns a vehicle, **When** they upload a valid PDF file with a supported document type and a future expiry date, **Then** the system creates the document record, stores the file privately, and returns a success confirmation.
2. **Given** an authenticated user, **When** they attempt to upload a document for a vehicle they do not own, **Then** the system rejects the request with an authorization error.
3. **Given** an authenticated user, **When** they upload a file exceeding 5MB or with an unsupported format (e.g., `.docx`, `.exe`), **Then** the system rejects the upload and returns a clear validation error.
4. **Given** an authenticated user, **When** they provide an expiry date in the past, **Then** the system rejects the request with a validation error.

---

### User Story 2 — Securely Download a Document File (Priority: P1)

A vehicle owner retrieves the actual file for one of their documents. The file is streamed directly to their device through an authenticated endpoint. No public or shareable URL is generated.

**Why this priority**: The core value of storing documents is being able to retrieve them. Secure access is a non-negotiable safety requirement — document files (insurance, finance contracts) are sensitive.

**Independent Test**: Can be fully tested by uploading a document then requesting its file via the authenticated download endpoint and verifying the correct file is returned and that accessing without authentication (or as a different user) is denied.

**Acceptance Scenarios**:

1. **Given** an authenticated user who owns a document, **When** they request the document file, **Then** the system streams the file content directly to them without exposing a public URL.
2. **Given** an authenticated user who does not own a document, **When** they request that document's file, **Then** the system returns a 403 Forbidden response.
3. **Given** an unauthenticated request, **When** a document file URL is accessed, **Then** the system returns a 401 Unauthorized response.

---

### User Story 3 — List Vehicle Documents (Priority: P2)

A vehicle owner views all documents associated with one of their vehicles. Documents are displayed ordered by expiry date so that the soonest-expiring documents appear first.

**Why this priority**: Listing enables the user to audit their document vault and act on upcoming expirations. It is dependent on documents existing (P1) but delivers standalone value as a management view.

**Independent Test**: Can be fully tested by creating multiple documents with different expiry dates for a vehicle and verifying the list returns them in the correct order and only for the authenticated owner.

**Acceptance Scenarios**:

1. **Given** an authenticated user with documents on a vehicle, **When** they request the document list for that vehicle, **Then** the system returns all documents ordered by expiry date, soonest first.
2. **Given** an authenticated user, **When** they request documents for a vehicle they do not own, **Then** the system returns a 403 Forbidden response.
3. **Given** a vehicle with no documents, **When** the owner requests the document list, **Then** the system returns an empty list.

---

### User Story 4 — Delete a Document (Priority: P3)

A vehicle owner permanently deletes a document and its associated file from their vault.

**Why this priority**: Deletion is a housekeeping action. The vault still delivers value without it, making it lower priority than upload and retrieval.

**Independent Test**: Can be fully tested by uploading a document, deleting it, then verifying the record no longer exists and the file is no longer retrievable.

**Acceptance Scenarios**:

1. **Given** an authenticated user who owns a document, **When** they request deletion, **Then** the system permanently removes both the document record and its associated file, returning a success response.
2. **Given** an authenticated user who does not own a document, **When** they request deletion of that document, **Then** the system returns a 403 Forbidden response.

---

### User Story 5 — Update a Document (Priority: P3)

A vehicle owner updates an existing document — changing its type, expiry date, or replacing the attached file. All fields are optional; only the fields provided are changed. Replacing the file removes the previous file before storing the new one.

**Why this priority**: Update completes the full lifecycle. The vault is functional without it (documents can be deleted and re-uploaded), but update provides a faster path for correcting mistakes or renewing expiring documents.

**Independent Test**: Can be fully tested by creating a document, sending a partial update (type only), verifying only that field changes, then sending a file replacement and verifying the new file is served while the old one is gone.

**Acceptance Scenarios**:

1. **Given** an authenticated user who owns a document, **When** they send an update with only a new expiry date, **Then** the system updates only the expiry date and leaves all other fields unchanged.
2. **Given** an authenticated user who owns a document, **When** they send an update with a new file, **Then** the system removes the existing file, stores the new file privately, and returns the updated document with `has_file: true`.
3. **Given** an authenticated user who does not own a document, **When** they attempt to update it, **Then** the system returns a 403 Forbidden response.
4. **Given** an authenticated user, **When** they send an update with an expiry date in the past, **Then** the system rejects the request with a validation error.

---

### Edge Cases

- What happens when a user tries to upload a document for a vehicle belonging to another user? The system rejects with 403 before any file processing occurs.
- What happens when a document type not in the approved list is submitted? The system returns a validation error listing valid types.
- What happens when a file is both an unsupported type AND oversized? The system returns both validation errors simultaneously.
- What happens when a document with no expiry date is uploaded? The system accepts it; expiry date is optional.
- What happens when the document record is deleted but the file deletion fails? The system must ensure both are removed atomically; orphaned files are treated as a bug.
- What happens when an authenticated user requests a document file that belongs to a different vehicle than the one in the URL? The system validates both vehicle ownership and document-vehicle association before serving the file.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow an authenticated vehicle owner to upload a document file linked to one of the six supported document types for a vehicle they own.
- **FR-002**: System MUST reject any upload request where the authenticated user does not own the target vehicle.
- **FR-003**: System MUST restrict uploaded files to PDF, JPG, and PNG formats; all other formats MUST be rejected.
- **FR-004**: System MUST reject any uploaded file larger than 5 MB.
- **FR-005**: Each document record MUST hold exactly one file; uploading a new file for the same document replaces the previous file with no duplicates retained.
- **FR-006**: System MUST store all document files in a private storage location that produces no publicly accessible or guessable URL.
- **FR-007**: System MUST provide an authenticated endpoint that streams a document file directly to the requesting owner.
- **FR-008**: System MUST deny file retrieval to any user who does not own the document, returning a 403 response.
- **FR-009**: System MUST allow an authenticated vehicle owner to list all documents for their vehicle, ordered by expiry date ascending (soonest expiring first), with non-expiring documents listed last.
- **FR-010**: System MUST allow an authenticated user to permanently delete a document they own, removing both the record and its associated file in a single operation.
- **FR-011**: When an expiry date is provided, system MUST reject dates that are not in the future at the time of submission.
- **FR-012**: Document type MUST be one of exactly six values: Vehicle License, Insurance Policy, Registration, Inspection Certificate, Driver License, Finance Contract.
- **FR-013**: System MUST allow an authenticated document owner to update any combination of type, expiry date, or file in a single request; fields not included in the request MUST remain unchanged.
- **FR-014**: When a file replacement is submitted during an update, system MUST remove the existing file before storing the new one — no duplicate files may exist for the same document.

### Key Entities

- **Document**: Represents a single vehicle-related paperwork record. Key attributes: type (one of six fixed categories), expiry date (optional, must be future), ownership references. Each document belongs to one User and one Vehicle and holds exactly one file.
- **Document File**: The binary file attached to a Document (PDF, JPG, or PNG, max 5 MB). Stored privately; accessible only via authenticated streaming endpoint. Permanently deleted when the parent Document is deleted.
- **Vehicle**: The vehicle the document is associated with. A vehicle may have many documents.
- **User**: The authenticated account holder. A user may own many vehicles and, through vehicles, many documents. Ownership is the sole authorization gate for all document operations.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can upload a valid document and receive confirmation in under 10 seconds under typical mobile network conditions.
- **SC-002**: 100% of document file access attempts by non-owners are denied — no file is ever served to a user who does not own it.
- **SC-003**: Zero document files are accessible via a direct, public, or unauthenticated URL at any point in the system's lifecycle.
- **SC-004**: Users can retrieve their full document list for a vehicle in under 2 seconds.
- **SC-005**: After deletion, 0% of deleted document files remain in storage — no orphaned files.
- **SC-006**: 100% of uploads violating type or size constraints are rejected with a descriptive error message before any storage operation occurs.
- **SC-007**: After a file replacement update, 0% of previous document files remain in storage — no orphaned files from replaced uploads.

## Assumptions

- Only the vehicle owner can upload, view, download, and delete documents for their vehicle; no shared access, delegation, or admin review workflow is in scope for this phase.
- Document status management (e.g., marking a document as verified or rejected by a third party) is explicitly out of scope and will be addressed in a future phase.
- Each document type is fixed at six values as specified; no user-defined or custom document types are supported in this phase.
- Expiry date is optional; documents without an expiry date are valid and treated as non-expiring.
- The system serves a single authenticated mobile client; no browser-based file preview or UI is required.
- A document may only be associated with one vehicle; cross-vehicle document sharing is out of scope.
- There is no soft-delete requirement for documents; deletion is permanent and immediate.
