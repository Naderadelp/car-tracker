<?php

namespace Tests\Feature\Auth;

use App\Models\Car;
use App\Models\Document;
use App\Models\FillUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FR-011, FR-012 — in-app account deletion.
 *
 * Both app stores require this from any app that offers account creation, so
 * its absence blocks store review rather than merely integration.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_driver_can_delete_their_own_account(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('message', 'Account deleted.');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_deletion_removes_the_drivers_car_and_its_records(): void
    {
        $car = Car::factory()->create();
        $user = User::find($car->user_id);
        Sanctum::actingAs($user);

        FillUp::create([
            'car_id'    => $car->id,
            'liters'    => 40,
            'odometer'  => 1000,
            'cost_egp'  => 1200,
            'fill_date' => now()->toDateString(),
        ]);
        Document::factory()->create(['user_id' => $user->id, 'car_id' => $car->id]);

        $this->deleteJson('/api/auth/user')->assertOk();

        $this->assertDatabaseMissing('cars', ['id' => $car->id]);
        $this->assertDatabaseMissing('fill_ups', ['car_id' => $car->id]);
        $this->assertDatabaseMissing('documents', ['user_id' => $user->id]);
    }

    public function test_deletion_ends_every_session_not_just_the_current_one(): void
    {
        $user = User::factory()->create();
        $user->createToken('another_device');
        $user->createToken('a_third_device');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/user')->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * FR-012 scenario 2: the attempt must fail exactly as it would for an
     * address that never existed — no "this account was deleted" hint.
     */
    public function test_a_deleted_account_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'email'             => 'gone@example.com',
            'password'          => 'password123',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/user')->assertOk();

        $deleted = $this->postJson('/api/auth/login', [
            'email'    => 'gone@example.com',
            'password' => 'password123',
        ])->assertStatus(422);

        $neverExisted = $this->postJson('/api/auth/login', [
            'email'    => 'never-existed@example.com',
            'password' => 'password123',
        ])->assertStatus(422);

        $this->assertSame(
            $neverExisted->json('errors'),
            $deleted->json('errors'),
            'A deleted account must be indistinguishable from one that never existed.'
        );
    }

    /**
     * users.email is unique and the row survives a soft delete, so without
     * scrubbing the address the driver could never sign up again with their own
     * email.
     */
    public function test_the_email_address_is_released_for_reuse(): void
    {
        $user = User::factory()->create(['email' => 'driver@example.com']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/user')->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'driver@example.com']);
    }

    public function test_deletion_requires_authentication(): void
    {
        $this->deleteJson('/api/auth/user')->assertUnauthorized();
    }
}
