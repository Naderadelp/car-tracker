# Product Specification: CarLog API
**Module:** Phase 1 - Identity & Onboarding
**Tech Stack:** PHP 8.4, Laravel 13, Laravel Sanctum, MySQL

## 1. Context & Objectives
This module handles the core user identity, session management, and the initial vehicle onboarding process for a mobile-first vehicle tracking application. The API must act as a pure headless backend. Authentication is handled via stateless API tokens (Laravel Sanctum). Because the mobile application captures both user and vehicle data on the "Create account" screen, registration must be handled as a single, atomic database transaction.

## 2. Database Schema

### `users` table
| Column | Type | Modifiers | Description |
| :--- | :--- | :--- | :--- |
| `id` | bigint(20) | unsigned, primary key | |
| `name` | varchar(255) | not null | Full name of the user. |
| `email` | varchar(255) | not null, unique, index | Used for login. |
| `password` | varchar(255) | not null | Bcrypt hashed. |
| `remember_token` | varchar(100) | nullable | Standard Laravel remember token. |
| `timestamps` | timestamp | | |

### `vehicles` table
| Column | Type | Modifiers | Description |
| :--- | :--- | :--- | :--- |
| `id` | bigint(20) | unsigned, primary key | |
| `user_id` | bigint(20) | unsigned, foreign key | References `users(id)`. |
| `brand` | varchar(100) | not null | e.g., 'MG' |
| `model` | varchar(100) | not null | e.g., 'One' |
| `year` | integer | not null | e.g., 2025 |
| `current_mileage` | integer | not null, default 0 | Odometer reading in km. |
| `has_warranty` | boolean | not null, default false | |
| `warranty_limit_km` | integer | nullable | |
| `warranty_expiry_date` | date | nullable | |
| `timestamps` | timestamp | | |
| `deleted_at` | timestamp | nullable | Soft deletes. |

*(Note: Ensure default Laravel `personal_access_tokens` and `password_reset_tokens` migrations are included in the build).*

## 3. REST API Endpoints (v1)
**Base Route:** `/api/v1/auth`

### 3.1. Register Account & Onboard Vehicle
* **Endpoint:** `POST /register`
* **Access:** Public
* **Payload:**
    * `name`: string, required, max:255
    * `email`: string, required, email, max:255, unique:users
    * `password`: string, required, min:8
    * `brand`: string, required
    * `model`: string, required
    * `year`: integer, required, digits:4
    * `current_mileage`: integer, required, min:0
    * `has_warranty`: boolean, required
    * `warranty_limit_km`: integer, required_if:has_warranty,true
    * `warranty_expiry_date`: date, required_if:has_warranty,true
* **Business Logic:**
    1. Validate payload via `RegisterUserRequest`.
    2. Initiate `DB::beginTransaction()`.
    3. Hash password and create `User` record.
    4. Create `Vehicle` record linked to the new User's ID.
    5. `DB::commit()`. (If any step fails, `DB::rollBack()`).
    6. Generate a Sanctum token named `mobile_app_token`.
* **Response (201 Created):**
    * Returns success message, the `User` object, the `Vehicle` object, and the raw plaintext `token`.

### 3.2. Login
* **Endpoint:** `POST /login`
* **Access:** Public
* **Payload:**
    * `email`: string, required, email
    * `password`: string, required
    * `device_name`: string, optional (defaults to `mobile_app_token`)
* **Business Logic:**
    1. Validate payload via `LoginUserRequest`.
    2. Attempt to find user by email.
    3. Verify password hash using `Hash::check`.
    4. If failed, throw `ValidationException` on the email field.
    5. If successful, generate Sanctum token.
* **Response (200 OK):**
    * Returns success message, the `User` object, and the raw plaintext `token`.

### 3.3. Fetch Authenticated User
* **Endpoint:** `GET /user`
* **Access:** Protected (`auth:sanctum`)
* **Payload:** None
* **Response (200 OK):**
    * Returns the currently authenticated `User` object (should include eager-loaded `vehicles`).

### 3.4. Logout
* **Endpoint:** `POST /logout`
* **Access:** Protected (`auth:sanctum`)
* **Payload:** None
* **Business Logic:**
    1. Identify the current access token making the request.
    2. Delete the specific token using `$request->user()->currentAccessToken()->delete()`.
* **Response (200 OK):**
    * Returns success message.

### 3.5. Forgot Password
* **Endpoint:** `POST /forgot-password`
* **Access:** Public
* **Payload:**
    * `email`: string, required, email, exists:users
* **Business Logic:**
    1. Validate payload.
    2. Utilize Laravel's built-in `Password::sendResetLink()`.
    3. If successful, return 200 OK. If failed (e.g., throttling), throw standard validation exception.
* **Response (200 OK):**
    * Returns status message (e.g., "We have emailed your password reset link.").

## 4. Architectural Rules & Constraints
* **Validation:** Do not rely on inline controller validation. All endpoints MUST use dedicated FormRequest classes.
* **Transactions:** Any endpoint writing to multiple tables (like `/register`) MUST be wrapped in a database transaction.
* **Routing:** Do not use `apiResource`. Explicitly define the endpoints to prevent exposing unintended methods.
* **Responses:** Standardize all responses to return JSON using the `Responder` trait.