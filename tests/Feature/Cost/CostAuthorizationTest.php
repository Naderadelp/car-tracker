<?php

namespace Tests\Feature\Cost;

use App\Models\Car;
use App\Models\Cost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FR-017 — a driver sees only costs for their own car.
 */
class CostAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CostPolicy falls back to hasPermissionTo() for non-owners, and spatie
        // throws if the permission row was never created.
        $this->seed(\Database\Seeders\RolePermissionsSeeder::class);
    }

    private function costOn(Car $car): Cost
    {
        return Cost::create([
            'car_id'     => $car->id,
            'user_id'    => $car->user_id,
            'spent_at'   => now()->toDateString(),
            'title'      => 'Annual insurance',
            'amount_egp' => 12500.00,
            'category'   => 'insurance',
        ]);
    }

    public function test_a_driver_sees_only_their_own_costs(): void
    {
        $mine     = Car::factory()->create();
        $theirs   = Car::factory()->create();

        $this->costOn($mine);
        $this->costOn($theirs);

        Sanctum::actingAs(User::find($mine->user_id));

        $data = $this->getJson("/api/cars/{$mine->id}/costs")->assertOk()->json('data');

        $this->assertCount(1, $data);
    }

    public function test_a_driver_cannot_read_another_drivers_ledger(): void
    {
        $theirs = Car::factory()->create();
        $this->costOn($theirs);

        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        Sanctum::actingAs($intruder);

        $this->getJson("/api/cars/{$theirs->id}/costs")->assertForbidden();
    }

    public function test_a_driver_cannot_change_another_drivers_cost(): void
    {
        $theirs = Car::factory()->create();
        $cost   = $this->costOn($theirs);

        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        Sanctum::actingAs($intruder);

        $this->putJson("/api/cars/{$theirs->id}/costs/{$cost->id}", ['amount_egp' => 1])
            ->assertForbidden();

        $this->assertSame('12500.00', $cost->fresh()->amount_egp);
    }

    /**
     * Another driver's cost is refused by CostPolicy before the path is even
     * considered, so this is 403 rather than 404 — consistent with how
     * DocumentController and FillUpController order their checks.
     */
    public function test_another_drivers_cost_is_not_reachable_through_your_own_car(): void
    {
        $mine   = Car::factory()->create();
        $theirs = Car::factory()->create();
        $cost   = $this->costOn($theirs);

        Sanctum::actingAs(User::find($mine->user_id));

        $this->getJson("/api/cars/{$mine->id}/costs/{$cost->id}")->assertForbidden();
    }

    /**
     * The other branch: the driver owns the cost, but reaches for it through a
     * car it does not belong to. The policy passes and the path check catches
     * it, which is a genuine 404.
     */
    public function test_your_own_cost_is_not_reachable_through_the_wrong_car(): void
    {
        $user   = User::factory()->create();
        $first  = Car::factory()->create(['user_id' => $user->id]);
        $second = Car::factory()->create(['user_id' => $user->id]);
        $cost   = $this->costOn($first);

        Sanctum::actingAs($user);

        $this->getJson("/api/cars/{$second->id}/costs/{$cost->id}")->assertNotFound();
    }

    public function test_costs_require_authentication(): void
    {
        $car = Car::factory()->create();

        $this->getJson("/api/cars/{$car->id}/costs")->assertUnauthorized();
    }
}
