# Feature Specification: Logbook & Telemetry

**Feature Branch**: `003-logbook-telemetry`
**Created**: 2026-05-03
**Status**: Draft

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Record a Refueling Event (Priority: P1)

A car owner logs a refueling event by entering how many litres were added, the total amount paid, and the date. They do not enter an odometer reading — the system automatically captures the car's current odometer value at the moment of submission.

**Why this priority**: The fill-up record is the primary manual data entry. Without recorded fill-ups, fuel consumption analytics cannot be produced.

**Independent Test**: Submit a valid refueling record (litres, cost, date) and verify it appears in the history with correct values and that the car's odometer was silently snapshotted onto the record.

**Acceptance Scenarios**:

1. **Given** I own a car, **When** I submit a refueling record with valid litres, cost, and a date on or before today, **Then** the record is saved, the car's current odometer is captured automatically, and I receive a success confirmation.
2. **Given** I submit a fill date in the future, **Then** the record is rejected with a clear validation message.
3. **Given** I submit a fuel volume below the minimum allowed amount, **Then** the record is rejected with a validation message.

---

### User Story 2 — View Refueling History with Consumption Statistics (Priority: P2)

A car owner views all recorded refueling events for their car alongside a live summary: total fill-up count, total money spent, and their average fuel consumption rate derived from the car's odometer snapshots taken at each fill-up.

**Why this priority**: Consumption analytics and expense visibility are the core user value of the logbook feature.

**Independent Test**: Record at least two fill-ups after the car's odometer has advanced between them, then view the history and verify statistics match manual calculation.

**Acceptance Scenarios**:

1. **Given** I have recorded multiple fill-ups, **When** I view my fill-up history, **Then** I see the list ordered most-recent-first with a summary showing total count, total cost, and average consumption rate.
2. **Given** I have no fill-ups for a car, **When** I view the history, **Then** I see an empty list with zero-valued statistics.
3. **Given** two cars exist in the system, **When** I view fill-ups for my car, **Then** I see only records belonging to that car.

---

### User Story 3 — Delete a Refueling Record (Priority: P3)

A car owner can remove a refueling record that was entered incorrectly.

**Why this priority**: Data correction is a necessary housekeeping capability.

**Independent Test**: Create a fill-up, delete it, then verify it no longer appears in the history.

**Acceptance Scenarios**:

1. **Given** a fill-up belongs to my car, **When** I delete it, **Then** it is permanently removed and I receive a confirmation.
2. **Given** a fill-up belongs to a different user's car, **When** I attempt to delete it, **Then** the request is denied.

---

### User Story 4 — Record a GPS Trip (Priority: P4)

The mobile application records a journey by collecting GPS location points with timestamps and submits them to the server. The system calculates total distance, stores the trip, and automatically increments the car's master odometer.

**Why this priority**: Trip recording is the primary mechanism for keeping the car's odometer current without any manual effort from the user.

**Independent Test**: Submit a sequence of at least two valid GPS points and verify a trip record is created with correct start/end values, calculated distance, and that the car's odometer increased by that distance.

**Acceptance Scenarios**:

1. **Given** I own a car and submit a valid sequence of at least 2 GPS points with timestamps, **When** the trip is processed, **Then** a trip record is stored with the correct start point, end point, and calculated distance.
2. **Given** a trip is successfully saved, **When** I check the car's master odometer, **Then** it has increased by exactly the trip's calculated distance.
3. **Given** I submit fewer than 2 GPS points, **Then** the request is rejected with a validation message.
4. **Given** a coordinate carries an out-of-range latitude or longitude, **Then** the request is rejected with a validation message.

---

### User Story 5 — View Trip History (Priority: P5)

A car owner views all recorded trips for their car, ordered most-recent-first.

**Why this priority**: Useful for reviewing travel history; lower priority than data capture.

**Independent Test**: Record multiple trips and verify the list is ordered by start time descending and scoped to the correct car.

**Acceptance Scenarios**:

1. **Given** trips exist for my car, **When** I view the trip history, **Then** I see all trips ordered by start time, most recent first.
2. **Given** trips exist for another user's car, **When** I view my trip history, **Then** I do not see them.

---

### Edge Cases

- What if all fill-ups share the same snapshotted odometer value? Average consumption returns zero or null rather than an error.
- What if the car is deleted? All associated fill-ups and trips are removed automatically.
- What if a GPS coordinate sequence contains duplicate consecutive points? The record is accepted; those pairs contribute zero distance to the total.
- What if the car's master odometer is zero when a fill-up is recorded? The snapshot captures zero; consumption calculations will be low-quality until the odometer reflects actual mileage.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow authenticated car owners to record a refueling event with: fuel volume (litres), total cost (EGP), and fill date.
- **FR-002**: The system MUST automatically snapshot the car's current master odometer onto the fill-up record at the time of creation — the user does not provide this value.
- **FR-003**: The system MUST reject refueling records with a fill date set in the future.
- **FR-004**: The system MUST reject refueling records with a fuel volume below 0.1 litres.
- **FR-005**: The system MUST return all refueling records for a car ordered by fill date descending, accompanied by: total fill-up count, total fuel expenditure (EGP), and average fuel consumption (calculated as the span between the highest and lowest snapshotted odometer values divided by total litres across all fill-ups).
- **FR-006**: The system MUST allow an authenticated owner to permanently delete a refueling record belonging to their car.
- **FR-007**: The system MUST allow authenticated car owners to submit a GPS trip as a sequence of timestamped location points (minimum 2 points).
- **FR-008**: The system MUST validate that all submitted GPS coordinate values fall within the valid geographic range.
- **FR-009**: The system MUST calculate total trip distance using great-circle distance calculations across consecutive coordinate pairs in the submitted sequence.
- **FR-010**: The system MUST store each trip with its start location and timestamp, end location and timestamp, and total calculated distance.
- **FR-011**: The system MUST automatically increment the car's master odometer by the trip's total distance when a trip is successfully recorded.
- **FR-012**: The system MUST return all trips for a car ordered by start time descending.
- **FR-013**: All refueling and trip endpoints MUST enforce car ownership — only the car's owner may access or modify its logbook data.

### Key Entities

- **FillUp**: A single refueling event for a car. Carries: fuel volume (litres), auto-snapshotted odometer reading (km), total cost (EGP), and fill date. Belongs to a Car.
- **Trip**: A single recorded journey. Carries: start and end location (lat/lng) and timestamps, plus the total calculated distance in km. Belongs to a Car.
- **Car** *(existing)*: Has a master odometer field that is set at registration, incremented by trips (FR-011), and can be manually adjusted via the car management interface.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A car owner can submit a complete refueling record (litres, cost, date only) in under 30 seconds.
- **SC-002**: Consumption statistics (average km/L, total cost) are accurate to 2 decimal places relative to the recorded data.
- **SC-003**: The car's master odometer reflects all trip distances immediately within the same operation that created them — no deferred updates.
- **SC-004**: A trip distance calculation for a known real-world route falls within ±1% of the actual ground distance.
- **SC-005**: All logbook data is strictly scoped to the owning user — no cross-user data is accessible or modifiable.

---

## Assumptions

- The mobile application handles GPS collection and batching; the server receives the final array of coordinate points, not a live stream.
- All monetary values are in Egyptian Pounds (EGP). Multi-currency support is out of scope.
- The car's master odometer is first set during car registration (`current_km`). Updates occur automatically via trips or manually via the car management interface — both are outside the scope of this module.
- Average consumption is only meaningful when at least two fill-ups have different snapshotted odometer values. With identical or insufficient readings the value is returned as zero or null.
- Deleting a fill-up or trip does not retroactively adjust the car's master odometer.
- There is no edit capability for fill-ups or trips in this version; incorrect records must be deleted and re-entered.
- Deleting a car cascades and removes all its fill-ups and trips.
