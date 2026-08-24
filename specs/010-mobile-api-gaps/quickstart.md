# Quickstart: Close the CarLog Mobile API Gaps

**Feature**: `010-mobile-api-gaps` · **Branch**: `010-mobile-api-gaps`

## Before you touch anything

```bash
# The single most important check in this repository.
ls bootstrap/cache/config.php
```

If that file **exists**, delete it with `php artisan config:clear` before running
the test suite. A cached config overrides the `<env>` values in `phpunit.xml`,
which points `RefreshDatabase` at the real PostgreSQL database instead of sqlite.
That has already destroyed this project's local database once.

**Never run** `php artisan config:cache` or `php artisan optimize` in this
repository, and never run any of the destructive migration commands
(`fresh`, `refresh`, `wipe`) against the local or production connection.

## Local setup

```bash
composer install
php artisan migrate            # plain migrate only — never the destructive variants
php artisan sync:permissions   # after any change to $models in RolePermissionsSeeder
php artisan serve
```

Local database is PostgreSQL at `127.0.0.1`, database `car_tracker`. The
`10.10.67.174` lines in `.env` are commented out and are not the active target.

## Running the tests

```bash
ls bootstrap/cache/config.php   # must not exist
php artisan test
```

The suite runs against sqlite `:memory:`. Phase A rewrites response shapes across
the whole project, so expect a large number of assertion updates in that phase
and a green suite at the end of it — a red suite after Phase A is not "expected
churn", it is unfinished work.

## Verifying each phase

| Phase | Verify by |
|---|---|
| A | `GET /cars/{car}/documents` returns 200 on PostgreSQL (it throws today); every collection decodes with one decoder; an empty `errors` serialises as `{}` |
| B | `PUT /cars/{car}` with `current_km`, then `POST /cars/{car}/fill-ups` — the new fill-up records the corrected odometer |
| C | `POST /cars/{car}/documents` with no file and last month's expiry date → 201; `POST /cars/{car}/fill-ups` with `station_name` and `fuel_type` → 201 |
| D | `POST /auth/forgot-password` for a registered and an unregistered address — **diff the two response bodies, they must be byte-identical**; `DELETE /auth/user` then attempt login |
| E | File a fill-up, read `GET /cars/{car}/costs` — it is already there; `PUT` its amount; edit the fill-up; the override survives |
| F | Record a fault with a photo, resolve it, confirm it leaves the attention list |
| G | `GET /cars/{car}/upcoming-services?include_past=1` returns passed intervals, each with `items[]` |
| H | File a trip with timings and a top speed; correct a parking record's address |
| I | Catalogue reads carry `name_ar`; an entry with no Arabic value falls back to the Latin one |
| J | `GET /cars/{car}/statistics?period=month&compare=previous` answers in one request; `GET /cars/{car}/valuation` returns `basis: "estimate"` |

## After deploying

These are outstanding on production **now**, before this feature adds to them:

```bash
php artisan sync:permissions   # has still never been run on production
php artisan storage:link
php artisan make:filament-user # then assignRole('admin')
```

This feature adds two permission sets (`cost`, `issue`). Without
`sync:permissions` on the server, every new endpoint returns 403 for non-admin
drivers while working perfectly in development — a failure that looks like an
authentication bug and is not one.

A queue worker on the `database` connection, default queue, is also required for
feature 009's CSV import. It is unrelated to this feature but shares the
deployment.
