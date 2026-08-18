<?php

/**
 * Removes everything database/seed-testdata.php created - and nothing else.
 *
 * Run with:
 *   php artisan tinker --execute="require 'database/seed-cleanup.php';"
 *
 * Works from database/seed-manifest.json, which records the primary key of
 * every row the seeder inserted. Rows that already existed before seeding were
 * never added to the manifest, so they are never touched.
 *
 * Before deleting anything it re-verifies that every user id in the manifest
 * really is an @seed.test account and aborts if that is not true.
 */

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarLog;
use App\Models\CarModel;
use App\Models\Document;
use App\Models\FillUp;
use App\Models\FuelPrice;
use App\Models\Item;
use App\Models\ParkingRecord;
use App\Models\Reminder;
use App\Models\Service;
use App\Models\ServiceCenter;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$cleanupManifestPath = database_path('seed-manifest.json');

if (! file_exists($cleanupManifestPath)) {
    echo "No seed manifest at {$cleanupManifestPath} - nothing to clean up." . PHP_EOL;

    return;
}

$cleanupManifest = json_decode(file_get_contents($cleanupManifestPath), true);

if (! is_array($cleanupManifest)) {
    echo "Seed manifest is not readable JSON - refusing to guess. Aborting." . PHP_EOL;

    return;
}

$cleanupIds = static fn (string $key): array => array_values(array_filter(
    array_map('intval', $cleanupManifest[$key] ?? [])
));

// -------------------------------------------------------------------------
// Safety checks before a single row is deleted.
// -------------------------------------------------------------------------
$cleanupDatabase = config('database.connections.' . config('database.default') . '.database');

if (($cleanupManifest['database'] ?? $cleanupDatabase) !== $cleanupDatabase) {
    printf(
        "Manifest was written against database '%s' but the current connection is '%s'. Aborting.\n",
        $cleanupManifest['database'],
        $cleanupDatabase
    );

    return;
}

$cleanupUserIds = $cleanupIds('users');

if ($cleanupUserIds !== []) {
    $nonSeedEmails = User::whereIn('id', $cleanupUserIds)
        ->where('email', 'not like', '%@seed.test')
        ->pluck('email')
        ->all();

    if ($nonSeedEmails !== []) {
        echo 'ABORT: manifest lists user ids whose email is not @seed.test: '
             . implode(', ', $nonSeedEmails) . PHP_EOL;

        return;
    }
}

// Deleting logged models would otherwise write a fresh pile of activity_log rows.
activity()->disableLogging();

$cleanupDeleted = [];
$cleanupStart   = microtime(true);

// -------------------------------------------------------------------------
// Delete in foreign-key-safe order.
// -------------------------------------------------------------------------

// Documents first, through Eloquent, so medialibrary removes the media rows
// and the files on disk along with them.
$cleanupDeleted['documents'] = 0;
foreach (Document::whereIn('id', $cleanupIds('documents'))->get() as $cleanupDocument) {
    $cleanupDocument->delete();
    $cleanupDeleted['documents']++;
}

// Sweep any media rows the cascade missed (e.g. an orphaned attachment).
$cleanupMediaIds = $cleanupIds('media');
$cleanupDeleted['media'] = 0;
if ($cleanupMediaIds !== []) {
    foreach (Spatie\MediaLibrary\MediaCollections\Models\Media::whereIn('id', $cleanupMediaIds)->get() as $cleanupMedia) {
        $cleanupMedia->delete();
        $cleanupDeleted['media']++;
    }
}

$cleanupDeleted['service_items']   = DB::table('service_items')->whereIn('id', $cleanupIds('service_items'))->delete();
$cleanupDeleted['car_logs']        = CarLog::whereIn('id', $cleanupIds('car_logs'))->delete();
$cleanupDeleted['fill_ups']        = FillUp::whereIn('id', $cleanupIds('fill_ups'))->delete();
$cleanupDeleted['trips']           = Trip::withoutEvents(
    static fn () => Trip::whereIn('id', $cleanupIds('trips'))->delete()
);
$cleanupDeleted['parking_records'] = ParkingRecord::whereIn('id', $cleanupIds('parking_records'))->delete();
$cleanupDeleted['reminders']       = Reminder::whereIn('id', $cleanupIds('reminders'))->delete();
$cleanupDeleted['services']        = Service::whereIn('id', $cleanupIds('services'))->delete();

// Cars use SoftDeletes - force it, otherwise the rows survive as tombstones.
$cleanupDeleted['cars'] = Car::withTrashed()->whereIn('id', $cleanupIds('cars'))->forceDelete();

// spatie/laravel-permission's model_has_roles is a morph table with no FK to
// users, so the role assignments have to be removed explicitly.
$cleanupDeleted['model_has_roles'] = 0;
if ($cleanupUserIds !== []) {
    $cleanupDeleted['model_has_roles'] = DB::table(config('permission.table_names.model_has_roles'))
        ->where('model_type', User::class)
        ->whereIn('model_id', $cleanupUserIds)
        ->delete();

    $cleanupDeleted['model_has_permissions'] = DB::table(config('permission.table_names.model_has_permissions'))
        ->where('model_type', User::class)
        ->whereIn('model_id', $cleanupUserIds)
        ->delete();

    $cleanupDeleted['personal_access_tokens'] = DB::table('personal_access_tokens')
        ->where('tokenable_type', User::class)
        ->whereIn('tokenable_id', $cleanupUserIds)
        ->delete();

    $cleanupDeleted['device_tokens'] = DB::table('device_tokens')
        ->whereIn('user_id', $cleanupUserIds)
        ->delete();
}

$cleanupDeleted['users']           = User::whereIn('id', $cleanupUserIds)->delete();
$cleanupDeleted['service_centers'] = ServiceCenter::whereIn('id', $cleanupIds('service_centers'))->delete();
$cleanupDeleted['car_models']      = CarModel::whereIn('id', $cleanupIds('car_models'))->delete();
$cleanupDeleted['brands']          = Brand::whereIn('id', $cleanupIds('brands'))->delete();
$cleanupDeleted['items']           = Item::whereIn('id', $cleanupIds('items'))->delete();
$cleanupDeleted['fuel_prices']     = FuelPrice::whereIn('id', $cleanupIds('fuel_prices'))->delete();

foreach ($cleanupDeleted as $cleanupTable => $cleanupCount) {
    printf("deleted %-24s %d\n", $cleanupTable, $cleanupCount);
}

// -------------------------------------------------------------------------
// Leftover check: anything still carrying the test-data markers.
// -------------------------------------------------------------------------
$cleanupLeftovers = [
    'users @seed.test'       => User::where('email', 'like', '%@seed.test')->count(),
    'items [seed]'           => Item::where('name', 'like', '[seed]%')->count(),
    'centres [seed]'         => ServiceCenter::where('name', 'like', '[seed]%')->count(),
    'reminders [seed]'       => Reminder::where('title', 'like', '[seed]%')->count(),
    'parking [seed]'         => ParkingRecord::where('name', 'like', '[seed]%')->count(),
];

foreach ($cleanupLeftovers as $cleanupLabel => $cleanupCount) {
    if ($cleanupCount > 0) {
        printf("WARNING leftover %-22s %d\n", $cleanupLabel, $cleanupCount);
    }
}

@unlink($cleanupManifestPath);

printf("Cleanup complete in %.1fs; manifest removed.\n", microtime(true) - $cleanupStart);

unset(
    $cleanupManifest, $cleanupManifestPath, $cleanupIds, $cleanupUserIds, $cleanupDeleted,
    $cleanupLeftovers, $cleanupMediaIds, $cleanupStart, $cleanupDatabase
);
