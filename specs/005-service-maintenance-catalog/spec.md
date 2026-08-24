# Feature Specification: Service & Maintenance Catalog

**Feature Branch**: `005-service-maintenance-catalog`
**Created**: 2026-05-04
**Status**: Draft
**Input**: User description: "Service & Maintenance Catalog — predictive maintenance schedules, dealership locations, and a master parts inventory for the CarLog project."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View Upcoming Maintenance for My Car (Priority: P1)

A car owner opens their car in the app and sees a chronological, predictive list of upcoming maintenance milestones (e.g., 40 000 km service, 50 000 km service). For each milestone they see how many kilometres remain until it is due, how many parts are involved, and the total cost in EGP.

**Why this priority**: This is the headline value of the module — it turns the app from a passive logbook into a *predictive* maintenance assistant. Without this view, the data in the catalog is invisible to the end user.

**Independent Test**: Seed at least two services for a known car model where one milestone km is below the car's odometer and another is above. Call the upcoming-services endpoint for that car. Confirm only the future milestones are returned, sorted by km ascending, with `remaining_km`, `items_count`, and `price` populated.

**Acceptance Scenarios**:

1. **Given** a car owner whose car is at 35 000 km and whose model has milestones at 20 000 / 40 000 / 60 000 km, **When** they request the upcoming-services list for that car, **Then** only the 40 000 km and 60 000 km milestones are returned in that order, with `remaining_km = 5 000` and `25 000` respectively.
2. **Given** a car owner whose car has already passed every defined milestone, **When** they request the upcoming-services list, **Then** an empty list is returned (not an error).
3. **Given** a car model that has no services defined, **When** the owner of any car of that model requests the list, **Then** an empty list is returned.
4. **Given** an authenticated user, **When** they request upcoming-services for a car they do not own, **Then** the request is denied.
5. **Given** a milestone with three associated parts/items, **When** the owner views it, **Then** `items_count = 3` and the milestone's total `price` is shown in EGP.

---

### User Story 2 - Find a Nearby Service Center for My Brand (Priority: P2)

A car owner needs to take their car to an authorised service center. They select their brand and the app returns a list of dealerships for that brand, sorted by distance from their current location, each tagged with whether it is currently open and how far away it is.

**Why this priority**: This is the second consumer-facing flow. It commonly follows the predictive view ("I need a service at 40 000 km — where do I take the car?") and is independently useful even without the predictive list.

**Independent Test**: Seed two service centers for a brand at known coordinates (one near, one far). Call the brand's service-centers endpoint with the user's current GPS as query parameters. Confirm the nearer center appears first, with a numeric `distance_km`, and that `is_open` correctly reflects the current time relative to its opening hours.

**Acceptance Scenarios**:

1. **Given** a brand with service centers at multiple coordinates, **When** the owner requests the list passing their current `lat`/`lng`, **Then** the centers are returned sorted by distance ascending, each with a `distance_km` value.
2. **Given** a service center whose `open_at` ≤ current time < `close_at`, **When** the list is requested, **Then** that center's `is_open` is `true`.
3. **Given** a service center whose hours fall outside the current time, **When** the list is requested, **Then** its `is_open` is `false`.
4. **Given** a brand with zero service centers, **When** the list is requested, **Then** an empty list is returned (not an error).
5. **Given** a request without `lat`/`lng` query parameters, **When** the list is requested, **Then** the system rejects the request with a clear validation error (see Assumptions — GPS is treated as required for the nearby endpoint).

---

### User Story 3 - Maintain the Master Parts Inventory (Priority: P3)

An administrator manages a shared catalog of maintenance parts (e.g., "Engine Oil", "Oil Filter") with names and prices. These parts are reusable across many services.

**Why this priority**: Required so the catalog has data, but it is back-office work. End users never see this UI directly — they only see the *consequences* of it via US1's `items_count` and milestone price.

**Independent Test**: As an admin (or user with the right permission), `POST` a new item, `GET` the list, `GET` the single item, `PUT` an update, and `DELETE` it. Confirm a duplicate-name `POST` is rejected.

**Acceptance Scenarios**:

1. **Given** an authorised user, **When** they create an item with a unique name and a non-negative price, **Then** the item is persisted and returned with `201 Created`.
2. **Given** an existing item, **When** an authorised user attempts to create a second item with the same name, **Then** the request is rejected as a duplicate.
3. **Given** an authorised user, **When** they update an item's name or price (or both), **Then** the change is persisted; updating an item with the *same* name as itself does not trigger a duplicate error.
4. **Given** an authorised user, **When** they delete an item, **Then** the item is removed from the inventory.
5. **Given** an authenticated user without management permission, **When** they attempt any write to the inventory, **Then** the request is denied.

---

### Edge Cases

- A service milestone is exactly equal to the car's current odometer (`service.km === car.current_km`) — treated as already-passed and excluded from the upcoming list.
- A service center's `close_at` is earlier in the clock than its `open_at` (overnight hours, e.g., 22:00 → 02:00). Out of scope for v1: the system assumes `open_at < close_at` within the same day.
- The `lat`/`lng` query parameters are present but malformed (non-numeric or out of range −90/90 / −180/180) — the system MUST return a validation error rather than a runtime crash.
- A car model has thousands of historical services — the upcoming list filter excludes every passed milestone.
- An item is deleted while still attached to a service — the link in the pivot is removed via cascade; the service's reported `items_count` updates immediately.
- Two cars of the same model both reach the same milestone — both correctly see it as upcoming until each one's odometer crosses the threshold.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow an authenticated user to view a list of upcoming maintenance milestones for a car they own.
- **FR-002**: System MUST exclude any maintenance milestone whose target km is less than or equal to the car's current odometer reading from the upcoming list.
- **FR-003**: For each milestone in the upcoming list, the system MUST report the target km, the remaining km (target km − current odometer), the total cost in EGP, and the count of parts/items associated with the milestone.
- **FR-004**: System MUST return the upcoming list sorted by target km ascending.
- **FR-005**: System MUST deny upcoming-list requests for a car the requesting user does not own.
- **FR-006**: System MUST allow an authenticated user to view the list of service centers for a given brand.
- **FR-007**: System MUST sort the service-center list by distance from the user's current GPS coordinates.
- **FR-008**: System MUST report the distance in kilometres between the user and each service center.
- **FR-009**: System MUST report each service center's open/closed status based on the current time relative to its `open_at` and `close_at` hours.
- **FR-010**: System MUST allow authorised users to create, list, retrieve, update, and delete records in the master parts inventory.
- **FR-011**: System MUST reject the creation of a part whose name duplicates an existing part's name.
- **FR-012**: System MUST allow updating a part to keep the same name without triggering a false-positive duplicate error against itself.
- **FR-013**: System MUST require authentication for every endpoint in this module.
- **FR-014**: System MUST cascade-delete service-center records when their parent brand is deleted, milestone records when their parent car model is deleted, and milestone-item links when either side is deleted, so no orphan rows remain.
- **FR-015**: System MUST validate that supplied GPS query parameters fall within latitude −90 to +90 and longitude −180 to +180; out-of-range values MUST produce a validation error.
- **FR-016**: System MUST validate part prices and milestone prices as non-negative numbers.

### Key Entities

- **Service Center**: A physical dealership offering authorised maintenance for a single brand. Knows its address, contact phone, opening hours, and GPS coordinates. Belongs to one Brand; a brand may have many.
- **Maintenance Milestone (Service)**: A scheduled service event for a specific car model at a specific km threshold (e.g., 40 000 km service for Toyota Corolla 2020). Carries a total price in EGP and is associated with a list of parts/items. Belongs to one car model; a car model may have many.
- **Part (Item)**: A maintenance part reusable across many milestones (e.g., "Engine Oil"). Has a unique name and a price. Many-to-many with Maintenance Milestones.
- **Milestone-Part Link**: The association between a milestone and a part. Optionally records the specific car for which the part was used so per-car maintenance history can be reconstructed later.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A car owner can see their next upcoming maintenance milestone, including remaining km and total cost, in a single screen tap from the car detail view.
- **SC-002**: 100% of milestones returned in the upcoming list have target km strictly greater than the car's current odometer.
- **SC-003**: 100% of service centers returned in the brand list include both an open/closed indicator and a distance value.
- **SC-004**: Service centers are returned in correct distance order — when two centers are at known distances `dA < dB` from a known point, the list always shows A before B.
- **SC-005**: 100% of duplicate-name part submissions are rejected without writing a row to the inventory.
- **SC-006**: A part's price update appears in the milestone breakdown immediately on the next read; no manual cache flush is required.
- **SC-007**: 100% of cross-user attempts to read another user's car upcoming-services list are denied.
- **SC-008**: An admin can populate a fresh parts inventory of 50 items via the management endpoints in under five minutes.

## Assumptions

- The car odometer field already exists in the Car entity (named `current_km` in this project's existing schema). The `services` table's foreign key to a car model uses the project's existing column name (`car_model_id`), not `model_id`.
- "Open now" is computed against the application server's clock and assumes service centers and users share a single timezone (Africa/Cairo). Multi-timezone support is out of scope for v1.
- Service-center `open_at` < `close_at` within the same day — overnight hours (close after midnight) are out of scope for v1.
- Distance is computed using a great-circle approximation (Earth radius 6 371 km). Sub-100-metre accuracy is not required for the "nearby centers" UX.
- GPS coordinates supplied as query parameters come from the client device. The server stores and validates them but does not derive them.
- The master parts inventory is managed by users with elevated permission (admin or super-user under the project's existing RBAC). Regular users have read-only access at most.
- Authentication is handled by the existing platform mechanism; this module enforces *authorisation* (ownership and management permissions) but does not introduce a new login flow.
- All routes register under the project's existing auth-protected API root; no separate URL version segment is introduced.
- GPS coordinates are **required** when listing nearby service centers — the headline value of the endpoint is distance-sorted results, so a request without `lat`/`lng` is rejected with a clear validation error rather than degraded silently.
- Greenfield database state: the planner is permitted to rewrite earlier `create_*_table` migrations rather than authoring `alter_*` migrations, because the deploy strategy is `migrate:fresh`.
