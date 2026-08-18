<?php

/**
 * Rebuilds the pre-seed baseline of the `car_tracker` database.
 *
 * WHY THIS EXISTS
 * ---------------
 * On 2026-08-18 the local Postgres database `car_tracker` was emptied by a
 * PHPUnit run. phpunit.xml overrides DB_CONNECTION to sqlite/:memory: through
 * <env> entries, but a config cache (bootstrap/cache/config.php) was present
 * with the pgsql connection baked in. A cached config wins over <env>, so
 * RefreshDatabase ran migrate:fresh against the real Postgres database and
 * dropped every table. The config cache has since been removed.
 *
 * This script restores the rows that existed immediately before that happened,
 * reconstructed from a snapshot taken earlier in the same session.
 *
 * IMPORTANT CAVEATS
 * -----------------
 *  - Password hashes were NOT recoverable. Both restored users get the
 *    password "password". Change them.
 *  - Identifying fields (ids, emails, names, relationships, odometer) are
 *    faithful to the snapshot. Scalar values that were never observed
 *    (item prices, service prices, car_log and fuel_price contents) are
 *    plausible reconstructions, not the originals.
 *  - This is deliberately separate from seed-testdata.php and is NOT recorded
 *    in seed-manifest.json, so seed-cleanup.php will leave it alone. That is
 *    what makes "cleanup returns to baseline" true.
 *
 * Run with:
 *   php artisan tinker --execute="require 'database/restore-baseline.php';"
 */

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarLog;
use App\Models\CarModel;
use App\Models\FuelPrice;
use App\Models\Item;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo 'Restoring baseline into: ' . DB::connection()->getDatabaseName() . PHP_EOL;

activity()->disableLogging();

// ---------------------------------------------------------------------------
// 1. Roles and permissions (guard `api`, via the app's own seeder)
// ---------------------------------------------------------------------------
(new Database\Seeders\RolePermissionsSeeder)->run();
app()[Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

printf(
    "roles/permissions: %d roles, %d permissions\n",
    App\Models\Role::count(),
    App\Models\Permission::count()
);

$restoreNow = Carbon::now();
$restoreHash = Hash::make('password');

// ---------------------------------------------------------------------------
// 2. Users (explicit ids so the original foreign keys line up again)
// ---------------------------------------------------------------------------
DB::transaction(static function () use ($restoreHash, $restoreNow): void {
    $baselineUsers = [
        1 => ['name' => 'Test User', 'email' => 'test@example.com', 'role' => 'user'],
        2 => ['name' => 'Nader',     'email' => 'nader@test.com',   'role' => 'admin'],
    ];

    foreach ($baselineUsers as $id => $row) {
        if (User::whereKey($id)->orWhere('email', $row['email'])->exists()) {
            continue;
        }

        $user = new User;
        $user->forceFill([
            'id'                => $id,
            'name'              => $row['name'],
            'email'             => $row['email'],
            'password'          => $restoreHash,
            'email_verified_at' => $restoreNow->copy()->subMonths(3),
            'created_at'        => $restoreNow->copy()->subMonths(3),
            'updated_at'        => $restoreNow->copy()->subMonths(3),
        ])->save();

        $user->assignRole($row['role']);
    }
});

// ---------------------------------------------------------------------------
// 3. Reference data observed in the snapshot
// ---------------------------------------------------------------------------
DB::transaction(static function () use ($restoreNow): void {
    if (! Brand::whereKey(1)->exists()) {
        (new Brand)->forceFill([
            'id' => 1, 'name' => 'Toyota',
            'created_at' => $restoreNow->copy()->subMonths(3),
            'updated_at' => $restoreNow->copy()->subMonths(3),
        ])->save();
    }

    if (! CarModel::whereKey(1)->exists()) {
        (new CarModel)->forceFill([
            'id' => 1, 'brand_id' => 1, 'name' => 'Corolla', 'model_year' => 2022,
            'created_at' => $restoreNow->copy()->subMonths(3),
            'updated_at' => $restoreNow->copy()->subMonths(3),
        ])->save();
    }

    // Cars: car 1 -> user 1 (no brand, model 1, 0 km); car 2 -> user 2 (brand 1, model 1, 205000 km)
    $baselineCars = [
        1 => ['user_id' => 1, 'brand_id' => null, 'car_model_id' => 1, 'current_km' => 0],
        2 => ['user_id' => 2, 'brand_id' => 1,    'car_model_id' => 1, 'current_km' => 205000],
    ];

    foreach ($baselineCars as $id => $row) {
        if (Car::withTrashed()->whereKey($id)->exists()) {
            continue;
        }

        (new Car)->forceFill($row + [
            'id'                   => $id,
            'tank_size'            => null,
            'has_warranty'         => false,
            'warranty_limit_km'    => null,
            'warranty_expiry_date' => null,
            'created_at'           => $restoreNow->copy()->subMonths(3),
            'updated_at'           => $restoreNow->copy()->subMonths(3),
        ])->save();
    }

    // Items 1-3 (names observed; prices reconstructed)
    $baselineItems = [
        1 => ['name' => 'Engine Oil (5W-30)', 'price' => 950.00],
        2 => ['name' => 'Oil Filter',         'price' => 180.00],
        3 => ['name' => 'Brake Pads',         'price' => 1400.00],
    ];

    foreach ($baselineItems as $id => $row) {
        if (Item::whereKey($id)->orWhere('name', $row['name'])->exists()) {
            continue;
        }

        (new Item)->forceFill($row + [
            'id'         => $id,
            'created_at' => $restoreNow->copy()->subMonths(3),
            'updated_at' => $restoreNow->copy()->subMonths(3),
        ])->save();
    }

    // Services 1-3 (relationships and km observed; prices reconstructed)
    $baselineServices = [
        1 => ['car_model_id' => 1,    'car_id' => 1, 'user_id' => 1, 'km' => 5000,  'price' => 1500.00],
        2 => ['car_model_id' => null, 'car_id' => 1, 'user_id' => 1, 'km' => 5000,  'price' => 1800.00],
        3 => ['car_model_id' => null, 'car_id' => 1, 'user_id' => 1, 'km' => 20000, 'price' => 3200.00],
    ];

    foreach ($baselineServices as $id => $row) {
        if (Service::whereKey($id)->exists()) {
            continue;
        }

        (new Service)->forceFill($row + [
            'id'         => $id,
            'created_at' => $restoreNow->copy()->subMonths(3),
            'updated_at' => $restoreNow->copy()->subMonths(3),
        ])->save();
    }

    // service_centers: 1 row existed; contents were not observed.
    if (App\Models\ServiceCenter::count() === 0) {
        (new App\Models\ServiceCenter)->forceFill([
            'id'         => 1,
            'brand_id'   => 1,
            'name'       => 'Toyota Service Centre - Nasr City',
            'address'    => '12 El Nasr Street, Nasr City, Cairo, Egypt',
            'open_at'    => '09:00:00',
            'close_at'   => '18:00:00',
            'mobile'     => '+201001234567',
            'lat'        => 30.056300,
            'lng'        => 31.330900,
            'created_at' => $restoreNow->copy()->subMonths(3),
            'updated_at' => $restoreNow->copy()->subMonths(3),
        ])->save();
    }

    // service_items: 3 rows existed; exact pairing was not observed.
    if (DB::table('service_items')->count() === 0) {
        DB::table('service_items')->insert([
            ['service_id' => 1, 'item_id' => 1, 'car_id' => 1, 'created_at' => $restoreNow, 'updated_at' => $restoreNow],
            ['service_id' => 1, 'item_id' => 2, 'car_id' => 1, 'created_at' => $restoreNow, 'updated_at' => $restoreNow],
            ['service_id' => 3, 'item_id' => 3, 'car_id' => 1, 'created_at' => $restoreNow, 'updated_at' => $restoreNow],
        ]);
    }

    // car_logs: 3 rows existed; contents were not observed.
    if (CarLog::count() === 0) {
        foreach ([
            ['car_id' => 2, 'service_id' => 1, 'odometer_at_service' => 180000, 'actual_cost' => 1650.00, 'performed_at' => $restoreNow->copy()->subMonths(8)->toDateString()],
            ['car_id' => 2, 'service_id' => 3, 'odometer_at_service' => 195000, 'actual_cost' => 3400.00, 'performed_at' => $restoreNow->copy()->subMonths(4)->toDateString()],
            ['car_id' => 2, 'service_id' => 2, 'odometer_at_service' => 203000, 'actual_cost' => 1900.00, 'performed_at' => $restoreNow->copy()->subMonths(1)->toDateString()],
        ] as $row) {
            CarLog::create($row);
        }
    }

    // fuel_prices: 5 rows existed; contents were not observed.
    if (FuelPrice::count() === 0) {
        foreach ([
            ['type' => '92',       'price_per_unit' => 13.75, 'effective_from' => $restoreNow->copy()->subMonths(12)->toDateString()],
            ['type' => '95',       'price_per_unit' => 15.00, 'effective_from' => $restoreNow->copy()->subMonths(12)->toDateString()],
            ['type' => '92',       'price_per_unit' => 15.25, 'effective_from' => $restoreNow->copy()->subMonths(4)->toDateString()],
            ['type' => '95',       'price_per_unit' => 17.00, 'effective_from' => $restoreNow->copy()->subMonths(4)->toDateString()],
            ['type' => 'electric', 'price_per_unit' => 2.55,  'effective_from' => $restoreNow->copy()->subMonths(4)->toDateString()],
        ] as $row) {
            FuelPrice::create($row);
        }
    }
});

// ---------------------------------------------------------------------------
// 4. Realign Postgres identity sequences after the explicit-id inserts
// ---------------------------------------------------------------------------
foreach (['users', 'brands', 'car_models', 'cars', 'items', 'services', 'service_items', 'car_logs', 'fuel_prices', 'service_centers'] as $restoreTable) {
    DB::statement(
        "SELECT setval(pg_get_serial_sequence(?, 'id'), COALESCE((SELECT MAX(id) FROM {$restoreTable}), 1))",
        [$restoreTable]
    );
}

echo str_repeat('-', 60) . PHP_EOL;
foreach ([
    'User' => User::class, 'Car' => Car::class, 'Brand' => Brand::class,
    'CarModel' => CarModel::class, 'Service' => Service::class, 'Item' => Item::class,
    'CarLog' => CarLog::class, 'FuelPrice' => FuelPrice::class,
] as $restoreLabel => $restoreClass) {
    printf("%-14s %d\n", $restoreLabel, $restoreClass::count());
}
printf("%-14s %d\n", 'service_items', DB::table('service_items')->count());

echo 'Baseline restored. Both users have the password "password" - change them.' . PHP_EOL;

unset($restoreNow, $restoreHash, $restoreTable, $restoreLabel, $restoreClass);
