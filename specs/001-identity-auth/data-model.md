# Data Model: Identity & Authentication

**Feature**: 001-identity-auth
**Date**: 2026-04-30

---

## Entities

### User

**Table**: `users`
**Module**: `src/Domain/User/`
**Soft Deletes**: No
**Activity Log**: Yes (`LogsActivity`)

| Column           | Type          | Modifiers                        | Description                  |
|------------------|---------------|----------------------------------|------------------------------|
| `id`             | bigint(20)    | unsigned, PK, auto-increment     |                              |
| `name`           | varchar(255)  | not null                         | Full name of the user        |
| `email`          | varchar(255)  | not null, unique, indexed        | Login identifier             |
| `password`       | varchar(255)  | not null                         | Bcrypt hashed                |
| `remember_token` | varchar(100)  | nullable                         | Standard Laravel token       |
| `created_at`     | timestamp     | nullable                         |                              |
| `updated_at`     | timestamp     | nullable                         |                              |

**Relationships**:
- `hasMany(Vehicle::class)` — a user owns one or more vehicles
- `hasMany(PersonalAccessToken::class)` — via Sanctum (implicit)

**Validation rules (registration)**:
- `name`: required, string, max:255
- `email`: required, email, max:255, unique:users
- `password`: required, string, min:8

---

### Vehicle

**Table**: `vehicles`
**Module**: `src/Domain/Vehicle/`
**Soft Deletes**: Yes (`deleted_at`)
**Activity Log**: Yes (`LogsActivity`)

| Column                 | Type         | Modifiers                              | Description                          |
|------------------------|--------------|----------------------------------------|--------------------------------------|
| `id`                   | bigint(20)   | unsigned, PK, auto-increment           |                                      |
| `user_id`              | bigint(20)   | unsigned, FK → `users(id)`, not null   | Owning user                          |
| `brand`                | varchar(100) | not null                               | e.g., 'MG'                           |
| `model`                | varchar(100) | not null                               | e.g., 'One'                          |
| `year`                 | integer      | not null                               | 4-digit year, e.g., 2025             |
| `current_km`      | integer      | not null, default 0                    | Odometer reading in km               |
| `has_warranty`         | boolean      | not null, default false                |                                      |
| `warranty_limit_km`    | integer      | nullable                               | Required when `has_warranty` is true |
| `warranty_expiry_date` | date         | nullable                               | Required when `has_warranty` is true |
| `created_at`           | timestamp    | nullable                               |                                      |
| `updated_at`           | timestamp    | nullable                               |                                      |
| `deleted_at`           | timestamp    | nullable                               | Soft delete marker                   |

**Relationships**:
- `belongsTo(User::class)` — vehicle is owned by one user

**Validation rules (registration)**:
- `brand`: required, string
- `model`: required, string
- `year`: required, integer, digits:4
- `current_km`: required, integer, min:0
- `has_warranty`: required, boolean
- `warranty_limit_km`: required_if:has_warranty,true, integer
- `warranty_expiry_date`: required_if:has_warranty,true, date

---

### Personal Access Token (Sanctum — framework-managed)

**Table**: `personal_access_tokens`
**Module**: Framework (not domain-owned)

| Column       | Type         | Description                          |
|--------------|--------------|--------------------------------------|
| `id`         | bigint       | PK                                   |
| `tokenable`  | morphs       | Polymorphic owner (User)             |
| `name`       | varchar(255) | Device name (`mobile_app_token`)     |
| `token`      | varchar(64)  | SHA-256 hashed token                 |
| `abilities`  | text         | JSON array of allowed abilities      |
| `last_used_at` | timestamp  | nullable                             |
| `expires_at` | timestamp    | nullable                             |
| `created_at` | timestamp    |                                      |
| `updated_at` | timestamp    |                                      |

---

## State Transitions

### Registration flow

```
[Request received]
  → Validate (RegisterUserRequest)
  → DB::beginTransaction()
    → Create User
    → Create Vehicle (linked to User)
  → DB::commit() → Generate Sanctum token → 201 response
  → DB::rollBack() on any failure → 422 / 500 response
```

### Login flow

```
[Request received]
  → Validate (LoginUserRequest)
  → Find User by email
  → Hash::check(password)
    → FAIL: ValidationException on email field → 422
    → PASS: Generate Sanctum token → 200 response
```

### Logout flow

```
[Authenticated request]
  → Identify current token via $request->user()->currentAccessToken()
  → Delete token
  → 200 response
```

---

## Required Migrations

1. `create_users_table` — standard Laravel (already provided)
2. `create_vehicles_table` — new migration for this feature
3. `create_personal_access_tokens_table` — standard Sanctum migration
4. `create_password_reset_tokens_table` — standard Laravel (already provided)
