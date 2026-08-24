# Quickstart: Identity & Authentication

**Feature**: 001-identity-auth
**Date**: 2026-04-30

A step-by-step guide to verify the authentication module works end-to-end.

---

## Prerequisites

- Laravel app running locally (e.g., `php artisan serve` on `http://localhost:8000`)
- Database migrated: `php artisan migrate`
- Sanctum installed and `api` guard configured in `config/auth.php`
- Mail driver configured (for forgot-password; `log` driver sufficient for local testing)

---

## Step 1 — Register a new user with a vehicle

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Ahmed Al-Rashid",
    "email": "ahmed@example.com",
    "password": "secret1234",
    "brand": "MG",
    "model": "One",
    "year": 2025,
    "current_km": 5000,
    "has_warranty": true,
    "warranty_limit_km": 100000,
    "warranty_expiry_date": "2028-12-31"
  }'
```

**Expected**: HTTP 201, response contains `user`, `vehicle`, and `token`.
Copy the `token` value for subsequent requests.

**Verify atomicity**: Temporarily break the Vehicle creation (e.g., remove a required column)
and confirm no User record is created.

---

## Step 2 — Login with existing credentials

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "ahmed@example.com",
    "password": "secret1234"
  }'
```

**Expected**: HTTP 200, response contains `user` and `token`.

---

## Step 3 — Fetch authenticated profile

```bash
curl -X GET http://localhost:8000/api/v1/auth/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {TOKEN_FROM_STEP_1_OR_2}"
```

**Expected**: HTTP 200, response contains `user` with nested `vehicles` array.

---

## Step 4 — Logout

```bash
curl -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {TOKEN}"
```

**Expected**: HTTP 200, `{ "message": "Logged out successfully." }`

Re-use the same token:

```bash
curl -X GET http://localhost:8000/api/v1/auth/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {SAME_TOKEN}"
```

**Expected**: HTTP 401 Unauthenticated — token is invalidated.

---

## Step 5 — Forgot password

```bash
curl -X POST http://localhost:8000/api/v1/auth/forgot-password \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "ahmed@example.com"}'
```

**Expected**: HTTP 200, status message. Check `storage/logs/laravel.log` for the reset link
when using the `log` mail driver.

---

## Validation Edge Cases

### Duplicate email on register

```bash
# Run Step 1 again with the same email
```
**Expected**: HTTP 422, error on `email` field.

### Wrong password on login

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "ahmed@example.com", "password": "wrongpassword"}'
```
**Expected**: HTTP 422, error on `email` field ("These credentials do not match our records.").

### Missing warranty fields when has_warranty is true

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test User",
    "email": "test2@example.com",
    "password": "secret1234",
    "brand": "Toyota",
    "model": "Corolla",
    "year": 2023,
    "current_km": 0,
    "has_warranty": true
  }'
```
**Expected**: HTTP 422, errors on `warranty_limit_km` and `warranty_expiry_date`.
