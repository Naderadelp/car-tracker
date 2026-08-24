# Feature Specification: Parking Tracker

**Feature Branch**: `004-parking-tracker`
**Created**: 2026-05-04
**Status**: Draft

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Record a Parking Location (Priority: P1)

A car owner parks their vehicle and wants to record exactly where it is so they can find it later. They may be in an outdoor lot with GPS signal, or in an underground garage where GPS fails — the system must handle both scenarios.

**Why this priority**: This is the core action of the entire module. Without the ability to record a location, no other story delivers value.

**Independent Test**: Can be fully tested by submitting a new parking record (with GPS coordinates or descriptive text) and confirming it is saved and returned in the car's parking history.

**Acceptance Scenarios**:

1. **Given** an authenticated car owner, **When** they submit a parking record with GPS coordinates and a timestamp, **Then** the record is saved and a success response is returned.
2. **Given** an authenticated car owner in an underground garage, **When** they submit a parking record with a location name and description (no GPS), **Then** the record is saved successfully.
3. **Given** an authenticated car owner, **When** they submit a parking record with neither a name nor GPS coordinates, **Then** the system rejects the request with a validation error.
4. **Given** an authenticated car owner, **When** they submit a parking record with a future timestamp, **Then** the system rejects the request with a validation error.
5. **Given** an authenticated user, **When** they attempt to add a parking record to a car they do not own, **Then** the request is denied.

---

### User Story 2 - Find Current Parking Location (Priority: P2)

A car owner returns from a trip or errand and cannot remember exactly where they left their car. They look up their car's current parking location to navigate back to it.

**Why this priority**: The most frequent read use-case. Quickly surfacing the most recent location is the primary consumer-facing value of this tracker.

**Independent Test**: Can be fully tested by recording a parking location and then retrieving the "current" parking endpoint, confirming it returns the most recent entry.

**Acceptance Scenarios**:

1. **Given** a car with at least one parking record, **When** the owner requests the current parking location, **Then** the most recently recorded location is returned.
2. **Given** a car with no parking history, **When** the owner requests the current parking location, **Then** a "not found" response is returned.
3. **Given** an authenticated user, **When** they request the current parking for a car they do not own, **Then** the request is denied.

---

### User Story 3 - View Full Parking History (Priority: P3)

A car owner wants to review all past locations where their vehicle has been parked, for example to verify usage or identify patterns.

**Why this priority**: Historical data is valuable context but not required to accomplish the primary goal of finding the car right now.

**Independent Test**: Can be tested by recording multiple parking events and confirming the history endpoint returns all of them in reverse chronological order.

**Acceptance Scenarios**:

1. **Given** a car with multiple parking records, **When** the owner requests the parking history, **Then** all records are returned with the most recent first.
2. **Given** a car with no parking history, **When** the owner requests the parking history, **Then** an empty list is returned.
3. **Given** an authenticated user, **When** they request history for a car they do not own, **Then** the request is denied.

---

### User Story 4 - Remove a Parking Record (Priority: P4)

A car owner wants to delete a parking record, for example a duplicate entry or an erroneous one.

**Why this priority**: Data hygiene is secondary to recording and retrieving data.

**Independent Test**: Can be tested by creating a parking record and then deleting it, confirming it no longer appears in the history.

**Acceptance Scenarios**:

1. **Given** an authenticated car owner with a parking record, **When** they delete it, **Then** the record is removed and a success response is returned.
2. **Given** an authenticated user, **When** they attempt to delete a parking record belonging to another user's car, **Then** the request is denied.
3. **Given** an authenticated car owner, **When** they attempt to delete a parking record that belongs to a different car than specified, **Then** the request is denied.

---

### Edge Cases

- What happens when both GPS coordinates and a descriptive name are submitted? Both should be accepted and stored together — hybrid records are valid.
- What happens when only one of lat/lng is provided without the other? The system should treat an incomplete coordinate pair as invalid GPS data.
- What happens when a car is deleted? All associated parking records are automatically removed.
- What happens when `parked_at` equals the current time exactly? The record should be accepted (boundary condition: `before_or_equal:now`).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a car owner to record a parking event with either GPS coordinates (latitude and longitude) or a descriptive location (name and/or description).
- **FR-002**: System MUST reject any parking record that contains neither a location name nor GPS coordinates, preventing completely blank entries.
- **FR-003**: System MUST require a `parked_at` timestamp for every parking record, and MUST reject timestamps set in the future.
- **FR-004**: System MUST allow a car owner to retrieve the most recent parking location for their car as a single object.
- **FR-005**: System MUST return a "not found" response when the current parking location is requested for a car with no parking history.
- **FR-006**: System MUST allow a car owner to retrieve the full chronological parking history for their car, ordered from most recent to oldest.
- **FR-007**: System MUST allow a car owner to delete a parking record, provided it belongs to their car.
- **FR-008**: System MUST prevent any authenticated user from reading, creating, or deleting parking records for a car they do not own.
- **FR-009**: System MUST automatically remove all parking records when their associated car is deleted.
- **FR-010**: System MUST accept GPS latitude values in the range −90 to +90 and longitude values in the range −180 to +180.

### Key Entities

- **Parking Record**: Represents a single parking event for a specific car. Key attributes: location (GPS coordinates and/or descriptive text), time parked. Belongs to one Car.
- **Car**: The vehicle being tracked. Already exists in the system. A car may have zero or many parking records.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A car owner can record their parking location in a single operation, with the result immediately available for retrieval.
- **SC-002**: The current parking location is always the chronologically most recent record — there is no ambiguity in which record is "current."
- **SC-003**: The system rejects 100% of parking submissions that contain no location data (neither name nor coordinates).
- **SC-004**: The system rejects 100% of parking submissions where the recorded parking time is set in the future.
- **SC-005**: Parking history is always returned in reverse chronological order with no exceptions.
- **SC-006**: No parking data for Car A is ever accessible to a user who owns only Car B.
- **SC-007**: Deleting a car results in zero orphaned parking records remaining in the system.

## Assumptions

- Each car in the system belongs to exactly one user; ownership is determined by the car's `user_id` field.
- GPS coordinates are provided by the calling client device; the server stores and validates them but does not generate or derive them.
- A parking record is immutable after creation — there is no update/edit endpoint. Users must delete and re-create if a correction is needed.
- Full parking history is retained indefinitely; there is no automatic expiration or archival of old records.
- An incomplete coordinate pair (only lat or only lng provided) is treated as missing GPS data, requiring a location name to pass validation.
- Authentication is handled by an existing mechanism in the platform; this module only enforces car ownership, not login state.
