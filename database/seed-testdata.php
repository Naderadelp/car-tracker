<?php

/**
 * Test-data seeder for the Filament admin panel.
 *
 * Run with:
 *   php artisan tinker --execute="require 'database/seed-testdata.php';"
 *
 * Every row this script creates is recorded in database/seed-manifest.json so
 * database/seed-cleanup.php can delete exactly what was created and nothing else.
 *
 * Safely re-runnable: if a manifest already exists the cleanup runs first, so a
 * second execution replaces the previous batch instead of duplicating it.
 *
 * NOT wired into DatabaseSeeder on purpose.
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$manifestPath = database_path('seed-manifest.json');

// ---------------------------------------------------------------------------
// Guard: never let this run against a database it was not pointed at knowingly.
// ---------------------------------------------------------------------------
echo 'Connection : ' . config('database.default') . PHP_EOL;
echo 'Host       : ' . config('database.connections.' . config('database.default') . '.host') . PHP_EOL;
echo 'Database   : ' . config('database.connections.' . config('database.default') . '.database') . PHP_EOL;
// A cached config overrides phpunit.xml's <env> sqlite settings, which is how
// this database was previously dropped by a RefreshDatabase test run. Refuse to
// add data while that trap is armed.
if (file_exists(base_path('bootstrap/cache/config.php'))) {
    echo 'ABORT: bootstrap/cache/config.php exists. A cached config beats phpunit.xml, '
         . 'so running the test suite would wipe this database. Run `php artisan config:clear` first.' . PHP_EOL;

    return;
}

echo str_repeat('-', 60) . PHP_EOL;

// ---------------------------------------------------------------------------
// Re-runnable: clear a previous batch first.
// ---------------------------------------------------------------------------
if (file_exists($manifestPath)) {
    echo "Existing manifest found -> running cleanup before re-seeding." . PHP_EOL;
    require __DIR__ . '/seed-cleanup.php';
    echo str_repeat('-', 60) . PHP_EOL;
}

// Activity logging off: it would write thousands of activity_log rows that are
// not part of the requested dataset and would have to be swept up again.
activity()->disableLogging();

$seedStart = microtime(true);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
if (! function_exists('seed_rf')) {
    /** Random float in [$a, $b]. */
    function seed_rf(float $a, float $b, int $decimals = 2): float
    {
        return round($a + (random_int(0, 1_000_000) / 1_000_000) * ($b - $a), $decimals);
    }
}

if (! function_exists('seed_pick')) {
    /** Random element of a list. */
    function seed_pick(array $list)
    {
        return $list[array_rand($list)];
    }
}

if (! function_exists('seed_cairo_lat')) {
    function seed_cairo_lat(): float
    {
        return seed_rf(29.900, 30.150, 6);
    }
}

if (! function_exists('seed_cairo_lng')) {
    function seed_cairo_lng(): float
    {
        return seed_rf(31.150, 31.450, 6);
    }
}

if (! function_exists('seed_make_pdf')) {
    /** Build a small but structurally valid single-page PDF. */
    function seed_make_pdf(array $lines): string
    {
        $content = '';
        $y       = 780;
        $size    = 20;

        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= "BT /F1 {$size} Tf 60 {$y} Td ({$escaped}) Tj ET\n";
            $y    -= 34;
            $size = 12;
        }

        // Pad the stream so the file lands in the low-KB range like a real scan.
        $content .= "% " . str_repeat('padding ', 260) . "\n";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                 . '/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf     = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $size    = count($objects) + 1;

        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF\n";

        return $pdf;
    }
}

/** Everything created, so cleanup can be exact. */
$manifest = [
    'created_at'      => Carbon::now()->toIso8601String(),
    'database'        => config('database.connections.' . config('database.default') . '.database'),
    'brands'          => [],
    'car_models'      => [],
    'items'           => [],
    'service_centers' => [],
    'services'        => [],
    'service_items'   => [],
    'users'           => [],
    'cars'            => [],
    'fill_ups'        => [],
    'trips'           => [],
    'car_logs'        => [],
    'reminders'       => [],
    'parking_records' => [],
    'documents'       => [],
    'media'           => [],
    'fuel_prices'     => [],
];

$now = Carbon::now();

// ===========================================================================
// 1. Reference data: brands + car models via the existing (idempotent) seeder
// ===========================================================================
// BrandSeeder uses firstOrCreate on (brand_id, name), so pre-existing rows are
// reused rather than duplicated. Snapshot ids before/after to learn what is new.
$brandIdsBefore    = Brand::pluck('id')->all();
$carModelIdsBefore = CarModel::pluck('id')->all();

DB::transaction(static function (): void {
    (new Database\Seeders\BrandSeeder)->run();
});

$manifest['brands']     = array_values(array_diff(Brand::pluck('id')->all(), $brandIdsBefore));
$manifest['car_models'] = array_values(array_diff(CarModel::pluck('id')->all(), $carModelIdsBefore));

printf(
    "brands       : +%d (total %d)\ncar_models   : +%d (total %d)\n",
    count($manifest['brands']),
    Brand::count(),
    count($manifest['car_models']),
    CarModel::count()
);

// ===========================================================================
// 2. Items (parts)
// ===========================================================================
$itemCatalogue = [
    ['Air Filter', 320], ['Cabin Filter', 410], ['Fuel Filter', 560],
    ['Spark Plug Set', 1250], ['Timing Belt', 2850], ['Serpentine Belt', 720],
    ['Water Pump', 2400], ['Thermostat', 680], ['Radiator Coolant (4L)', 540],
    ['Brake Discs (Front Pair)', 3200], ['Brake Fluid DOT4', 260],
    ['Rear Brake Pads', 1450], ['Wiper Blades (Pair)', 480],
    ['Battery 70Ah', 4600], ['Alternator Belt', 640], ['Gearbox Oil (ATF)', 1120],
    ['Differential Oil', 890], ['Shock Absorber (Front)', 2750],
    ['Shock Absorber (Rear)', 2380], ['Control Arm Bushing', 640],
    ['Tie Rod End', 830], ['Wheel Bearing', 1580], ['CV Joint Boot', 520],
    ['Clutch Kit', 6800], ['Engine Mount', 1340], ['Oxygen Sensor', 2150],
    ['Ignition Coil', 1780], ['PCV Valve', 390], ['Headlight Bulb (H4)', 240],
    ['AC Compressor Belt', 700],
];

DB::transaction(static function () use ($itemCatalogue, &$manifest, $now): void {
    foreach ($itemCatalogue as $index => [$name, $price]) {
        $item = Item::firstOrCreate(
            ['name' => '[seed] ' . $name],
            [
                'price'      => $price + random_int(-40, 140),
                'created_at' => $now->copy()->subDays(random_int(400, 700)),
                'updated_at' => $now->copy()->subDays(random_int(1, 90)),
            ]
        );

        if ($item->wasRecentlyCreated) {
            $manifest['items'][] = $item->id;
        }
    }
});

printf("items        : +%d (total %d)\n", count($manifest['items']), Item::count());

// ===========================================================================
// 3. Service centers
// ===========================================================================
$districts = [
    'Nasr City', 'Heliopolis', 'Maadi', '6th of October', 'Sheikh Zayed',
    'New Cairo', 'Dokki', 'Mohandessin', 'Shubra', 'Zamalek', 'Obour',
    'Giza', 'Haram', 'Rehab', 'Madinaty', 'Helwan', 'Ain Shams',
    'Faisal', 'Agouza', 'Katameya',
];

$brandsForCentres = Brand::inRandomOrder()->limit(20)->get();

DB::transaction(static function () use ($districts, $brandsForCentres, &$manifest, $now): void {
    foreach ($districts as $index => $district) {
        $brand = $brandsForCentres[$index % max($brandsForCentres->count(), 1)] ?? null;

        $openHour  = random_int(7, 10);
        $closeHour = $openHour + random_int(8, 12); // always after open_at
        $closeHour = min($closeHour, 23);

        $centre = ServiceCenter::create([
            'brand_id'   => $brand?->id,
            'name'       => '[seed] ' . ($brand?->name ?? 'Multi-Brand') . ' Service Centre - ' . $district,
            'address'    => sprintf(
                '%d %s Street, %s, Cairo, Egypt',
                random_int(1, 220),
                seed_pick(['El Nasr', 'El Thawra', 'Makram Ebeid', 'El Hegaz', 'Salah Salem', 'El Orouba']),
                $district
            ),
            'open_at'    => sprintf('%02d:00:00', $openHour),
            'close_at'   => sprintf('%02d:%02d:00', $closeHour, seed_pick([0, 30])),
            'mobile'     => '+2010' . random_int(10000000, 99999999),
            'lat'        => seed_cairo_lat(),
            'lng'        => seed_cairo_lng(),
            'created_at' => $now->copy()->subDays(random_int(300, 700)),
            'updated_at' => $now->copy()->subDays(random_int(1, 120)),
        ]);

        $manifest['service_centers'][] = $centre->id;
    }
});

printf("centres      : +%d (total %d)\n", count($manifest['service_centers']), ServiceCenter::count());

// ===========================================================================
// 4. Catalogue services + service_items pivot
// ===========================================================================
// Catalogue = user_id NULL and car_id NULL (see Service::getIsCatalogueAttribute).
$catalogueModels = CarModel::inRandomOrder()->limit(30)->get();
$itemIds         = Item::whereIn('id', $manifest['items'])->pluck('id')->all();
$intervals       = [10000, 20000, 30000, 40000, 60000, 80000];

DB::transaction(static function () use ($catalogueModels, $intervals, $itemIds, &$manifest, $now): void {
    foreach ($catalogueModels as $model) {
        foreach ($intervals as $km) {
            $service = Service::create([
                'car_model_id' => $model->id,
                'car_id'       => null,
                'user_id'      => null,
                'km'           => $km,
                'price'        => round(900 + ($km / 10000) * seed_rf(380, 760), 2),
                'created_at'   => $now->copy()->subDays(random_int(400, 700)),
                'updated_at'   => $now->copy()->subDays(random_int(1, 120)),
            ]);

            $manifest['services'][] = $service->id;

            // The service_items pivot is not populated anywhere in the app, so
            // fill it directly; without this items_count / ?include=items are empty.
            if ($itemIds !== []) {
                $picked = (array) array_rand(array_flip($itemIds), min(random_int(2, 5), count($itemIds)));

                foreach ($picked as $itemId) {
                    $pivotId = DB::table('service_items')->insertGetId([
                        'service_id' => $service->id,
                        'item_id'    => $itemId,
                        'car_id'     => null,
                        'created_at' => $service->created_at,
                        'updated_at' => $service->updated_at,
                    ]);

                    $manifest['service_items'][] = $pivotId;
                }
            }
        }
    }
});

printf(
    "services     : +%d (total %d)\nservice_items: +%d (total %d)\n",
    count($manifest['services']),
    Service::count(),
    count($manifest['service_items']),
    DB::table('service_items')->count()
);

// ===========================================================================
// 5. Users (+ role) and one car each
// ===========================================================================
$firstNames = [
    'Ahmed', 'Mohamed', 'Mahmoud', 'Youssef', 'Omar', 'Khaled', 'Hassan', 'Tarek',
    'Amr', 'Karim', 'Mostafa', 'Sherif', 'Ibrahim', 'Ali', 'Hesham', 'Nader',
    'Mona', 'Salma', 'Nourhan', 'Yasmin', 'Dina', 'Heba', 'Rania', 'Aya',
    'Mariam', 'Sara', 'Nada', 'Farida', 'Menna', 'Reem',
];
$lastNames = [
    'Hassan', 'Ibrahim', 'Fathy', 'Mansour', 'Abdelrahman', 'Zaki', 'Kamal',
    'Shawky', 'Ramzy', 'Sabry', 'Nabil', 'Gaber', 'Adel', 'Farouk', 'Sultan',
    'ElSayed', 'ElGhazaly', 'Roshdy', 'Selim', 'Anwar',
];

$USER_COUNT = 40;
$RICH_CARS  = 12; // cars that get the deep fill-up / trip history

// One shared bcrypt hash: the `hashed` cast leaves an already-hashed value
// alone, so this avoids 40 separate (slow) bcrypt rounds.
$passwordHash = Hash::make('password');

$carModelPool = CarModel::with('brand')->inRandomOrder()->limit(60)->get();

$seededCars = []; // [carId => ['tank' => float, 'model' => CarModel, 'startKm' => int]]

DB::transaction(static function () use (
    $USER_COUNT, $firstNames, $lastNames, $passwordHash, $carModelPool, &$manifest, &$seededCars, $now
): void {
    for ($i = 1; $i <= $USER_COUNT; $i++) {
        $first = $firstNames[($i * 7) % count($firstNames)];
        $last  = $lastNames[($i * 3) % count($lastNames)];

        $createdAt = $now->copy()->subDays(random_int(220, 730))->subHours(random_int(0, 23));

        $user = User::create([
            'name'              => sprintf('[seed] %s %s', $first, $last),
            'email'             => sprintf('seed%02d@seed.test', $i),
            'password'          => $passwordHash,
            'email_verified_at' => random_int(1, 10) > 1 ? $createdAt->copy()->addHours(random_int(1, 40)) : null,
        ]);

        $user->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $now->copy()->subDays(random_int(0, 120)),
        ])->save();

        // Never grant admin: only nader@test.com may reach the panel.
        $user->assignRole('user');

        $manifest['users'][] = $user->id;

        $model = $carModelPool[($i - 1) % max($carModelPool->count(), 1)];

        // Electric for a small slice of the fleet; the rest are petrol.
        $isElectric = ($i % 13 === 0);
        $tank       = $isElectric
            ? seed_rf(45, 82, 2)   // kWh battery
            : (float) seed_pick([40, 42, 45, 48, 50, 52, 55, 60, 65, 70, 75, 80]);

        $hasWarranty = ($i % 5 !== 0);

        $car = Car::create([
            'user_id'              => $user->id,
            'brand_id'             => $model->brand_id,
            'car_model_id'         => $model->id,
            'current_km'           => 0, // set precisely after fill-ups + trips
            'tank_size'            => $tank,
            'has_warranty'         => $hasWarranty,
            'warranty_limit_km'    => $hasWarranty ? seed_pick([60000, 100000, 120000, 150000, 200000]) : null,
            'warranty_expiry_date' => $hasWarranty
                ? $now->copy()->addDays(random_int(-200, 900))->toDateString()
                : null,
        ]);

        $car->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $now->copy()->subDays(random_int(0, 60)),
        ])->save();

        $manifest['cars'][] = $car->id;

        $seededCars[$car->id] = [
            'tank'      => $tank,
            'electric'  => $isElectric,
            'model_id'  => $model->id,
            'user_id'   => $user->id,
            'start_km'  => random_int(4000, 96000),
            'created_at' => $createdAt,
        ];
    }
});

printf(
    "users        : +%d (total %d)\ncars         : +%d (total %d)\n",
    count($manifest['users']),
    User::count(),
    count($manifest['cars']),
    Car::count()
);

// ===========================================================================
// 6. Fill-ups
// ===========================================================================
// Physical model, so FillUpRepositoryEloquent::statistics() produces sane numbers:
//   tank_percentage = tank level AFTER the fill
//   consumed(i) = liters(i) + (pct(i-1) - pct(i))/100 * tank_size   <- the repo's formula
// Generating forward from a target km/L makes average_consumption land in a
// realistic band instead of 0.00.
$carIds     = array_keys($seededCars);
$richCarIds = array_slice($carIds, 0, $RICH_CARS);

$fuelBasePrice = ['92' => 15.25, '95' => 17.00, 'electric' => 2.55];

foreach (array_chunk($carIds, 8) as $chunk) {
    DB::transaction(static function () use ($chunk, $seededCars, $richCarIds, $fuelBasePrice, &$manifest, $now): void {
        foreach ($chunk as $carId) {
            $meta     = $seededCars[$carId];
            $tank     = $meta['tank'];
            $electric = $meta['electric'];
            $isRich   = in_array($carId, $richCarIds, true);
            $count    = $isRich ? random_int(24, 26) : random_int(4, 6);

            $baseType = $electric ? 'electric' : seed_pick(['92', '95']);

            // Efficiency band: km per litre (petrol) or km per kWh (electric).
            $effLow  = $electric ? 4.6 : 8.8;
            $effHigh = $electric ? 7.2 : 14.2;

            $odometer = $meta['start_km'];
            $level    = seed_rf(86, 100, 2);      // % after the first fill
            $fuel     = $level / 100 * $tank;     // litres / kWh in the tank

            // Spread the history over the past ~2 years, ending recently.
            $daysSpan = $isRich ? random_int(560, 700) : random_int(160, 420);
            $date     = $now->copy()->subDays($daysSpan);
            $dayStep  = (int) max(4, floor($daysSpan / max($count, 1)));

            for ($i = 0; $i < $count; $i++) {
                if ($i === 0) {
                    $liters = round($level / 100 * $tank * seed_rf(0.75, 1.0, 4), 2);
                } else {
                    $eff  = seed_rf($effLow, $effHigh, 2);
                    $dist = random_int(300, 700);

                    // Cannot burn more than is in the tank (keep a ~5% reserve).
                    $maxDist = (int) floor(max($fuel - 0.05 * $tank, 0.5) * $eff);
                    $dist    = max(150, min($dist, $maxDist));

                    $consumed = $dist / $eff;
                    $fuel     = max($fuel - $consumed, 0.02 * $tank);

                    // Mostly brim it; occasionally a partial fill.
                    $level  = random_int(1, 5) === 1 ? seed_rf(62, 82, 2) : seed_rf(86, 100, 2);
                    $target = $level / 100 * $tank;

                    if ($target <= $fuel) {           // partial fill below current level
                        $level  = seed_rf(88, 100, 2);
                        $target = $level / 100 * $tank;
                    }

                    $liters    = round($target - $fuel, 2);
                    $fuel      = $target;
                    $odometer += $dist;
                }

                $type  = $electric ? 'electric' : (random_int(1, 6) === 1 ? seed_pick(['92', '95']) : $baseType);
                $price = $fuelBasePrice[$type] * seed_rf(0.88, 1.12, 4);

                $fillDate = $date->copy()->addDays(random_int(0, max($dayStep - 1, 0)));
                if ($fillDate->greaterThan($now)) {
                    $fillDate = $now->copy()->subDays(random_int(0, 5));
                }

                $fill = FillUp::create([
                    'car_id'          => $carId,
                    'liters'          => max($liters, 1.0),
                    'tank_percentage' => $level,
                    'odometer'        => $odometer,
                    'cost_egp'        => round(max($liters, 1.0) * $price, 2),
                    'fill_date'       => $fillDate->toDateString(),
                    'fuel_type'       => $type,
                    'station_lat'     => seed_cairo_lat(),
                    'station_lng'     => seed_cairo_lng(),
                ]);

                $fill->forceFill([
                    'created_at' => $fillDate->copy()->addHours(random_int(6, 21)),
                    'updated_at' => $fillDate->copy()->addHours(random_int(6, 21)),
                ])->save();

                $manifest['fill_ups'][] = $fill->id;

                $date->addDays($dayStep);
            }
        }
    });
}

// $seededCars was captured by value above, so read the real per-car maximum
// odometer back out of the database rather than trusting the closure's copy.
foreach (FillUp::whereIn('car_id', $carIds)->selectRaw('car_id, max(odometer) as m')->groupBy('car_id')->get() as $row) {
    $seededCars[$row->car_id]['last_fill_km'] = (int) $row->m;
}

printf("fill_ups     : +%d (total %d)\n", count($manifest['fill_ups']), FillUp::count());

// ===========================================================================
// 7. Trips  (TripObserver accounted for explicitly)
// ===========================================================================
// App\Observers\TripObserver does `$car->current_km += round($trip->total_distance_km)`
// on every Trip creation and fires OdometerAdvanced, whose two ShouldQueue
// listeners would be pushed onto the `jobs` table (QUEUE_CONNECTION=database).
// Trips are therefore created with model events suppressed and the identical
// odometer arithmetic is applied by hand below, so current_km ends up exactly
// where the observer would have put it - without queue or notification fallout.
foreach (array_chunk($carIds, 8) as $chunk) {
    DB::transaction(static function () use ($chunk, $richCarIds, &$manifest, $now): void {
        foreach ($chunk as $carId) {
            $count = in_array($carId, $richCarIds, true) ? random_int(9, 11) : random_int(2, 3);
            $sum   = 0;

            for ($i = 0; $i < $count; $i++) {
                $distance = seed_rf(4.5, 82.0, 2);
                $tripDate = $now->copy()->subDays(random_int(1, 180))->subHours(random_int(0, 23));

                $trip = Trip::withoutEvents(static fn () => Trip::create([
                    'car_id'            => $carId,
                    'start_lat'         => seed_cairo_lat(),
                    'start_lng'         => seed_cairo_lng(),
                    'end_lat'           => seed_cairo_lat(),
                    'end_lng'           => seed_cairo_lng(),
                    'total_distance_km' => $distance,
                ]));

                Trip::withoutEvents(static fn () => $trip->forceFill([
                    'created_at' => $tripDate,
                    'updated_at' => $tripDate,
                ])->save());

                $manifest['trips'][] = $trip->id;

                // Exactly what TripObserver would have added.
                $sum += (int) round($distance);
            }

            unset($sum);
        }
    });
}

// Sum every trip's rounded distance per car - the exact total TripObserver
// would have accumulated onto current_km had its events been allowed to fire.
$tripDistanceByCar = [];
foreach (Trip::whereIn('car_id', $carIds)->get(['car_id', 'total_distance_km']) as $trip) {
    $tripDistanceByCar[$trip->car_id] = ($tripDistanceByCar[$trip->car_id] ?? 0) + (int) round($trip->total_distance_km);
}

printf("trips        : +%d (total %d)\n", count($manifest['trips']), Trip::count());

// ---------------------------------------------------------------------------
// 7b. Settle each car's odometer: last fill-up reading + every trip's distance.
// ---------------------------------------------------------------------------
DB::transaction(static function () use ($carIds, $seededCars, $tripDistanceByCar): void {
    foreach ($carIds as $carId) {
        $base = $seededCars[$carId]['last_fill_km'] ?? $seededCars[$carId]['start_km'];
        $km   = $base + random_int(20, 400) + ($tripDistanceByCar[$carId] ?? 0);

        Car::whereKey($carId)->update(['current_km' => $km]);
    }
});

$finalKm = Car::whereIn('id', $carIds)->pluck('current_km', 'id')->all();

$badOdometer = FillUp::whereIn('car_id', $carIds)
    ->join('cars', 'cars.id', '=', 'fill_ups.car_id')
    ->whereColumn('fill_ups.odometer', '>', 'cars.current_km')
    ->count();

printf("odometer     : settled for %d cars (fill-ups above current_km: %d)\n", count($finalKm), $badOdometer);

// ===========================================================================
// 8. Car logs (completed maintenance)
// ===========================================================================
$catalogueByModel = Service::whereIn('id', $manifest['services'])
    ->get(['id', 'car_model_id', 'km', 'price'])
    ->groupBy('car_model_id');

foreach (array_chunk($carIds, 10) as $chunk) {
    DB::transaction(static function () use ($chunk, $seededCars, $finalKm, $catalogueByModel, &$manifest, $now): void {
        foreach ($chunk as $carId) {
            $modelId  = $seededCars[$carId]['model_id'];
            $current  = $finalKm[$carId] ?? 50000;
            $services = $catalogueByModel[$modelId] ?? collect();

            $count = random_int(3, 5);

            for ($i = 0; $i < $count; $i++) {
                $service  = $services->isNotEmpty() ? $services->random() : null;
                $odometer = max(500, (int) round($current * seed_rf(0.25, 0.96, 4)));

                $log = CarLog::create([
                    'car_id'              => $carId,
                    'service_id'          => $service?->id,
                    'odometer_at_service' => $odometer,
                    'actual_cost'         => round(((float) ($service->price ?? 1800)) * seed_rf(0.82, 1.35, 4), 2),
                    'performed_at'        => $now->copy()->subDays(random_int(10, 690))->toDateString(),
                ]);

                $log->forceFill([
                    'created_at' => $now->copy()->subDays(random_int(10, 690)),
                    'updated_at' => $now->copy()->subDays(random_int(1, 100)),
                ])->save();

                $manifest['car_logs'][] = $log->id;
            }
        }
    });
}

printf("car_logs     : +%d (total %d)\n", count($manifest['car_logs']), CarLog::count());

// ===========================================================================
// 9. Reminders (mix of date-based and km-based, some already notified)
// ===========================================================================
$reminderTitles = [
    'Oil change due', 'Annual licence renewal', 'Tyre rotation',
    'Insurance renewal', 'Air conditioning service', 'Brake inspection',
    'Battery health check', 'Wheel alignment', 'Coolant flush',
    'Timing belt inspection',
];

foreach (array_chunk($carIds, 10) as $chunk) {
    DB::transaction(static function () use ($chunk, $finalKm, $reminderTitles, &$manifest, $now): void {
        foreach ($chunk as $carId) {
            $current = $finalKm[$carId] ?? 50000;

            for ($i = 0; $i < 3; $i++) {
                // Always satisfy "at least one of remind_on / remind_at_km".
                $mode = seed_pick(['date', 'km', 'both']);

                $overdue = random_int(1, 3) === 1;

                $remindOn = $mode === 'km'
                    ? null
                    : $now->copy()->addDays($overdue ? -random_int(5, 150) : random_int(5, 260))->toDateString();

                $remindAtKm = $mode === 'date'
                    ? null
                    : ($overdue ? max(100, $current - random_int(500, 8000)) : $current + random_int(500, 12000));

                $reminder = Reminder::create([
                    'car_id'       => $carId,
                    'remind_on'    => $remindOn,
                    'remind_at_km' => $remindAtKm,
                    'title'        => '[seed] ' . seed_pick($reminderTitles),
                    'description'  => '[seed] Scheduled maintenance reminder generated for admin panel test data.',
                    // Only overdue reminders can plausibly have been notified.
                    'notified_at'  => ($overdue && random_int(1, 2) === 1)
                        ? $now->copy()->subDays(random_int(1, 40))
                        : null,
                ]);

                $reminder->forceFill([
                    'created_at' => $now->copy()->subDays(random_int(20, 500)),
                    'updated_at' => $now->copy()->subDays(random_int(0, 60)),
                ])->save();

                $manifest['reminders'][] = $reminder->id;
            }
        }
    });
}

printf("reminders    : +%d (total %d)\n", count($manifest['reminders']), Reminder::count());

// ===========================================================================
// 10. Parking records (mix of named and coordinate-only)
// ===========================================================================
$parkingNames = [
    'Cairo Festival City P3', 'Mall of Egypt Level B1', 'Office Garage',
    'Home Street Parking', 'City Stars P2', 'Almaza Bay Lot',
    'Nile Corniche Kerbside', 'Downtown Tahrir Garage', 'Airport T3 Long Stay',
    'Club Car Park',
];

foreach (array_chunk($carIds, 10) as $chunk) {
    DB::transaction(static function () use ($chunk, $parkingNames, &$manifest, $now): void {
        foreach ($chunk as $carId) {
            for ($i = 0; $i < 4; $i++) {
                $named = random_int(1, 10) > 4;

                $record = ParkingRecord::create([
                    'car_id'      => $carId,
                    'name'        => $named ? '[seed] ' . seed_pick($parkingNames) : null,
                    'description' => $named
                        ? '[seed] Parked on level ' . random_int(1, 5) . ', bay ' . random_int(1, 220) . '.'
                        : null,
                    'latitude'    => seed_cairo_lat(),
                    'longitude'   => seed_cairo_lng(),
                    'parked_at'   => $now->copy()->subDays(random_int(1, 400))->subHours(random_int(0, 23)),
                ]);

                $record->forceFill([
                    'created_at' => $record->parked_at,
                    'updated_at' => $record->parked_at,
                ])->save();

                $manifest['parking_records'][] = $record->id;
            }
        }
    });
}

printf("parking      : +%d (total %d)\n", count($manifest['parking_records']), ParkingRecord::count());

// ===========================================================================
// 11. Documents (+ real media files via spatie/laravel-medialibrary)
// ===========================================================================
$tmpDir = sys_get_temp_dir() . '/car-tracker-seed-' . getmypid();
if (! is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$mediaOk     = 0;
$mediaFailed = 0;
$mediaError  = null;

foreach (array_chunk($carIds, 10) as $chunk) {
    foreach ($chunk as $carId) {
        $userId = $seededCars[$carId]['user_id'];
        $types  = (array) array_rand(array_flip(Document::TYPES), 2);

        foreach ($types as $type) {
            $document = Document::create([
                'user_id'     => $userId,
                'car_id'      => $carId,
                'type'        => $type,
                'expiry_date' => $now->copy()->addDays(random_int(-120, 720))->toDateString(),
            ]);

            $document->forceFill([
                'created_at' => $now->copy()->subDays(random_int(20, 640)),
                'updated_at' => $now->copy()->subDays(random_int(0, 60)),
            ])->save();

            $manifest['documents'][] = $document->id;

            try {
                $fileName = sprintf('seed-%s-car%d.pdf', $type, $carId);
                $path     = $tmpDir . '/' . $fileName;

                file_put_contents($path, seed_make_pdf([
                    '[seed] ' . strtoupper(str_replace('_', ' ', $type)),
                    'Car ID: ' . $carId . '   User ID: ' . $userId,
                    'Expires: ' . $document->expiry_date->toDateString(),
                    'Generated test document - safe to delete.',
                ]));

                $media = $document
                    ->addMedia($path)
                    ->preservingOriginal()
                    ->usingFileName($fileName)
                    ->usingName('[seed] ' . str_replace('_', ' ', $type))
                    ->toMediaCollection('vehicle_documents');

                $manifest['media'][] = $media->id;
                $mediaOk++;

                @unlink($path);
            } catch (\Throwable $e) {
                $mediaFailed++;
                $mediaError ??= $e->getMessage();
            }
        }
    }
}

@rmdir($tmpDir);

printf(
    "documents    : +%d (total %d) | media attached: %d, failed: %d%s\n",
    count($manifest['documents']),
    Document::count(),
    $mediaOk,
    $mediaFailed,
    $mediaError ? ' | first error: ' . $mediaError : ''
);

// ===========================================================================
// 12. Fuel prices
// ===========================================================================
DB::transaction(static function () use (&$manifest, $now): void {
    $series = [
        '92'       => [12.50, 13.75, 14.35, 15.25, 16.10],
        '95'       => [13.75, 15.00, 15.75, 17.00, 18.20],
        'electric' => [1.95, 2.15, 2.35, 2.55, 2.80],
    ];

    $monthsBack = [22, 17, 12, 6, 1];

    foreach ($series as $type => $prices) {
        foreach ($prices as $index => $price) {
            $effective = $now->copy()->subMonths($monthsBack[$index])->startOfMonth();

            $row = FuelPrice::create([
                'type'           => $type,
                'price_per_unit' => $price,
                'effective_from' => $effective->toDateString(),
            ]);

            $row->forceFill([
                'created_at' => $effective,
                'updated_at' => $effective,
            ])->save();

            $manifest['fuel_prices'][] = $row->id;
        }
    }
});

printf("fuel_prices  : +%d (total %d)\n", count($manifest['fuel_prices']), FuelPrice::count());

// ===========================================================================
// Persist the manifest
// ===========================================================================
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo str_repeat('-', 60) . PHP_EOL;
printf("Manifest written to %s\n", $manifestPath);
printf("Done in %.1fs\n", microtime(true) - $seedStart);

// Sanity read-out: consumption statistics for the deep-history cars.
$repo = app(App\Repositories\Contracts\FillUpRepository::class);
echo str_repeat('-', 60) . PHP_EOL;
echo "Fill-up statistics sample (deep-history cars):" . PHP_EOL;
foreach (array_slice($richCarIds, 0, 5) as $carId) {
    $stats = $repo->statistics($carId);
    printf(
        "  car %-4d fills=%-3d distance=%-7d avg=%-6s cost=%s\n",
        $carId,
        $stats['total_fill_ups'],
        $stats['total_distance_km'],
        $stats['average_consumption'],
        $stats['total_cost_egp']
    );
}

unset($manifest, $seededCars, $catalogueByModel);
