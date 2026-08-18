<?php

namespace Tests\Feature\Filament;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarLog;
use App\Models\CarModel;
use App\Models\Document;
use App\Models\FillUp;
use App\Models\FuelPrice;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCenter;
use App\Models\User;
use Database\Seeders\RolePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every resource's edit page against a real record, which exercises
 * form hydration and the relationship/option closures the create pages only
 * partially cover.
 */
class AdminEditPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin->fresh());
    }

    public function test_every_edit_page_renders(): void
    {
        $owner = User::factory()->create();
        $brand = Brand::factory()->create(['name' => 'Toyota']);
        $carModel = CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2021]);

        $car = Car::create([
            'user_id' => $owner->id,
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'current_km' => 12_000,
            'tank_size' => 50,
            'has_warranty' => true,
            'warranty_limit_km' => 100_000,
            'warranty_expiry_date' => now()->addYear()->toDateString(),
        ]);

        $service = Service::create([
            'car_model_id' => $carModel->id,
            'km' => 10_000,
            'price' => 1_500,
        ]);

        $item = Item::create(['name' => 'Oil filter', 'price' => 250]);
        $service->items()->attach($item->id);

        $records = [
            'users' => $owner,
            'cars' => $car,
            'brands' => $brand,
            'car-models' => $carModel,
            'services' => $service,
            'items' => $item,
            'service-centers' => ServiceCenter::create([
                'brand_id' => $brand->id,
                'name' => 'Downtown Service',
                'address' => '1 Main St',
                'open_at' => '08:00:00',
                'close_at' => '18:00:00',
                'mobile' => '+201000000000',
                'lat' => 30.04442100,
                'lng' => 31.23571200,
            ]),
            'car-logs' => CarLog::create([
                'car_id' => $car->id,
                'service_id' => $service->id,
                'odometer_at_service' => 11_000,
                'actual_cost' => 1_600,
                'performed_at' => now()->subMonth()->toDateString(),
            ]),
            'fill-ups' => FillUp::create([
                'car_id' => $car->id,
                'liters' => 40,
                'tank_percentage' => 80,
                'odometer' => 11_500,
                'cost_egp' => 600,
                'fill_date' => now()->subWeek()->toDateString(),
                'fuel_type' => '95',
            ]),
            'documents' => Document::create([
                'user_id' => $owner->id,
                'car_id' => $car->id,
                'type' => 'vehicle_license',
                'expiry_date' => now()->addMonths(6)->toDateString(),
            ]),
            'reminders' => Reminder::create([
                'car_id' => $car->id,
                'remind_at_km' => 15_000,
                'title' => 'Oil change',
            ]),
            'fuel-prices' => FuelPrice::create([
                'type' => '95',
                'price_per_unit' => 12.50,
                'effective_from' => now()->toDateString(),
            ]),
            'roles' => Role::where('name', 'admin')->firstOrFail(),
            'permissions' => Permission::where('name', 'index-car')->firstOrFail(),
        ];

        foreach ($records as $slug => $record) {
            $this->get("/admin/{$slug}/{$record->getKey()}/edit")
                ->assertSuccessful();
        }
    }
}
