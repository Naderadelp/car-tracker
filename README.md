# Car Tracker

A Laravel API for keeping track of a car: services, fuel, trips, parking, documents and the
reminders that come out of all of it.

I started it to have somewhere I could build a whole backend properly rather than in the gaps
around a feature ticket. It's the project where I worked out how I actually like to structure
things, and most of that is written down in
[`architecture_patterns.md`](architecture_patterns.md).

## What it does

- **Cars** organised by brand and model, with a catalog that drives registration
- **Service history** against service centers, services and a parts inventory
- **Fuel** fill-ups, tank size, and fuel usage worked out from them
- **Trips** and **parking records**
- **Documents** with expiry tracking, licence and insurance
- **Reminders** that fire on a date *or* on a kilometer reading
- **Push notifications** through Firebase
- **Roles and permissions** with per-model policies

## The part I'd want to talk about

A reminder can be due two different ways. Either a date has passed, or the car has driven past a
target odometer reading. The second one has no event to hang off, because nothing happens at the
moment the car crosses 60,000km. It's only true the next time someone logs a reading.

So the check is one query that handles both:

```php
$q->where(function ($dateQuery) {
    $dateQuery->whereNotNull('remind_on')
        ->whereDate('remind_on', '<=', today());
})->orWhere(function ($kmQuery) {
    $kmQuery->whereNotNull('remind_at_km')
        ->whereHas('car', function ($carQuery) {
            $carQuery->whereColumn('cars.current_km', '>=', 'reminders.remind_at_km');
        });
});
```

The `whereColumn` compares the car's current reading against the reminder's target inside the
existence check, so the database decides what's due rather than me pulling every reminder into
PHP and looping. `notified_at` is what stops it sending twice.

That runs on a schedule, and separately an `OdometerAdvanced` event fires when a log pushes the
reading up, so a reminder crossed mid-day doesn't wait for the next scheduled run.

## How it's built

**Repositories** behind contracts in `app/Repositories/Contracts`, with Spatie QueryBuilder
wired into the Eloquent implementations, so filtering and sorting are declared per resource
instead of being rebuilt in every controller.

**Events and listeners** for anything that fans out. `CarLogCreated`, `OdometerAdvanced` and
`GasStationCheckIn` each have listeners that decide whether a notification is warranted. Keeps
the notification logic out of the controllers.

**Scheduled commands** for the things that are time-based rather than action-based:
`app:check-reminders`, `app:check-document-expiry`, `app:check-warranty-expiry`.

**Auth** is Sanctum, but I replaced Laravel's password reset with an email OTP flow, for
registration and for password recovery, because the app is mobile-first and a reset link in an
email is awkward on a phone. The mail sends through the queue.

**Permissions** are Spatie, with an `app:sync-permissions-and-roles` command so adding a model
doesn't mean hand-writing its CRUD permissions into a seeder.

Laravel 13, PHP 8.3. There's a Postman collection in the repo covering the endpoints.

## Running it

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan app:sync-permissions-and-roles
php artisan queue:work
php artisan serve
```

Scheduled jobs need `php artisan schedule:work` running, and push notifications need Firebase
credentials in `.env`.

## What I'd do next

- **Tests.** There are three real ones, covering the car model catalog endpoints, and that isn't
  enough for a codebase this size. The reminder due-check is the first thing I'd cover, since
  it's the piece with actual logic in it.
- Docker Compose, and CI that runs the suite on push.
- The home dashboard endpoint recalculates everything per request and should be cached.
- Rate limiting on the OTP endpoints. Right now nothing stops someone requesting codes in a loop.
