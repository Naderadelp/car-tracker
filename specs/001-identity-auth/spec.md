# Feature Specification: Identity & Authentication

**Feature Branch**: `001-identity-auth`
**Created**: 2026-04-30
**Status**: Draft
**Input**: carlog_auth_spec.md — Phase 1 Identity & Onboarding

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New User Registration with Vehicle Onboarding (Priority: P1)

A new user downloads the CarLog mobile app. On the single "Create Account" screen they enter
their personal details and their first vehicle's information together. On submission they are
immediately authenticated and taken to their vehicle dashboard — no second step required.

**Why this priority**: This is the entry point for all users. Without it no one can access the
system. It also captures the first vehicle atomically, enabling the core tracking value
proposition from the first session.

**Independent Test**: Can be fully tested by submitting a valid registration payload and
verifying that a user record, a linked vehicle record, and an authentication token are all
returned in a single response.

**Acceptance Scenarios**:

1. **Given** a visitor provides a valid name, email, password, and complete vehicle details,
   **When** they submit the registration form, **Then** the system creates both a user account
   and a linked vehicle record atomically, returns a 201 response containing the user object,
   the vehicle object, and a plaintext authentication token.

2. **Given** a visitor submits a registration with an email that already exists,
   **When** the system processes the request, **Then** it returns a validation error on the
   email field and creates no records.

3. **Given** a visitor provides `has_warranty: true` but omits `warranty_limit_km` or
   `warranty_expiry_date`, **When** submitted, **Then** the system returns validation errors
   for the missing warranty fields and creates no records.

4. **Given** an unexpected error occurs after the user record is saved but before the vehicle
   record is saved, **When** the transaction fails, **Then** neither the user nor the vehicle
   record persists (full rollback).

---

### User Story 2 - Returning User Login (Priority: P1)

A registered user opens the app and enters their email and password. They are authenticated and
receive a token granting access to all protected endpoints.

**Why this priority**: Existing users must be able to access the system. Login is a prerequisite
for every protected feature in the application.

**Independent Test**: Can be fully tested by submitting valid credentials and confirming a token
and user object are returned, then submitting invalid credentials and confirming a 422 error is
returned on the email field.

**Acceptance Scenarios**:

1. **Given** a user submits a correct email and password,
   **When** the system processes the login, **Then** it returns a 200 response with the user
   object and a plaintext authentication token.

2. **Given** a user submits a correct email but wrong password,
   **When** the system processes the login, **Then** it returns a validation error on the email
   field and issues no token.

3. **Given** a user optionally supplies a `device_name` field,
   **When** authenticated, **Then** the issued token is named after that device; if omitted the
   token is named `mobile_app_token`.

---

### User Story 3 - View Authenticated Profile (Priority: P2)

An authenticated user requests their profile. The response includes their account details and
all vehicles they have registered.

**Why this priority**: Provides the foundational "me" endpoint required by the mobile app on
every app launch to hydrate the user's session state.

**Independent Test**: Can be fully tested by authenticating, calling the profile endpoint, and
verifying the response contains the user object with at least one nested vehicle.

**Acceptance Scenarios**:

1. **Given** a user includes a valid token in their request,
   **When** they call the profile endpoint, **Then** the system returns their user object
   including all associated vehicles.

2. **Given** a request is made without a valid token or with an expired token,
   **When** processed, **Then** the system returns a 401 Unauthorized response.

---

### User Story 4 - Logout (Priority: P2)

An authenticated user chooses to log out from the current device. Only the token used for the
current session is invalidated; other device sessions remain active.

**Why this priority**: Security requirement. Users must be able to end a session without
affecting other devices.

**Independent Test**: Can be fully tested by logging out and then attempting to use the same
token — the system must reject it with a 401.

**Acceptance Scenarios**:

1. **Given** a user sends a logout request with a valid token,
   **When** processed, **Then** only that specific token is deleted and a 200 success message
   is returned.

2. **Given** the same token is used after logout,
   **When** sent to any protected endpoint, **Then** the system returns a 401 Unauthorized.

---

### User Story 5 - Forgot Password (Priority: P3)

A user who has forgotten their password requests a reset link by providing their registered
email address.

**Why this priority**: Essential for account recovery but not a blocker for the core
tracking functionality; users can still be onboarded by an admin path if needed in the short term.

**Independent Test**: Can be fully tested by submitting a registered email and confirming a
reset-link response is returned, then submitting an unregistered email and confirming a
validation error.

**Acceptance Scenarios**:

1. **Given** a user submits a registered email address,
   **When** the system processes the request, **Then** it sends a password reset link to that
   email and returns a 200 status message.

2. **Given** a user submits an email not found in the system,
   **When** processed, **Then** the system returns a validation error on the email field.

3. **Given** a user triggers the reset link request multiple times in rapid succession,
   **When** the rate limit is exceeded, **Then** the system returns a throttle/validation error
   and does not send additional emails.

---

### Edge Cases

- What happens when the database is unavailable during registration? The transaction fails,
  no partial records are saved, and the user receives a 500 error.
- What happens when a token is manually deleted from the database while the user is active?
  The next protected request returns a 401 Unauthorized.
- What happens if `year` is not a valid 4-digit integer? The system returns a validation error
  before any database operation occurs.
- What happens if `current_km` is negative? The system rejects it as invalid (min: 0).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow a new user to register by providing personal details and
  first vehicle details in a single request, creating both records atomically.
- **FR-002**: The system MUST validate all registration input fields before any database write;
  invalid input MUST return descriptive field-level errors.
- **FR-003**: The system MUST issue a plaintext authentication token upon successful registration
  and upon successful login.
- **FR-004**: The system MUST allow a registered user to authenticate using their email and
  password.
- **FR-005**: The system MUST allow an authenticated user to retrieve their own profile data
  including all associated vehicles.
- **FR-006**: The system MUST allow an authenticated user to invalidate only their current
  session token without affecting other active sessions.
- **FR-007**: The system MUST allow a user to request a password reset link sent to their
  registered email address.
- **FR-008**: Warranty fields (`warranty_limit_km`, `warranty_expiry_date`) MUST only be
  required when `has_warranty` is true.
- **FR-009**: All write operations touching multiple records MUST be wrapped in a database
  transaction that rolls back completely on any failure.
- **FR-010**: All validation MUST be performed through dedicated request validation classes —
  not inline in controller methods.

### Key Entities *(include if feature involves data)*

- **User**: Represents an account holder. Key attributes: name, email (unique), password
  (hashed). Central entity all other domain objects belong to.
- **Vehicle**: Represents a registered vehicle. Key attributes: brand, model, year,
  current km, warranty status, warranty expiry, warranty km limit. Belongs to a User;
  supports soft-deletion.
- **Access Token**: Represents an active authenticated session for a specific device. Named per
  device; scoped to one user; independently revocable.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new user can complete registration and receive an authentication token in a
  single interaction, with no additional steps required.
- **SC-002**: Invalid registration input is rejected before any data is persisted; the user
  receives field-level error messages.
- **SC-003**: A partial failure during registration (e.g., vehicle creation fails) results in
  zero records saved — no orphaned user records exist in the system.
- **SC-004**: An authenticated user can retrieve their profile including all vehicles within
  one request.
- **SC-005**: A user logging out from one device does not affect the sessions of any other
  device they are logged into.
- **SC-006**: 100% of password reset requests for registered emails result in a reset link
  being sent; unregistered emails are rejected with a clear error message.
- **SC-007**: All authentication endpoints respond within acceptable time for a mobile app
  experience — users do not perceive noticeable delay during login or registration.

## Clarifications

### Session 2026-04-30

- Q: Should the implementation use DDD `src/Domain/` folder structure or standard Laravel MVC `app/` structure? → A: Standard Laravel `app/` folders with Repository pattern, Form Requests, and Responder trait preserved (Option A).
- Q: Should API routes be versioned (e.g., `/api/v1/auth`)? → A: No versioning — routes use `/api/auth/` prefix only.

## Assumptions

- The application is a mobile-first, API-only backend; there is no server-rendered web
  interface for authentication flows.
- Each user registers with exactly one vehicle at account creation time; additional vehicles
  may be added in a later phase.
- Password reset is handled entirely via email link; in-app token-based reset flows are out of
  scope for this phase.
- All API responses are JSON; no HTML or redirect responses are returned from auth endpoints.
- A "device name" for token naming defaults to `mobile_app_token` when not supplied by the
  client; the mobile app may supply a device-specific name.
- Email delivery for password reset relies on the platform's configured mail service; delivery
  success is outside this module's scope.
- Rate limiting on the forgot-password endpoint follows the platform's default throttle
  configuration; custom rate-limit tuning is out of scope for Phase 1.
