# Feature Specification: Close the CarLog Mobile API Gaps

**Feature Branch**: `010-mobile-api-gaps`
**Created**: 2026-08-24
**Status**: Draft
**Input**: User description: "Close the CarLog mobile API gaps documented in docs/mobile-api-gaps.md: 5 blockers (car update endpoint with odometer/warranty/colour, relaxed document rules, richer manual fill-ups, an expenses resource, an issues resource), 9 missing fields, 3 missing aggregates, and 4 response-contract fixes."

## Context

The CarLog mobile app stores everything on the device today. The gap report
(`docs/mobile-api-gaps.md`, 2026-08-18) compared every screen in the Flutter
build against the published API guide and found 21 places where the app cannot
be backed by the service as it stands, plus 3 compliance items.

This feature closes all of them. Nothing here is new product surface: every item
is something the app already shows a driver, that the service currently cannot
store or return.

**Two audiences.** The immediate consumer is the mobile client. The person whose
experience is at stake is the driver, and the acceptance scenarios below are
written from the driver's seat.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The car record stays current (Priority: P1)

A driver's mileage changes every day. Today it is captured once, when they sign
up, and can never be corrected. The same is true of their warranty details and
their car's colour. A driver must be able to keep the basic facts about their
car accurate for as long as they own it.

**Why this priority**: Mileage is the single most-written value in the app and
the input to almost everything else — fuel consumption, the service schedule,
warranty state. Every other story produces wrong numbers until this one exists.
It is the root dependency, and the gap report names it as such.

**Independent Test**: Register a car, change its recorded mileage, and confirm
the new value is returned on the next read and used by the next fuel record.
Delivers a driver who can correct their own odometer — useful on its own.

**Acceptance Scenarios**:

1. **Given** a car recorded at 45,000 km, **When** the driver corrects the reading to 47,200 km, **Then** the car reports 47,200 km and the next fuel record is filed against 47,200 km.
2. **Given** a car with a warranty that was extended by the dealer, **When** the driver updates the warranty distance and expiry date, **Then** the new limits are reflected in the car's warranty state.
3. **Given** a driver who chose a paint colour at sign-up, **When** they read their car back, **Then** the colour they chose is returned.
4. **Given** a car whose mileage was mistyped as 470,000 km, **When** the driver corrects it back down to 47,000 km, **Then** the correction is accepted and later records use 47,000 km.
5. **Given** a driver, **When** they attempt to change a car that is not theirs, **Then** the change is refused.

---

### User Story 2 - Records the app already collects are accepted (Priority: P1)

Two of the app's most-used forms are rejected by the service today. The document
form collects a type and an expiry date and nothing else — but a file is
demanded, and an expiry date in the past is refused. The fuel form collects the
station name, the mileage at the pump and the fuel type — none of which the
service will accept.

**Why this priority**: These are not missing features, they are refusals. The
driver fills in a form the app offers, and the save fails. An expired licence is
precisely the record a driver most needs to keep.

**Independent Test**: Save a document with no file and an expiry date last
month, and save a fuel record carrying a station name, a pump reading and a
fuel type. Both succeed and read back intact.

**Acceptance Scenarios**:

1. **Given** a driver whose licence expired last month, **When** they record it with a type and that past date and no scan attached, **Then** the record is saved and reported as expired.
2. **Given** a driver adding a scan later, **When** they attach a file to an existing document, **Then** the file is stored against that record.
3. **Given** a driver filling up at a named station, **When** they record litres, cost, the pump reading and the fuel grade, **Then** all four are stored and returned on the record.
4. **Given** a driver who records a fill-up without a pump reading, **When** the record is saved, **Then** it is accepted and the car's current mileage is used.

---

### User Story 3 - A driver can close their account (Priority: P1)

A driver who no longer wants the service can delete their account and their data
from inside the app. Separately, asking to reset a forgotten password must not
reveal whether an address has an account.

**Why this priority**: Both app stores require in-app account deletion from any
app that offers account creation. This blocks store review, not merely
integration — it will stop the release regardless of how complete the rest is.
The password-reset leak is a live account-enumeration weakness on a shipped
endpoint.

**Independent Test**: Request deletion as a signed-in driver and confirm the
account can no longer sign in and personal data is gone. Request a password
reset for a known and an unknown address and confirm both responses are
identical.

**Acceptance Scenarios**:

1. **Given** a signed-in driver, **When** they request account deletion, **Then** their account and personal records become inaccessible and their sessions end.
2. **Given** a deleted account, **When** someone attempts to sign in with those credentials, **Then** the attempt fails as it would for an address that never existed.
3. **Given** an address with no account, **When** a password reset is requested for it, **Then** the response is indistinguishable from one for a registered address.

---

### User Story 4 - Every cost has somewhere to live (Priority: P2)

The app has a whole Costs tab. A driver logs insurance, tyres, fines, washing
and parking there, each with a date, a label, an amount and a category, and sees
a total and a breakdown by category. The service has nowhere to put any of it.

The ledger is **unified**: fuel and maintenance spending appears in it too,
carried across automatically from the records the driver already files, so the
Costs tab shows everything the car has cost without asking anyone to type it
twice. A driver can overwrite the amount on any of those carried-across entries,
and can delete a manual entry that turns out to duplicate one.

**Why this priority**: A complete bottom-nav tab is unusable without it. It sits
below P1 only because it fails visibly and safely — the tab is empty rather than
wrong.

**Independent Test**: Record several costs in different categories, read them
back for one car, and confirm the total and the per-category split.

**Acceptance Scenarios**:

1. **Given** a driver, **When** they record an insurance payment with a date, a label and an amount, **Then** it appears in their cost list for that car.
2. **Given** a driver who files a fuel record, **When** they open the Costs tab, **Then** that spending is already listed under the fuel category without being re-entered.
3. **Given** a carried-across entry whose amount is wrong, **When** the driver overwrites it, **Then** the total uses the driver's figure rather than the original.
4. **Given** a manual entry that duplicates a carried-across one, **When** the driver deletes the manual entry, **Then** the total stops counting it twice.
5. **Given** several recorded costs, **When** the driver opens the Costs tab, **Then** they see a total and a share for each category.
6. **Given** a mistyped amount, **When** the driver edits or removes the entry, **Then** the list and the totals reflect the change.
7. **Given** a driver, **When** they read costs, **Then** they see only costs for their own car.

---

### User Story 5 - Faults are recorded, not just remembered (Priority: P2)

The app keeps a photo-first fault log: a date, a title, a severity, a summary of
the problem, what was done about it, and a photo. Serious unresolved faults are
promoted onto the notifications screen. The service has no equivalent — the
nearest thing is a reminder, which is a future date rather than a recorded
event.

**Why this priority**: A second complete screen with nothing behind it. The
photo handling reuses the pattern already proven by documents, so the cost is
mostly in the new record type rather than in new mechanics.

**Independent Test**: Record a fault with a photo, list faults for a car, mark
one resolved, and confirm unresolved serious faults are the ones surfaced for
attention.

**Acceptance Scenarios**:

1. **Given** a driver whose car developed a fault, **When** they record it with a title, a severity and a description, **Then** it appears in the fault log for that car.
2. **Given** a recorded fault, **When** the driver attaches a photo, **Then** the photo is stored and retrievable with the fault.
3. **Given** a serious unresolved fault, **When** the driver checks what needs attention, **Then** that fault is listed alongside overdue services.
4. **Given** a fault that has been fixed, **When** the driver records the solution and closes it, **Then** it stops being surfaced as needing attention.

---

### User Story 6 - The whole service schedule is visible (Priority: P2)

The Services tab shows a driver their complete maintenance schedule — intervals
already passed greyed out, the next one highlighted, later ones ahead of it —
and opening any interval shows a checklist of items with individual prices.
Today only future intervals can be fetched, they arrive without their checklist,
and a driver's own added lines have nowhere to be stored.

**Why this priority**: The checklist screen *is* the Services tab. Without it
the tab renders a count where it should render content.

**Independent Test**: Read the schedule for a car and confirm past, current and
future intervals all appear, each carrying its checklist, and that a
driver-added line persists.

**Acceptance Scenarios**:

1. **Given** a car past several service intervals, **When** the driver opens the schedule, **Then** completed, current and upcoming intervals are all present and distinguishable.
2. **Given** an interval with checklist items, **When** the driver opens it, **Then** each item's name and price are shown without a further request.
3. **Given** a driver who adds their own line to an interval with a label and a price, **When** they reopen that interval, **Then** their line is still there.

---

### User Story 7 - History keeps its detail (Priority: P2)

Three history screens lose information the moment a record is filed. A
maintenance entry reads "Brake pads · El Nasr Auto · service" in the app but
stores only a cost and a date, so ad-hoc work ends up as a number with no
description. A trip shows start and end time, duration and top speed, all of
which are discarded. A saved parking spot holds a label, a reverse-geocoded
address and a note, and cannot be corrected once saved.

**Why this priority**: The data is collected and then thrown away. Every day
this is not fixed produces history that can never be reconstructed.

**Independent Test**: File a maintenance entry with a description and a
workshop, a trip with its timings and top speed, and a parking spot with an
address; read all three back; then correct the parking spot.

**Acceptance Scenarios**:

1. **Given** ad-hoc work not tied to a scheduled interval, **When** the driver files it with a title, a workshop, a category and notes, **Then** the history row shows what was done and who did it.
2. **Given** a completed trip, **When** it is filed with its start time, end time, duration and top speed, **Then** the trip history shows all four.
3. **Given** a saved parking spot, **When** the driver corrects its label, address or note, **Then** the correction is stored.

---

### User Story 8 - Arabic content reads as Arabic (Priority: P3)

The app ships a full right-to-left Arabic build. Service items, service-centre
names and their addresses all have an Arabic variant in the client model and
switch with the app's language. The service holds one Latin name and one Latin
address.

**Why this priority**: The Arabic build is real and shipping, but it degrades to
Latin catalogue names rather than failing. Below the stories that refuse data or
lose it.

**Independent Test**: Read the service catalogue and centre list with the app in
Arabic and confirm Arabic names and addresses are available without a second
request.

**Acceptance Scenarios**:

1. **Given** a service item with an Arabic name, **When** the catalogue is read, **Then** both the Latin and Arabic names are available to the client.
2. **Given** a service centre with an Arabic name and address, **When** centres are read, **Then** both language variants are available.
3. **Given** an entry with no Arabic variant recorded, **When** it is read, **Then** the client can still display it without a blank.

---

### User Story 9 - Insight screens load in one request (Priority: P3)

Three screens currently need a driver's entire history downloaded to render.
The monthly report compares this month with last across spend, distance,
fill-up count, average fuel price and cost per kilometre, with a four-week
breakdown. The fuel chart plots efficiency per fill-up with best, worst and
average called out. The valuation screen shows what the car is worth now
against what it cost.

**Why this priority**: These screens work today by brute force, page by page,
which gets slower for every driver every month. They are correctness-neutral but
they do not scale.

**Independent Test**: Open each insight screen for a driver with a long history
and confirm the figures are returned directly rather than assembled from the
full record set.

**Acceptance Scenarios**:

1. **Given** a driver with several months of history, **When** they open the monthly report, **Then** this month's and last month's spend, distance, fill-up count, average fuel price and cost per kilometre are returned together.
2. **Given** a driver with a series of fill-ups, **When** they open the fuel chart, **Then** each fill-up carries its own efficiency figure and the best, worst and average are identifiable.
3. **Given** a driver who recorded what they paid for the car and when, **When** they open the valuation screen, **Then** they see the purchase figure, an estimated present value and the change between them.

---

### User Story 10 - One predictable response shape (Priority: P3)

Four inconsistencies each cost the client a special case: the two fuel-record
endpoints return the same resource in two different shapes; the busiest screen's
list arrives unwrapped while every other list is wrapped; the error field is an
object in one failure mode and an empty list in another, so no single error
model parses both; and reading the signed-in driver omits their car, forcing a
second request on every cold start.

**Why this priority**: Nothing is broken and nothing is lost — the client simply
carries avoidable complexity. Last, but cheap.

**Independent Test**: Decode every list response with one decoder and every
error with one error model, and confirm app launch needs a single request to
recover the driver and their car.

**Acceptance Scenarios**:

1. **Given** any collection endpoint, **When** it is read, **Then** it is wrapped consistently with every other collection.
2. **Given** the same resource created by either of two routes, **When** each response is decoded, **Then** one decoder handles both.
3. **Given** any failure, **When** the error is decoded, **Then** the error field has one consistent shape across validation and non-validation failures.
4. **Given** a cold app launch, **When** the signed-in driver is read, **Then** their car can be obtained in the same request.

---

### Edge Cases

- A driver corrects their mileage *downwards* — a typo, or a replaced instrument cluster. **Resolved (D3): accepted.** Records already filed keep the figures they were filed with; only later records use the new value.
- A trip is filed while a manual mileage correction is in flight. Which value wins, and is the trip's distance still added on top?
- A driver types a manual `fuel` cost for a fill-up that is already carried across into the ledger. **Resolved (D2): the ledger shows both**, marked differently, and the driver deletes the manual one. Until they do, the total counts it twice — this is visible and driver-fixable by design rather than prevented.
- A carried-across entry is overwritten by the driver, and then its source fuel record is edited. The driver's figure wins; the source change must not silently overwrite it.
- A fuel record is deleted after its carried-across entry was overwritten. The entry it produced has to either survive as a manual entry or go with it — one or the other, not an orphan pointing at nothing.
- A document is saved with neither a file nor an expiry date. It is a type and nothing else; is that a record worth keeping?
- A document's expiry date is corrected from a past date to a future one — its state must move from expired back to valid.
- A fault is recorded with a severity but no description, from the roadside.
- A photo attached to a fault exceeds the size the service accepts.
- A driver deletes their account while records still reference them — trips, documents, costs and faults must all become inaccessible, and any stored files with them.
- A driver deletes their account and registers again with the same address.
- The monthly report is requested for a driver's first month, so there is no previous month to compare against.
- The fuel chart is requested when only one fill-up exists, so no distance-between-fills can be computed and efficiency is undefined for it.
- A car's mileage is corrected downwards after fill-ups exist, making a previously computed efficiency figure negative.
- The service schedule is read for a car with no scheduled intervals at all.
- An Arabic variant is missing for some catalogue entries but not others.
- A parking spot is corrected to coordinates in a different city.
- Costs, faults, documents and fuel records are requested for a car belonging to another driver.

## Requirements *(mandatory)*

### Functional Requirements

**Car record (US1)**

- **FR-001**: A driver MUST be able to correct the recorded mileage of their own car after registration.
- **FR-002**: A driver MUST be able to update their warranty coverage — whether one exists, its distance limit, and its expiry date — after registration.
- **FR-003**: The system MUST record a car's colour at registration and allow it to be changed afterwards.
- **FR-004**: The system MUST refuse any attempt by a driver to change a car that is not their own.
- **FR-005**: Records that capture mileage at the time of an event MUST use the car's current recorded mileage at that moment, so a correction is reflected in every subsequent record.

**Records the app collects (US2)**

- **FR-006**: The system MUST accept a document with no file attached, storing the type and expiry date alone.
- **FR-007**: The system MUST accept a document whose expiry date is in the past, and report such a document as expired.
- **FR-008**: A driver MUST be able to attach a file to a document that was created without one.
- **FR-009**: The system MUST accept a station name, a pump mileage reading and a fuel grade on a manually recorded fill-up, each of them optional.
- **FR-010**: When a fill-up is recorded without a pump reading, the system MUST fall back to the car's current recorded mileage.

**Account lifecycle (US3)**

- **FR-011**: A signed-in driver MUST be able to request deletion of their own account from within the app.
- **FR-012**: On deletion the system MUST make the driver's account, their car and all records referencing it inaccessible, including any stored files, and MUST end all their active sessions.
- **FR-013**: A password-reset request MUST return an identical response whether or not the address has an account.

**Costs (US4)**

- **FR-014**: A driver MUST be able to record a cost against their car with a date, a label, an amount and a category.
- **FR-015**: The system MUST support the category set the app already uses: fuel, service, insurance, tyres, warranty and other.
- **FR-042**: When a fuel record is filed, the system MUST make its spending appear in the car's cost ledger under the fuel category, without the driver entering it a second time.
- **FR-043**: When a maintenance entry is filed, the system MUST make its spending appear in the cost ledger under the service category, on the same terms.
- **FR-044**: The ledger MUST distinguish entries the driver typed in from entries carried across from a fuel or maintenance record, so that a duplicate can be recognised and removed.
- **FR-045**: A driver MUST be able to overwrite the amount on a carried-across entry, and totals MUST then use the driver's figure rather than the original.
- **FR-046**: When a fuel record or maintenance entry is corrected or removed, its carried-across ledger entry MUST follow, unless the driver has overwritten that entry's amount.
- **FR-016**: A driver MUST be able to list, correct and remove their own recorded costs.
- **FR-017**: The system MUST return costs only for cars belonging to the requesting driver.

**Faults (US5)**

- **FR-018**: A driver MUST be able to record a fault against their car with a date, a title, a severity, a description of the problem, what was done about it, and a note.
- **FR-019**: A driver MUST be able to attach a photo to a fault and retrieve it afterwards.
- **FR-020**: A driver MUST be able to list, correct, resolve and remove their own recorded faults.
- **FR-021**: The system MUST distinguish unresolved faults from resolved ones, and MUST make serious unresolved faults available alongside overdue services in the driver's attention list.

**Service schedule (US6)**

- **FR-022**: The system MUST return a car's whole service schedule, including intervals the car has already passed, distinguishing passed, current and upcoming.
- **FR-023**: The system MUST return each interval's checklist items with their names and prices in the same response as the schedule.
- **FR-024**: A driver MUST be able to add their own checklist lines, each with a label and a price, to a service interval, and those lines MUST persist.

**History detail (US7)**

- **FR-025**: The system MUST record a title, a workshop, a category and notes against a maintenance entry, so that work not tied to a scheduled interval is still described.
- **FR-026**: The system MUST record a trip's start time, end time, duration and top speed alongside its distance.
- **FR-027**: The system MUST record an address against a parking record, distinct from its label and its note.
- **FR-028**: A driver MUST be able to correct a saved parking record.

**Arabic (US8)**

- **FR-029**: The system MUST hold and return an Arabic name for service items and service centres, and an Arabic address for service centres, alongside the existing Latin values.
- **FR-030**: The system MUST return entries with no Arabic variant recorded in a way the client can still display.

**Insights (US9)**

- **FR-031**: The system MUST return, for a requested period, the driver's spend split by source, distance travelled, fill-up count, average fuel price and cost per kilometre, together with the equivalent figures for the preceding period.
- **FR-032**: The system MUST return a weekly breakdown of fuel spend, service spend and distance within the requested period.
- **FR-033**: The system MUST return a fuel-efficiency figure on each individual fill-up, so the series can be charted without recomputation.
- **FR-034**: The system MUST record what a driver paid for their car and when.
- **FR-035**: The system MUST return an estimated present value for a car, derived from its purchase figure, its age and its recorded mileage, together with the change against what was paid. The figure MUST be presented as an estimate, and MUST NOT depend on any external market-data source.

**Response contract (US10)**

- **FR-036**: Every collection response MUST use the same envelope.
- **FR-037**: The same resource MUST be returned in the same shape regardless of which route produced it.
- **FR-038**: The error field MUST have one consistent shape across validation failures and other failures.
- **FR-039**: Reading the signed-in driver MUST be able to return their car in the same response.

**Cross-cutting**

- **FR-040**: Every new record type MUST be readable and writable only by the driver who owns the car it belongs to, consistent with the existing rules for documents and fuel records.
- **FR-041**: Every new collection MUST be pageable, filterable and sortable in the same way as the existing collections.

### Key Entities

- **Car**: Gains a colour, a purchase figure and a purchase date, and becomes correctable after registration for mileage and warranty.
- **Cost**: A single item of spending on a car — when, what it was for, how much, and which category. Belongs to one car. Either typed in by the driver or carried across from a fuel record or maintenance entry, and it knows which it is and, if carried across, what it came from and whether the driver has since overwritten it.
- **Fault**: A recorded problem with a car — when, a title, how serious, what was wrong, what was done, a note, whether it is resolved, and an optional photo. Belongs to one car.
- **Document**: No longer requires a file, and accepts a past expiry date.
- **Fuel record**: Gains a station name, a pump mileage reading, a fuel grade and a computed efficiency figure.
- **Maintenance entry**: Gains a title, a workshop, a category and notes.
- **Trip**: Gains a start time, an end time, a duration and a top speed.
- **Parking record**: Gains an address, and becomes correctable.
- **Service item / Service centre**: Gain Arabic variants of their name, and of the centre's address.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every screen in the mobile app can be backed by the service; the gap report's count of blockers falls from 5 to 0 and its total of 21 items to 0.
- **SC-002**: A driver can correct their mileage and see the corrected figure used by the next record they file, without signing out.
- **SC-003**: Both forms that are rejected today — a document with no file and a past expiry date, and a fill-up carrying a station, a pump reading and a grade — save successfully on the first attempt.
- **SC-004**: A driver can delete their account entirely from within the app, satisfying both app stores' review requirement.
- **SC-005**: A password-reset request reveals nothing about whether an address is registered: responses for known and unknown addresses are byte-identical.
- **SC-006**: The Costs tab and the fault log both render real stored data rather than device-local data.
- **SC-007**: Opening the monthly report, the fuel chart or the service schedule takes one request each, regardless of how long the driver's history is.
- **SC-008**: The client decodes every collection with a single decoder and every failure with a single error model.
- **SC-009**: App launch recovers the signed-in driver and their car in one request rather than two.
- **SC-010**: No existing mobile or admin behaviour regresses: the full automated test suite passes, and every endpoint documented in the API guide continues to answer as documented except where this specification deliberately changes it.

## Assumptions

- **Arabic is stored, not translated.** Arabic values are held alongside the Latin ones and both are returned, so the client can switch language without a refetch. The gap report recommends this over resolving server-side from the request language, because the client caches both.
- **Missing Arabic falls back to Latin.** An entry with no Arabic variant returns the Latin value rather than a blank.
- **Account deletion is immediate from the driver's point of view.** The account stops working at once. Whether records are erased outright or retained briefly for recovery is an operational detail; the driver-visible outcome is the same.
- **Costs are one unified ledger.** Manual entries and entries carried across from fuel records and maintenance entries share a single ledger, so the Costs tab is the whole picture rather than a partial one.
- **Carried-across entries stay linked to their source** until a driver overwrites them, at which point the driver's figure takes precedence and later source changes leave it alone.
- **Double-counting is driver-visible, not prevented.** A driver who types a manual entry for spending that is already carried across will see it twice and can delete the duplicate. This is the user's explicit decision: keep both, let the driver overwrite, remove the manual one afterwards.
- **Cost categories are a fixed list.** The six the app already uses. Drivers cannot invent new ones in this feature.
- **Amounts stay in the currency already in use.** No multi-currency support is introduced.
- **Fault severity is a fixed, ordered list**, and "serious" for the attention list means the highest level.
- **One photo per fault**, matching the app's fault sheet.
- **Fault photos reuse the document file rules** — same accepted formats and size limit.
- **Efficiency figures reuse the existing calculation.** The per-fill-up figure is the same arithmetic already used for the all-time average, including its tank-percentage correction, applied to a single record.
- **The reporting period is a calendar month by default**, compared against the preceding calendar month.
- **"Present value" is derived, not sourced.** It is computed from the purchase figure, the car's age and its recorded mileage. No external market-data provider is introduced, and the figure is labelled an estimate. Comparable listings, which the app currently shows, are dropped — they cannot be produced without market data.
- **Mileage may be corrected in either direction**, and corrections do not rewrite history. Figures already computed on already-filed records stand.
- **Existing route and payload shapes may change.** The contract fixes in US10 alter responses the API guide already documents. The mobile client is the only consumer and is not yet released, so this is a safe moment to make them; after release it would not be.
- **The API guide is updated with this feature.** `docs/mobile-api.md` is part of the deliverable, not a follow-up.
- **Ownership rules are inherited.** New record types follow the ownership and permission model already applied to documents and fuel records rather than defining a new one.
- **Client-side work is out of scope.** Section 6 of the gap report — the email verification screen, the fuel-type selector, the file picker, the push headers — is work for the Flutter app and is not part of this feature.

## Out of Scope

- Any change to the Flutter client.
- Multi-car support. One car per driver remains the model.
- Multi-currency handling.
- Reworking reminders, gas-station check-in or fuel prices, which are built but unclaimed by the app. The gap report flags that reminders overlap the app's derived notifications and that ownership should be decided — that decision is not made here.
- Sourcing real market valuations from an external provider.

## Traceability

Every item in the gap report maps to a story and a requirement. Nothing is dropped.

| Gap | Item | Story | Requirements |
|---|---|---|---|
| B1 | No way to update the odometer | US1 | FR-001, FR-005 |
| B2 | Documents demand a file and refuse past dates | US2 | FR-006, FR-007, FR-008 |
| B3 | Manual fill-up drops odometer, station, fuel type | US2 | FR-009, FR-010 |
| B4 | The Costs tab has no resource behind it | US4 | FR-014 – FR-017, FR-042 – FR-046 |
| B5 | The issue log doesn't exist server-side | US5 | FR-018 – FR-021 |
| F1 | Service intervals arrive without their checklist | US6 | FR-023 |
| F2 | Only future intervals are fetchable | US6 | FR-022 |
| F3 | Custom services can't carry their own items | US6 | FR-024 |
| F4 | Maintenance logs lose what was done and who did it | US7 | FR-025 |
| F5 | Trips lose their timing and top speed | US7 | FR-026 |
| F6 | No Arabic anywhere in the payloads | US8 | FR-029, FR-030 |
| F7 | Parking has no address, and no way to correct one | US7 | FR-027, FR-028 |
| F8 | Car colour is collected and then thrown away | US1 | FR-003 |
| F9 | Warranty is write-once | US1 | FR-002 |
| A1 | Monthly report | US9 | FR-031, FR-032 |
| A2 | Consumption over time | US9 | FR-033 |
| A3 | Resale valuation | US9 | FR-034, FR-035 |
| C1 | Two fill-up shapes | US10 | FR-037 |
| C2 | Bare array from upcoming-services | US10 | FR-036 |
| C3 | `errors` flips type | US10 | FR-038 |
| C4 | `GET /auth/user` omits the car | US10 | FR-039 |
| S1 | No account deletion | US3 | FR-011, FR-012 |
| S2 | Forgot-password leaks registered addresses | US3 | FR-013 |
| S3 | Tank size collected but never asked for | — | Client-side; see Out of Scope |

## Resolved Decisions

The three decisions that shaped this specification, and what was chosen.

- **D1 — A car's "present value" is derived, not sourced.** Purchase price and date are recorded, and the present value is estimated from the figure paid, the car's age and its mileage. No market-data provider is introduced. The gap report called this out as needing a decision before anything else — "this is a market-data feature, not a logbook one". Consequence: the valuation screen keeps its headline number but loses comparable listings, and the figure must be presented as an estimate rather than an appraisal.

- **D2 — One unified cost ledger, with duplicates left visible.** All six categories stay. Fuel records and maintenance entries carry across into the ledger automatically, the driver can overwrite any carried-across amount, and a manual entry that duplicates one can be deleted. Consequence: this is more work than a manual-only ledger — carried-across entries have to track their source, survive its edits and deletions, and know when the driver has overridden them. In exchange the Costs tab shows the car's whole cost without anyone typing anything twice. The trade accepted here is that a driver *can* double-count themselves; the system makes that visible and fixable rather than impossible.

- **D3 — Mileage may be corrected in either direction.** A downward correction is accepted, because typos and replaced instrument clusters are both real. Records already filed keep the figures they were filed with; only later records use the corrected value. Consequence: a stored efficiency figure can look wrong after a large downward correction, and nothing recalculates it. This was chosen over refusing the correction, which would leave a driver permanently stuck with a fat-fingered reading and no way out from inside the app.
