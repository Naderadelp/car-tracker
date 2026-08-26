<?php

namespace Tests\Feature\Car;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FR-004. CarPolicy did not exist before this feature, so these are the first
 * ownership checks a car has ever had.
 */
class CarAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CarPolicy falls back to hasPermissionTo() when the driver is not the
        // owner, and spatie throws PermissionDoesNotExist if the permission was
        // never created. Seeding the roles and permissions exercises the real
        // path rather than an accident.
        $this->seed(\Database\Seeders\RolePermissionsSeeder::class);
    }

    public function test_a_driver_cannot_read_another_drivers_car(): void
    {
        $car = Car::factory()->create();

        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        Sanctum::actingAs($intruder);

        $this->getJson("/api/cars/{$car->id}")->assertForbidden();
    }

    public function test_a_driver_cannot_change_another_drivers_car(): void
    {
        $car = Car::factory()->create(['current_km' => 45_000]);

        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        Sanctum::actingAs($intruder);

        $this->putJson("/api/cars/{$car->id}", ['current_km' => 1])
            ->assertForbidden();

        $this->assertSame(45_000, $car->fresh()->current_km);
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $car = Car::factory()->create();

        $this->putJson("/api/cars/{$car->id}", ['current_km' => 1])
            ->assertUnauthorized();
    }

    public function test_an_admin_may_change_any_car(): void
    {
        $car = Car::factory()->create(['current_km' => 45_000]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        // Gate::before() in AppServiceProvider bypasses every check for admins.
        $this->putJson("/api/cars/{$car->id}", ['current_km' => 60_000])->assertOk();

        $this->assertSame(60_000, $car->fresh()->current_km);
    }
}
