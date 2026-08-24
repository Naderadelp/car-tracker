# Quickstart: Service & Maintenance Catalog

Quick reference for exercising the new endpoints once the implementation lands. Replace `{TOKEN}` with a Sanctum bearer token, `{CAR_ID}` with a car owned by the authenticated user, and `{BRAND_ID}` with that car's brand.

## 1. Run migrations + sync permissions

```bash
php artisan migrate          # adds service_centers, services, items, service_items
php artisan sync:permissions # provisions service-center / service / item permissions
```

For a clean reset (greenfield-style): `php artisan migrate:fresh --seed`.

## 2. Seed minimal catalogue data (manual or via tinker)

```bash
php artisan tinker --execute="
\$brand = \App\Models\Brand::firstOrCreate(['name' => 'Toyota']);
\$model = \App\Models\CarModel::firstOrCreate(['brand_id' => \$brand->id, 'name' => 'Corolla'], ['model_year' => 2020]);
\App\Models\ServiceCenter::create([
    'brand_id' => \$brand->id,
    'name'     => 'Toyota New Cairo',
    'address'  => 'Ring Road, Fifth Settlement',
    'open_at'  => '09:00:00',
    'close_at' => '21:00:00',
    'mobile'   => '+201112223344',
    'lat'      => 30.06263,
    'lng'      => 31.24967,
]);
\$oil    = \App\Models\Item::firstOrCreate(['name' => 'Engine Oil'],  ['price' => 150]);
\$filter = \App\Models\Item::firstOrCreate(['name' => 'Oil Filter'], ['price' => 75]);
\$svc    = \App\Models\Service::create([
    'car_model_id' => \$model->id,
    'km'           => 40000,
    'price'        => 1250,
]);
\$svc->items()->attach([\$oil->id, \$filter->id]);
echo 'Seeded.' . PHP_EOL;
"
```

## 3. Master parts inventory (CRUD)

```bash
# List
curl 'http://localhost:8000/api/items?filter[name]=oil' \
  -H "Authorization: Bearer {TOKEN}"

# Create
curl -X POST http://localhost:8000/api/items \
  -H "Authorization: Bearer {TOKEN}" -H "Content-Type: application/json" \
  -d '{ "name": "Brake Pads", "price": 450 }'

# Show
curl http://localhost:8000/api/items/1 -H "Authorization: Bearer {TOKEN}"

# Update
curl -X PATCH http://localhost:8000/api/items/1 \
  -H "Authorization: Bearer {TOKEN}" -H "Content-Type: application/json" \
  -d '{ "price": 500 }'

# Delete
curl -X DELETE http://localhost:8000/api/items/1 -H "Authorization: Bearer {TOKEN}"
```

## 4. Nearby service centers for a brand

```bash
# Distance-ordered (required GPS query params)
curl 'http://localhost:8000/api/brands/{BRAND_ID}/service-centers?lat=30.05&lng=31.24' \
  -H "Authorization: Bearer {TOKEN}"

# Missing GPS → 422
curl 'http://localhost:8000/api/brands/{BRAND_ID}/service-centers' \
  -H "Authorization: Bearer {TOKEN}"
```

Expect each entry to carry `is_open` and `distance_km`.

## 5. Upcoming maintenance for a car

```bash
curl http://localhost:8000/api/cars/{CAR_ID}/upcoming-services \
  -H "Authorization: Bearer {TOKEN}"
```

Expect a list filtered to `service.km > car.current_km`, ordered by `km` ascending, with `remaining_km`, `items_count`, and `price` per row.

```bash
# Embed the parent car_model + brand + items in one round trip
curl 'http://localhost:8000/api/cars/{CAR_ID}/upcoming-services?include=carModel.brand,items' \
  -H "Authorization: Bearer {TOKEN}"
```

## 6. Cross-user denial smoke test

Authenticate as User B and target User A's car:

```bash
curl http://localhost:8000/api/cars/{CAR_A_ID}/upcoming-services \
  -H "Authorization: Bearer {TOKEN_B}"
```

Expect `403`.

## 7. Validation smoke tests (expect `422`)

```bash
# Duplicate item name
curl -X POST http://localhost:8000/api/items \
  -H "Authorization: Bearer {TOKEN}" -H "Content-Type: application/json" \
  -d '{ "name": "Engine Oil", "price": 150 }'

# Out-of-range GPS
curl 'http://localhost:8000/api/brands/{BRAND_ID}/service-centers?lat=999&lng=0' \
  -H "Authorization: Bearer {TOKEN}"

# Negative price on item create
curl -X POST http://localhost:8000/api/items \
  -H "Authorization: Bearer {TOKEN}" -H "Content-Type: application/json" \
  -d '{ "name": "Foo", "price": -1 }'
```

## 8. Cascade-delete checks

```bash
php artisan tinker --execute="
\$model = \App\Models\CarModel::first();
\$cnt = \App\Models\Service::where('car_model_id', \$model->id)->count();
echo 'services before model delete: ' . \$cnt . PHP_EOL;
\$model->forceDelete();
\$cnt = \App\Models\Service::where('car_model_id', \$model->id)->count();
echo 'services after  model delete: ' . \$cnt . PHP_EOL;
"
```

Repeat for `Brand → ServiceCenter` and `Service → service_items`.
