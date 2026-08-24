# Feature Specification: Tank Size & Percentage-Based Fuel Usage

**Feature Branch**: `008-tank-size-fuel-usage`
**Created**: 2026-06-02
**Status**: Draft
**Input**: User description: "Add per-car tank_size (full fuel capacity in liters) and an after-fill tank_percentage (0-100 gauge reading) on each fill-up, and use them to refine the average fuel-usage statistic (reported as km/L)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Record and update tank capacity (Priority: P1)

A driver tells the app how large their car's fuel tank is (its full capacity in
liters). They can provide this when first registering their car, and they can
correct or set it later through their **profile-edit** screen (which also lets
them update their name).

**Why this priority**: Tank capacity is the foundation that makes the refined
fuel-usage calculation possible. Capturing it is independently valuable (it is
visible car information) and unblocks the rest of the feature.

**Independent Test**: Register a car with a tank capacity and confirm it appears
in the car's details; register without one and confirm the car is still created;
later update the capacity (and name) via profile edit and confirm the new values
are returned.

**Acceptance Scenarios**:

1. **Given** a new user registering a car, **When** they include a tank capacity, **Then** the car is created with that capacity and it appears in the car details.
2. **Given** a new user registering a car, **When** they omit the tank capacity, **Then** the car is still created successfully with no capacity set.
3. **Given** an authenticated user, **When** they submit an updated tank capacity and/or name via profile edit, **Then** their car's tank capacity (and their name) is updated and the updated profile and car are returned.
4. **Given** an unauthenticated request, **When** it calls the profile-edit endpoint, **Then** it is rejected as unauthorized.

---

### User Story 2 - Record tank level after a fill-up (Priority: P2)

When logging a fill-up, a driver can optionally record how full the tank was
*after* filling, as a percentage from 0 to 100 read from the fuel gauge. This
works for both detailed manual fill-ups and the quick "amount paid" fill-up.

**Why this priority**: The after-fill level is the extra signal that lets the
system correct for partial fills. It is optional, so it layers cleanly on top of
the existing fill-up flows without disrupting them.

**Independent Test**: Record a fill-up with an after-fill percentage and confirm
it is stored and returned; record one without it and confirm the fill-up still
succeeds; attempt an out-of-range percentage and confirm it is rejected.

**Acceptance Scenarios**:

1. **Given** a driver logging a manual fill-up, **When** they include an after-fill percentage between 0 and 100, **Then** the fill-up is saved with that level and it appears in the fill-up details.
2. **Given** a driver logging a quick fill-up, **When** they include an after-fill percentage, **Then** it is saved alongside the existing amount/liters data.
3. **Given** a driver logging any fill-up, **When** they omit the percentage, **Then** the fill-up is saved successfully without it.
4. **Given** a driver logging a fill-up, **When** they submit a percentage below 0 or above 100, **Then** the request is rejected with a validation error.

---

### User Story 3 - See accurate average fuel efficiency (Priority: P3)

A driver views their car's fill-up statistics and sees an average fuel
efficiency in kilometers per liter that reflects how much fuel was actually
consumed between fill-ups, accounting for partial fills when the data is
available.

**Why this priority**: This is the headline payoff of the feature, but it
depends on the inputs from Stories 1 and 2. It must also remain correct (and
non-erroring) for cars that have little or no tank data.

**Independent Test**: For a car with complete capacity and after-fill data
across several fill-ups, confirm the reported average matches the corrected
calculation; for a car missing that data, confirm a sensible average is still
returned without error.

**Acceptance Scenarios**:

1. **Given** a car with a known tank capacity and after-fill levels on its fill-ups, **When** the driver views statistics, **Then** the average km/L reflects consumption computed as liters added adjusted by the change in tank level times capacity, across consecutive fill-ups ordered by odometer.
2. **Given** a car with no tank capacity or no after-fill levels, **When** the driver views statistics, **Then** the average km/L is computed from liters added and distance alone, without error.
3. **Given** a car with only one fill-up (or with non-positive computed consumption), **When** the driver views statistics, **Then** the average is reported as zero.
4. **Given** a car with multiple fill-ups, **When** the average is computed, **Then** the fuel from the first fill-up is excluded from consumption because the distance before it is unknown.

---

### Edge Cases

- **Single fill-up**: not enough data to compute distance between fills → average is zero.
- **Missing tank capacity**: the change-in-level correction is skipped; consumption falls back to liters added.
- **Missing after-fill level on some fills**: correction applied only to consecutive pairs where both levels (and capacity) are known; other intervals use liters added alone.
- **Tank level higher at the later fill than the earlier one** (e.g. user filled to fuller level): the correction term naturally accounts for it from the recorded data.
- **Non-positive total consumption** (sparse or inconsistent data): average is reported as zero rather than a negative or infinite value.
- **Out-of-range percentage** (<0 or >100) or **non-positive capacity**: rejected by validation.
- **Electric vehicles**: capacity and level can still be stored, but km/L is not specially adapted for them in this feature.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a user to optionally provide their car's full fuel tank capacity (in liters) when registering the car.
- **FR-002**: System MUST allow an authenticated user to update their own car's tank capacity (and their name) after registration via a profile-edit endpoint; the tank capacity targets the user's car created at registration (their most recent car if more than one exists).
- **FR-003**: System MUST treat tank capacity as optional — a car may exist with no capacity recorded.
- **FR-004**: System MUST validate tank capacity, when provided, as a positive number within a reasonable upper bound.
- **FR-005**: System MUST allow a user to optionally record the after-fill tank level (0–100%) on each fill-up, for both the manual fill-up and the quick fill-up entry paths.
- **FR-006**: System MUST keep the existing fill-up inputs (liters and amount paid) unchanged and treat the after-fill tank level as supplementary information only.
- **FR-007**: System MUST validate the after-fill tank level, when provided, as a number between 0 and 100 inclusive.
- **FR-008**: System MUST report average fuel usage as kilometers per liter (km/L).
- **FR-009**: System MUST compute consumption between two consecutive fill-ups (ordered by odometer ascending) as the liters added at the later fill-up plus the change in tank level (earlier minus later) multiplied by the tank capacity, applying the tank-level adjustment only when both fill-ups have a recorded after-fill level and the car has a recorded capacity.
- **FR-010**: System MUST exclude the first fill-up's liters from total consumption, because the distance traveled before the first fill-up is unknown.
- **FR-011**: System MUST degrade gracefully when tank capacity or after-fill levels are missing, computing the average from liters added and distance traveled alone.
- **FR-012**: System MUST report an average of zero when there are fewer than two fill-ups or when total computed consumption is not greater than zero.
- **FR-013**: System MUST expose the tank capacity in the car's representation and the after-fill tank level in the fill-up's representation.
- **FR-014**: System MUST continue to report the existing fill-up statistics (total fill-ups, total cost) alongside the refined average, and additionally report the total distance covered.

### Key Entities *(include if feature involves data)*

- **Car**: gains an optional **tank capacity** attribute representing the full fuel capacity in liters.
- **Fill-up**: gains an optional **after-fill tank level** attribute (0–100%) representing the fuel gauge reading immediately after filling.
- **Fuel-usage statistics**: a derived summary for a car, including the average efficiency in km/L and the total distance covered, computed from the car's ordered fill-ups and capacity.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can record a tank capacity at registration and later update it, and in both cases the current capacity is visible in the car's details.
- **SC-002**: A user can record an after-fill tank level on both manual and quick fill-ups, and in every such case it is stored and returned with the fill-up.
- **SC-003**: For a car with complete capacity and after-fill data, the reported average km/L equals the partial-fill-corrected calculation within 0.01 (two-decimal rounding).
- **SC-004**: For a car missing capacity or after-fill data, viewing statistics returns a numeric average without error in 100% of cases.
- **SC-005**: For identical fill-up data, the refined average no longer counts the first fill-up's fuel as consumed (eliminating the prior over-count), verifiable by comparison against the previous calculation.
- **SC-006**: All fill-up and registration requests that previously succeeded without the new fields continue to succeed unchanged (full backward compatibility).

## Assumptions

- Tank capacity is expressed in liters and after-fill level as a percentage (0–100), consistent with how the mobile app reads the fuel gauge.
- The after-fill level is a supplementary, possibly imprecise signal; liters/amount-paid remain the authoritative fuel-quantity inputs.
- Electric vehicles are not special-cased; the km/L metric is oriented to liquid fuel, and a missing capacity simply disables the correction term.
- Tank capacity is made correctable through a profile-edit endpoint that updates the authenticated user's own data (their name and their car's tank capacity); each user is treated as having a single car (the one created at registration). No separate car-edit endpoint or permission is introduced.
- No automated tests are added (per project convention; testing is N/A for this codebase).
- A reference design document exists at `docs/superpowers/specs/2026-06-02-tank-size-fuel-usage-design.md` and informs implementation.
