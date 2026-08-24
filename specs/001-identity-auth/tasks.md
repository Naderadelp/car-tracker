---
description: "Task list for Identity & Authentication (001-identity-auth)"
---

# Tasks: Identity & Authentication

**Input**: Design documents from `specs/001-identity-auth/`
**Prerequisites**: plan.md ✅ spec.md ✅ data-model.md ✅ contracts/auth-api.md ✅ research.md ✅ quickstart.md ✅

**Tests**: Not requested — no test tasks generated.

**Organization**: Tasks grouped by user story. Each story is independently testable.

## Format: `[ID] [P?] [Story?] Description — file path`

- **[P]**: Can run in parallel (different files, no shared dependencies)
- **[Story]**: Which user story this task belongs to (US1–US5)

## Path Conventions

```
src/Domain/{Module}/Entities/               ← lean models + trait files
src/Domain/{Module}/Entities/Traits/        ← Relations / Attributes traits
src/Domain/{Module}/Http/Controllers/       ← controllers (use Responder trait)
src/Domain/{Module}/Http/Requests/          ← Form Request classes
src/Domain/{Module}/Http/Resources/         ← API Resource classes
src/Domain/{Module}/Repositories/Contracts/ ← repository interfaces
src/Domain/{Module}/Repositories/Eloquent/  ← repository implementations
src/Domain/{Module}/Providers/              ← service providers
src/Infrastructure/AbstractRepositories/    ← base EloquentRepository
src/Infrastructure/l5/Contracts/            ← RepositoryInterface
src/Common/Traits/                          ← Responder trait
database/migrations/                        ← migration files
routes/                                     ← API route files
```

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Laravel project initialization and core infrastructure that every domain module depends on.

- [x] T0\0 Install Laravel 13 with PHP 8.4 — run `composer create-project laravel/laravel . "^13.0"` and verify `composer.json`
- [x] T0\0 [P] Install and configure Laravel Sanctum — run `composer require laravel/sanctum` and publish config to `config/sanctum.php`
- [x] T0\0 [P] Install Spatie Laravel-Activity-Log — run `composer require spatie/laravel-activitylog` and publish config to `config/activitylog.php`
- [x] T0\0 Configure MySQL connection in `.env` (DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
- [x] T0\0 Create RepositoryInterface contract in `src/Infrastructure/l5/Contracts/RepositoryInterface.php` (declare: all(), find(), findWhere(), findWhereFirst(), paginate(), create(), update(), delete(), with(), scopeQuery(), spatie())
- [x] T0\0 Create base EloquentRepository in `src/Infrastructure/AbstractRepositories/EloquentRepository.php` (implement RepositoryInterface; expose spatie() method, $allowedFilters, $allowedIncludes, $allowedSorts, $allowedDefaultSorts, $allowedFiltersExact, $allowedFilterScopes)
- [x] T0\0 Create Responder trait in `src/Common/Traits/Responder.php` (methods: setData(), useCollection(), setApiResponse(), response())

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database schema and service providers that ALL user stories depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T0\0 Create users table migration in `database/migrations/` (columns: id, name varchar(255), email varchar(255) unique, password varchar(255), remember_token varchar(100) nullable, timestamps)
- [x] T0\0 [P] Create vehicles table migration in `database/migrations/` (columns: id, user_id FK→users, brand varchar(100), model varchar(100), year int, current_km int default 0, has_warranty bool default false, warranty_limit_km int nullable, warranty_expiry_date date nullable, timestamps, deleted_at nullable)
- [x] T0\0 [P] Publish Sanctum and password reset migrations — run `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` (creates personal_access_tokens table migration)
- [x] T0\0 Run all migrations — `php artisan migrate`
- [x] T0\0 Create UserServiceProvider in `src/Domain/User/Providers/UserServiceProvider.php` and register in `bootstrap/providers.php`
- [x] T0\0 [P] Create VehicleServiceProvider in `src/Domain/Vehicle/Providers/VehicleServiceProvider.php` and register in `bootstrap/providers.php`
- [x] T0\0 [P] Create AuthServiceProvider in `src/Domain/Auth/Providers/AuthServiceProvider.php` and register in `bootstrap/providers.php`

**Checkpoint**: Foundation ready — all user story implementation can now begin.

---

## Phase 3: User Story 1 — Registration with Vehicle Onboarding (Priority: P1) 🎯 MVP

**Goal**: New user submits personal + vehicle details in one request; system atomically creates both records and returns a Sanctum token.

**Independent Test**: `POST /api/v1/auth/register` with valid payload → HTTP 201 containing user object, vehicle object, and token. Re-run with same email → HTTP 422. Submit with `has_warranty:true` but no warranty fields → HTTP 422.

### Implementation for User Story 1

- [x] T0\0 [P] [US1] Create User entity in `src/Domain/User/Entities/User.php` (use HasApiTokens, LogsActivity; $fillable: name, email, password; $table: users; $hidden: password, remember_token; getActivitylogOptions() logging all attributes)
- [x] T0\0 [P] [US1] Create UserRelations trait in `src/Domain/User/Entities/Traits/UserRelations.php` (hasMany Vehicle::class)
- [x] T0\0 [P] [US1] Create UserAttributes trait in `src/Domain/User/Entities/Traits/UserAttributes.php` (casts: email_verified_at → datetime, created_at/updated_at → ISO string accessors if needed)
- [x] T0\0 [P] [US1] Create Vehicle entity in `src/Domain/Vehicle/Entities/Vehicle.php` (use SoftDeletes, LogsActivity; $fillable: user_id, brand, model, year, current_km, has_warranty, warranty_limit_km, warranty_expiry_date; casts: has_warranty→boolean, warranty_expiry_date→date; getActivitylogOptions())
- [x] T0\0 [P] [US1] Create VehicleRelations trait in `src/Domain/Vehicle/Entities/Traits/VehicleRelations.php` (belongsTo User::class)
- [x] T0\0 [P] [US1] Create VehicleAttributes trait in `src/Domain/Vehicle/Entities/Traits/VehicleAttributes.php` (casts: year→integer, current_km→integer)
- [x] T0\0 [US1] Create UserRepository contract in `src/Domain/User/Repositories/Contracts/UserRepository.php` (extends RepositoryInterface — no additional methods needed for Phase 1)
- [x] T0\0 [US1] Create UserRepositoryEloquent in `src/Domain/User/Repositories/Eloquent/UserRepositoryEloquent.php` (extends EloquentRepository, implements UserRepository; model() returns User::class); bind in UserServiceProvider
- [x] T0\0 [P] [US1] Create VehicleRepository contract in `src/Domain/Vehicle/Repositories/Contracts/VehicleRepository.php` (extends RepositoryInterface)
- [x] T0\0 [P] [US1] Create VehicleRepositoryEloquent in `src/Domain/Vehicle/Repositories/Eloquent/VehicleRepositoryEloquent.php` (extends EloquentRepository, implements VehicleRepository; model() returns Vehicle::class); bind in VehicleServiceProvider
- [x] T0\0 [P] [US1] Create UserResource in `src/Domain/User/Http/Resources/UserResource.php` (fields: id, name, email, created_at→toISOString(), updated_at→toISOString())
- [x] T0\0 [P] [US1] Create VehicleResource in `src/Domain/Vehicle/Http/Resources/VehicleResource.php` (fields: id, user_id, brand, model, year, current_km, has_warranty, warranty_limit_km, warranty_expiry_date, created_at→toISOString(), updated_at→toISOString())
- [x] T0\0 [US1] Create RegisterUserRequest in `src/Domain/Auth/Http/Requests/RegisterUserRequest.php` (rules: name required max:255, email required email max:255 unique:users, password required min:8, brand required, model required, year required integer digits:4, current_km required integer min:0, has_warranty required boolean, warranty_limit_km required_if:has_warranty,true integer, warranty_expiry_date required_if:has_warranty,true date)
- [x] T0\0 [US1] Create AuthController in `src/Domain/Auth/Http/Controllers/AuthController.php` with `use Responder`; inject UserRepository and VehicleRepository via constructor; implement register() method: DB::beginTransaction() → userRepository->create() → vehicleRepository->create() with user_id → DB::commit() → $user->createToken('mobile_app_token') → setApiResponse(201) → response()
- [x] T0\0 [US1] Define POST /api/v1/auth/register explicit route in `routes/api.php` pointing to AuthController@register (no apiResource)

**Checkpoint**: User Story 1 fully functional — registration creates user + vehicle atomically, returns 201 with token.

---

## Phase 4: User Story 2 — Returning User Login (Priority: P1)

**Goal**: Registered user authenticates with email + password and receives a Sanctum token.

**Independent Test**: `POST /api/v1/auth/login` with correct credentials → HTTP 200 with user + token. Wrong password → HTTP 422 on email field.

### Implementation for User Story 2

- [x] T0\0 [US2] Create LoginUserRequest in `src/Domain/Auth/Http/Requests/LoginUserRequest.php` (rules: email required email, password required, device_name optional string)
- [x] T0\0 [US2] Add login() method to AuthController in `src/Domain/Auth/Http/Controllers/AuthController.php` (validate via LoginUserRequest; find user by email via userRepository; Hash::check password; on failure throw ValidationException on email field; on success $user->createToken($deviceName ?? 'mobile_app_token'); setData + useCollection UserResource + setApiResponse(200) + response())
- [x] T0\0 [US2] Add POST /api/v1/auth/login explicit route in `routes/api.php` pointing to AuthController@login

**Checkpoint**: User Stories 1 and 2 both independently functional.

---

## Phase 5: User Story 3 — View Authenticated Profile (Priority: P2)

**Goal**: Authenticated user retrieves their profile including all associated vehicles in one request.

**Independent Test**: `GET /api/v1/auth/user` with valid Bearer token → HTTP 200 with user + nested vehicles array. Without token → HTTP 401.

### Implementation for User Story 3

- [x] T0\0 [US3] Update UserResource in `src/Domain/User/Http/Resources/UserResource.php` to include `'vehicles' => VehicleResource::collection($this->whenLoaded('vehicles'))`
- [x] T0\0 [US3] Add me() method to AuthController in `src/Domain/Auth/Http/Controllers/AuthController.php` (authorize via auth:sanctum middleware — no explicit authorize() call needed; load $request->user() with vehicles via userRepository->with(['vehicles'])->find(auth()->id()); setData + useCollection UserResource + response())
- [x] T0\0 [US3] Add GET /api/v1/auth/user route wrapped in `Route::middleware('auth:sanctum')` group in `routes/api.php` pointing to AuthController@me

**Checkpoint**: User Stories 1, 2, and 3 all independently functional.

---

## Phase 6: User Story 4 — Logout (Priority: P2)

**Goal**: Authenticated user invalidates only their current session token; other device sessions remain active.

**Independent Test**: `POST /api/v1/auth/logout` with valid token → HTTP 200. Re-use the same token on any protected endpoint → HTTP 401.

### Implementation for User Story 4

- [x] T0\0 [US4] Add logout() method to AuthController in `src/Domain/Auth/Http/Controllers/AuthController.php` ($request->user()->currentAccessToken()->delete(); setApiResponse(fn() => response()->json(['message' => 'Logged out successfully.'], 200)); response())
- [x] T0\0 [US4] Add POST /api/v1/auth/logout route inside `auth:sanctum` middleware group in `routes/api.php` pointing to AuthController@logout

**Checkpoint**: All P1 and P2 user stories fully functional.

---

## Phase 7: User Story 5 — Forgot Password (Priority: P3)

**Goal**: User requests a password reset link via email; system sends it if the email is registered.

**Independent Test**: `POST /api/v1/auth/forgot-password` with registered email → HTTP 200 with status message and reset link in logs. Unregistered email → HTTP 422.

### Implementation for User Story 5

- [x] T0\0 [US5] Configure mail driver for local testing — set `MAIL_MAILER=log` in `.env` so reset emails are written to `storage/logs/laravel.log`
- [x] T0\0 [US5] Create ForgotPasswordRequest in `src/Domain/Auth/Http/Requests/ForgotPasswordRequest.php` (rules: email required email exists:users)
- [x] T0\0 [US5] Add forgotPassword() method to AuthController in `src/Domain/Auth/Http/Controllers/AuthController.php` (validate via ForgotPasswordRequest; call Password::sendResetLink(['email' => $request->email]); on STATUS_RESET_LINK_SENT setApiResponse 200 with status message; on failure throw ValidationException on email field)
- [x] T0\0 [US5] Add POST /api/v1/auth/forgot-password explicit public route in `routes/api.php` pointing to AuthController@forgotPassword

**Checkpoint**: All 5 user stories independently functional.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Observability, cleanup, and end-to-end verification across all stories.

- [x] T0\0 [P] Add $logAttributes and getActivitylogOptions() returning LogOptions::defaults()->logOnly(['*']) to User entity in `src/Domain/User/Entities/User.php`
- [x] T0\0 [P] Add $logAttributes and getActivitylogOptions() returning LogOptions::defaults()->logOnly(['*']) to Vehicle entity in `src/Domain/Vehicle/Entities/Vehicle.php`
- [x] T0\0 [P] Run sync:permissions command — `php artisan sync:permissions` (ensure user and vehicle models are in $models array in `src/Domain/User/Database/Seeds/RolePermissionsSeederTableSeeder.php`)
- [x] T0\0 Verify all 5 endpoints against quickstart.md scenarios (`specs/001-identity-auth/quickstart.md`) — registration, login, profile, logout, forgot-password including validation edge cases

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately; T002, T003 parallelizable
- **Foundational (Phase 2)**: Depends on Phase 1 completion — blocks all user stories; T009, T010, T013, T014 parallelizable with T008/T012
- **User Story 1 (Phase 3)**: Depends on Phase 2 — T015–T020 and T025–T026 parallelizable; T021–T024 sequential (contract before impl); T027 before T028; T028 before T029
- **User Story 2 (Phase 4)**: Depends on Phase 2 — T030 before T031; T031 before T032
- **User Story 3 (Phase 5)**: Depends on Phase 2 + UserResource (T025) — T033 before T034; T034 before T035
- **User Story 4 (Phase 6)**: Depends on Phase 2 only — T036 before T037
- **User Story 5 (Phase 7)**: Depends on Phase 2 only — T038, T039 parallelizable; T040 before T041
- **Polish (Phase 8)**: Depends on all user stories complete — T042, T043, T044 parallelizable

### User Story Dependencies

- **US1 (P1)**: Foundation ready → no other story dependency
- **US2 (P1)**: Foundation ready → no other story dependency (UserResource already created in US1)
- **US3 (P2)**: Requires US1 (UserResource with vehicles) → otherwise independent
- **US4 (P2)**: Foundation ready → no other story dependency
- **US5 (P3)**: Foundation ready → no other story dependency

### Within Each User Story

- Entities before repositories
- Repository contract before Eloquent implementation
- Eloquent implementation before controller method
- Form Request before controller method
- Controller method before route definition

### Parallel Opportunities

- T002, T003 (installs) — run together
- T009, T010, T013, T014 — run together after T008/T012
- T015, T016, T017, T018, T019, T020 (entity files) — all different files
- T021, T023 (repository contracts) — different files
- T025, T026 (resources) — different files
- T042, T043, T044 (observability) — different files

---

## Parallel Example: User Story 1

```bash
# Run entity files together (T015–T020):
Task: "Create User entity in src/Domain/User/Entities/User.php"
Task: "Create UserRelations trait in src/Domain/User/Entities/Traits/UserRelations.php"
Task: "Create UserAttributes trait in src/Domain/User/Entities/Traits/UserAttributes.php"
Task: "Create Vehicle entity in src/Domain/Vehicle/Entities/Vehicle.php"
Task: "Create VehicleRelations trait in src/Domain/Vehicle/Entities/Traits/VehicleRelations.php"
Task: "Create VehicleAttributes trait in src/Domain/Vehicle/Entities/Traits/VehicleAttributes.php"

# Then run resources together (T025–T026):
Task: "Create UserResource in src/Domain/User/Http/Resources/UserResource.php"
Task: "Create VehicleResource in src/Domain/Vehicle/Http/Resources/VehicleResource.php"
```

---

## Implementation Strategy

### MVP First (User Stories 1 & 2 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL)
3. Complete Phase 3: User Story 1 (Register + Vehicle)
4. Complete Phase 4: User Story 2 (Login)
5. **STOP and VALIDATE** via `quickstart.md` Steps 1–2
6. Ship MVP — users can register and login

### Incremental Delivery

1. Setup + Foundational → infrastructure ready
2. US1 (Register) → test independently → first working endpoint
3. US2 (Login) → test independently → full auth flow
4. US3 (Profile) → test independently
5. US4 (Logout) → test independently → complete session lifecycle
6. US5 (Forgot Password) → test independently → full identity module

### Parallel Team Strategy

With multiple developers after Phase 2 completes:
- Developer A: US1 (Register — most complex, DB transaction)
- Developer B: US2 + US4 (Login + Logout — simple token operations)
- Developer C: US3 + US5 (Profile + Forgot Password — independent)

---

## Notes

- [P] tasks = different files, no shared dependencies at the time they run
- [USn] label maps each task to a specific user story for traceability
- Each user story is independently completable and testable via `quickstart.md`
- No `apiResource` routes — all routes are explicit per the constitution and carlog_auth_spec.md
- Auth endpoints (register, login, forgot-password) are public; profile and logout use `auth:sanctum` middleware
- Principle V deviation (no `$this->authorize()`) is justified in `plan.md` Complexity Tracking
