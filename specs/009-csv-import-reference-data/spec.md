# Feature Specification: CSV Import for Reference Data

**Feature Branch**: `009-csv-import-reference-data`
**Created**: 2026-08-18
**Status**: Draft
**Input**: User description: "CSV import for reference data resources in the Filament admin panel"

## Context

The admin panel (feature 008 follow-on) exposes fourteen resources, but every row must
currently be typed in one at a time. The catalogue tables are the acute problem: production
today holds a handful of brands and almost no car models, services, items, service centres
or fuel prices. Populating them by hand is the blocker on the mobile app's registration
flow, which cannot offer a car picker without brands and models.

Bulk import is scoped deliberately to **reference data only**. Activity data (fill-ups,
trips, logs) belongs to a specific car and user and carries ownership rules that make
bulk creation risky. Users, roles and permissions are excluded outright — importing those
can silently grant privileges.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Bulk-load a catalogue table from a spreadsheet (Priority: P1)

An administrator has a spreadsheet of car brands and models supplied by the business. They
open the relevant admin list screen, choose Import, upload the file, and the rows appear in
the table without further manual entry.

**Why this priority**: This is the entire point of the feature. Without it the catalogue
stays empty and the mobile registration cascade has nothing to show. Every other story is a
refinement of this one.

**Independent Test**: Upload a well-formed CSV of ten brands to the Brands screen and confirm
ten brands exist afterwards. Delivers immediate value even with no sample file and no failure
reporting.

**Acceptance Scenarios**:

1. **Given** an administrator on the Brands list screen, **When** they upload a CSV of valid
   brand rows, **Then** every row is created and the table reflects the new count.
2. **Given** a CSV whose header names differ from the system's field names, **When** the
   administrator maps each column to the correct field, **Then** the import succeeds using
   that mapping.
3. **Given** a CSV containing a row that duplicates an existing unique value, **When** the
   import runs, **Then** the remaining valid rows are still imported and the duplicate is
   reported rather than aborting the whole file.
4. **Given** a large file, **When** the import is running, **Then** the administrator is not
   forced to keep the browser open, and is notified when it finishes.

---

### User Story 2 - Understand the expected format before uploading (Priority: P2)

Before preparing a file, the administrator needs to know which columns are expected, which
are mandatory, and what a valid value looks like — without reading documentation or guessing.

**Why this priority**: Reduces failed imports rather than enabling them. Valuable, but the
feature works without it.

**Independent Test**: Open the import dialog on any of the six screens and confirm the
expected columns and an example value for each are visible, and that a sample file can be
downloaded and re-uploaded unmodified without error.

**Acceptance Scenarios**:

1. **Given** the import dialog is open, **When** the administrator looks at it before
   choosing a file, **Then** they see each expected column, an example value, and a clear
   indication of which columns are mandatory.
2. **Given** the import dialog is open, **When** the administrator downloads the sample file,
   **Then** it contains the expected header row and at least one example data row.
3. **Given** a downloaded sample file, **When** it is uploaded unmodified, **Then** it imports
   successfully.
4. **Given** a field constrained to a fixed set of values, **When** the administrator reads the
   dialog, **Then** the permitted values are stated.

---

### User Story 3 - Correct and re-submit rows that failed (Priority: P3)

Some rows in a real-world spreadsheet will be wrong. The administrator needs to know which
ones failed, why, and be able to fix just those rather than re-running the whole file.

**Why this priority**: Matters most on large files. On a ten-row file the administrator can
find the problem by eye.

**Independent Test**: Upload a file where a known subset of rows is invalid, then confirm the
valid rows were created, the count of failures is reported, and the failed rows are
retrievable with their reasons.

**Acceptance Scenarios**:

1. **Given** a file mixing valid and invalid rows, **When** the import completes, **Then** the
   administrator is told how many rows succeeded and how many failed.
2. **Given** an import with failures, **When** the administrator retrieves the failed rows,
   **Then** each carries the reason it was rejected.
3. **Given** a corrected file of previously failed rows, **When** it is uploaded, **Then**
   those rows are created without duplicating the rows that already succeeded.

---

### Edge Cases

- **No background worker running.** Imports are processed in the background. If no worker is
  running the file is accepted and then never processed, with no error shown. This is the
  single most likely operational failure and MUST be addressed (see FR-013).
- A row references a related record by name that does not exist (e.g. a car model naming an
  unknown brand).
- A file is uploaded with a header row but no data rows.
- A file is uploaded that is not a CSV, or is a genuine Excel `.xlsx` binary.
- A file exceeds a reasonable row count.
- A numeric column contains text, or a date column an unparseable value.
- A value falls outside a fixed permitted set (fuel type, size unit).
- Two rows within the same file conflict with each other on a unique value.
- A service centre's closing time is not after its opening time.
- The same file is uploaded twice.
- The administrator closes the browser mid-import.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Administrators MUST be able to start an import from the list screen of each of
  the six reference resources: brands, car models, items, services, service centres and fuel
  prices.
- **FR-002**: The system MUST accept CSV files. Genuine Excel `.xlsx` binaries are out of
  scope for this feature; the system MUST reject them with a message telling the administrator
  to save as CSV.
- **FR-003**: The system MUST let the administrator map the columns in their file to the
  system's fields, rather than requiring exact header names.
- **FR-004**: The system MUST validate every row against the same rules the equivalent manual
  form enforces, so that import cannot introduce data the UI would reject.
- **FR-005**: A single invalid row MUST NOT abort the import; valid rows MUST still be created.
- **FR-006**: The system MUST report, on completion, how many rows succeeded and how many
  failed.
- **FR-007**: Failed rows MUST be retrievable together with the reason each was rejected.
- **FR-008**: The import dialog MUST display the expected columns, an example value for each,
  and which are mandatory, before a file is chosen.
- **FR-009**: The administrator MUST be able to download a sample file containing the expected
  headers and at least one example row.
- **FR-010**: Columns that reference another record MUST be resolvable by a human-readable
  value (e.g. brand name), not only by numeric id.
- **FR-011**: Imports MUST be processed in the background so that large files do not block the
  browser, and the administrator MUST be notified on completion.
- **FR-012**: Import MUST be restricted to administrators. It MUST NOT be reachable by any
  other role. [Resolves an existing gap: an unrecognised ability currently resolves to
  "allow" rather than "deny".]
- **FR-013**: The deployment MUST include a running background worker, and the deployment
  documentation MUST state this. Without it imports silently never complete.
- **FR-014**: Imports MUST record who ran them and when.
- **FR-015**: Re-importing a row that already exists MUST NOT create a duplicate.

### Key Entities

- **Import**: One import run. Records the file name, which resource it targeted, who started
  it, when it completed, and the counts of total, processed and successful rows.
- **Failed Import Row**: One rejected row. Records the original row data, the reason for
  rejection, and the import it belongs to.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can populate a catalogue table of 100 rows in under two
  minutes of hands-on time, versus roughly an hour of manual entry.
- **SC-002**: An administrator who has never used the feature can produce a valid file and
  import it successfully on the first attempt, using only the sample file and the dialog's
  own guidance.
- **SC-003**: A file of 1,000 rows imports without the administrator's browser being blocked
  and without request timeouts.
- **SC-004**: No import can create a record that the equivalent manual form would reject.
- **SC-005**: Given a file with a known number of invalid rows, the reported success and
  failure counts match exactly, and every valid row is created.
- **SC-006**: A non-administrator cannot reach or trigger import on any resource.

## Assumptions

- Administrators can save a spreadsheet as CSV. `.xlsx` support is deliberately deferred;
  if it later proves a real obstacle it becomes its own feature.
- The six reference resources are the whole scope. Activity data (fill-ups, trips, car logs,
  reminders, parking records), documents, users, cars, roles and permissions are excluded.
- Import creates and updates records; it never deletes.
- The existing admin panel authentication and the `admin` role gate are reused as-is.
- The existing per-resource validation rules are the source of truth for what a valid row is;
  this feature does not introduce a second, divergent set.
- Background job processing already exists in the application and is available in every
  environment where import is enabled.
- English-only column headers and messages, consistent with the rest of the panel.
