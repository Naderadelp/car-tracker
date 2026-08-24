# API Contracts: Identity & Authentication

**Base Route**: `/api/v1/auth`
**Format**: JSON
**Auth**: Laravel Sanctum (Bearer token for protected endpoints)

---

## POST /api/v1/auth/register

**Access**: Public
**Purpose**: Create a new user account and onboard their first vehicle atomically.

### Request

```json
{
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
}
```

**Field rules**:

| Field                  | Type    | Rules                                        |
|------------------------|---------|----------------------------------------------|
| `name`                 | string  | required, max:255                            |
| `email`                | string  | required, email, max:255, unique:users       |
| `password`             | string  | required, min:8                              |
| `brand`                | string  | required                                     |
| `model`                | string  | required                                     |
| `year`                 | integer | required, digits:4                           |
| `current_km`      | integer | required, min:0                              |
| `has_warranty`         | boolean | required                                     |
| `warranty_limit_km`    | integer | required_if:has_warranty,true                |
| `warranty_expiry_date` | date    | required_if:has_warranty,true                |

### Response — 201 Created

```json
{
  "message": "Account created successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Ahmed Al-Rashid",
      "email": "ahmed@example.com",
      "created_at": "2026-04-30T10:00:00.000000Z",
      "updated_at": "2026-04-30T10:00:00.000000Z"
    },
    "vehicle": {
      "id": 1,
      "user_id": 1,
      "brand": "MG",
      "model": "One",
      "year": 2025,
      "current_km": 5000,
      "has_warranty": true,
      "warranty_limit_km": 100000,
      "warranty_expiry_date": "2028-12-31",
      "created_at": "2026-04-30T10:00:00.000000Z",
      "updated_at": "2026-04-30T10:00:00.000000Z"
    },
    "token": "1|plaintext_token_here"
  }
}
```

### Response — 422 Unprocessable Entity (validation failure)

```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

---

## POST /api/v1/auth/login

**Access**: Public
**Purpose**: Authenticate a registered user and issue a session token.

### Request

```json
{
  "email": "ahmed@example.com",
  "password": "secret1234",
  "device_name": "iphone_15_pro"
}
```

**Field rules**:

| Field         | Type   | Rules                         |
|---------------|--------|-------------------------------|
| `email`       | string | required, email               |
| `password`    | string | required                      |
| `device_name` | string | optional (default: `mobile_app_token`) |

### Response — 200 OK

```json
{
  "message": "Login successful.",
  "data": {
    "user": {
      "id": 1,
      "name": "Ahmed Al-Rashid",
      "email": "ahmed@example.com",
      "created_at": "2026-04-30T10:00:00.000000Z",
      "updated_at": "2026-04-30T10:00:00.000000Z"
    },
    "token": "2|plaintext_token_here"
  }
}
```

### Response — 422 Unprocessable Entity (wrong credentials)

```json
{
  "message": "These credentials do not match our records.",
  "errors": {
    "email": ["These credentials do not match our records."]
  }
}
```

---

## GET /api/v1/auth/user

**Access**: Protected — requires `Authorization: Bearer {token}` header
**Purpose**: Retrieve the authenticated user's profile including all their vehicles.

### Request

No body. Token in header:
```
Authorization: Bearer 2|plaintext_token_here
```

### Response — 200 OK

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Ahmed Al-Rashid",
      "email": "ahmed@example.com",
      "vehicles": [
        {
          "id": 1,
          "brand": "MG",
          "model": "One",
          "year": 2025,
          "current_km": 5000,
          "has_warranty": true,
          "warranty_limit_km": 100000,
          "warranty_expiry_date": "2028-12-31",
          "created_at": "2026-04-30T10:00:00.000000Z",
          "updated_at": "2026-04-30T10:00:00.000000Z"
        }
      ],
      "created_at": "2026-04-30T10:00:00.000000Z",
      "updated_at": "2026-04-30T10:00:00.000000Z"
    }
  }
}
```

### Response — 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

---

## POST /api/v1/auth/logout

**Access**: Protected — requires `Authorization: Bearer {token}` header
**Purpose**: Invalidate the current session token only.

### Request

No body. Token in header.

### Response — 200 OK

```json
{
  "message": "Logged out successfully."
}
```

---

## POST /api/v1/auth/forgot-password

**Access**: Public
**Purpose**: Send a password reset link to a registered email address.

### Request

```json
{
  "email": "ahmed@example.com"
}
```

**Field rules**:

| Field   | Type   | Rules                    |
|---------|--------|--------------------------|
| `email` | string | required, email, exists:users |

### Response — 200 OK

```json
{
  "message": "We have emailed your password reset link."
}
```

### Response — 422 Unprocessable Entity (email not found or throttled)

```json
{
  "message": "We can't find a user with that email address.",
  "errors": {
    "email": ["We can't find a user with that email address."]
  }
}
```

---

## POST /api/v1/auth/reset-password

**Access**: Public
**Purpose**: Reset the user's password using the token received via email deep link.

### Request

```json
{
  "token": "abc123...",
  "email": "ahmed@example.com",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}
```

**Field rules**:

| Field                    | Type   | Rules                              |
|--------------------------|--------|------------------------------------|
| `token`                  | string | required                           |
| `email`                  | string | required, email, exists:users      |
| `password`               | string | required, min:8, confirmed         |
| `password_confirmation`  | string | required                           |

### Response — 200 OK

```json
{
  "message": "Your password has been reset."
}
```

### Response — 422 Unprocessable Entity (invalid/expired token)

```json
{
  "message": "This password reset token is invalid.",
  "errors": {
    "email": ["This password reset token is invalid."]
  }
}
```
