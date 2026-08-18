<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Cars\CarResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\RolePermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Tests\TestCase;

/**
 * Covers the guard split between the Filament admin panel and the API.
 *
 * The panel authenticates on the session-based `web` guard, while every role
 * and permission row lives under spatie's `api` guard label. These tests pin
 * that behaviour down rather than assuming it works.
 */
class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionsSeeder::class);
    }

    private function userWithRole(?string $role = null): User
    {
        $user = User::factory()->create();

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }

    public function test_the_suite_runs_against_sqlite_in_memory(): void
    {
        // Guards against a stale bootstrap/cache/config.php pointing the suite
        // at a shared Postgres instance, which RefreshDatabase would wipe.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_the_api_guard_label_resolves_to_a_real_guard(): void
    {
        $this->assertIsArray(config('auth.guards.api'));
        $this->assertInstanceOf(\Illuminate\Contracts\Auth\Guard::class, Auth::guard('api'));
    }

    public function test_permissions_resolve_against_the_api_guard_not_the_session_guard(): void
    {
        $user = $this->userWithRole('admin');

        $this->assertSame('api', $user->guardName());
        $this->assertSame('api', Guard::getDefaultName($user));

        // The session guard is a different thing entirely.
        $this->assertSame('web', config('auth.defaults.guard'));
        $this->assertSame('web', Filament::getPanel('admin')->getAuthGuard());
    }

    public function test_an_admin_passes_permission_checks_inside_the_filament_panel(): void
    {
        $user = $this->userWithRole('admin');

        Filament::setCurrentPanel('admin');
        $this->actingAs($user); // web guard, exactly as the panel logs users in.

        $this->assertTrue($user->hasPermissionTo('index-car'));
        $this->assertTrue($user->hasPermissionTo('destroy-fuel-price'));
        $this->assertTrue($user->can('index-car'));

        // Resource-level authorization runs through the same permission names.
        $this->assertTrue(CarResource::canViewAny());
        $this->assertTrue(CarResource::canCreate());
        $this->assertTrue(UserResource::canViewAny());
    }

    public function test_a_non_admin_fails_resource_permission_checks_inside_the_panel(): void
    {
        // super-user and user are seeded with no permissions at all.
        $user = $this->userWithRole('user');

        Filament::setCurrentPanel('admin');
        $this->actingAs($user);

        $this->assertFalse($user->hasPermissionTo('index-car'));
        $this->assertFalse(CarResource::canViewAny());
    }

    public function test_only_the_admin_role_can_access_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($this->userWithRole('admin')->canAccessPanel($panel));
        $this->assertFalse($this->userWithRole('super-user')->canAccessPanel($panel));
        $this->assertFalse($this->userWithRole('user')->canAccessPanel($panel));
        $this->assertFalse($this->userWithRole()->canAccessPanel($panel));
    }

    public function test_guests_are_redirected_to_the_panel_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_admin_can_load_the_dashboard_and_a_resource_list(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $this->get('/admin')->assertSuccessful();
        $this->get('/admin/cars')->assertSuccessful();
        $this->get('/admin/roles')->assertSuccessful();
    }

    public function test_an_ordinary_user_account_is_rejected_by_the_panel(): void
    {
        $this->actingAs($this->userWithRole('user'));

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/cars')->assertForbidden();
    }

    public function test_a_super_user_account_is_rejected_by_the_panel(): void
    {
        $this->actingAs($this->userWithRole('super-user'));

        $this->get('/admin')->assertForbidden();
    }

    public function test_a_user_with_no_roles_is_rejected_by_the_panel(): void
    {
        $this->actingAs($this->userWithRole());

        $this->get('/admin')->assertForbidden();
    }
}
