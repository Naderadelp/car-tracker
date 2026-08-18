<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Support\PermissionGroups;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Role resource paints one CheckboxList per permission subject, which means
 * the state is a nested array rather than something Filament can bind straight
 * to the relationship. These tests cover the hydrate/sync bridge.
 */
class RolePermissionEditorTest extends TestCase
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

    public function test_permission_names_are_grouped_by_their_longest_matching_subject(): void
    {
        $this->assertSame('car', PermissionGroups::subjectFor('index-car'));
        $this->assertSame('car-log', PermissionGroups::subjectFor('index-car-log'));
        $this->assertSame('car-model', PermissionGroups::subjectFor('force-delete-car-model'));
        $this->assertSame('service-center', PermissionGroups::subjectFor('destroy-service-center'));
        $this->assertSame('service', PermissionGroups::subjectFor('destroy-service'));
        $this->assertSame('fuel-price', PermissionGroups::subjectFor('restore-fuel-price'));
        $this->assertSame('document', PermissionGroups::subjectFor('secure-download-document'));
        $this->assertSame('role', PermissionGroups::subjectFor('assign-role'));
        $this->assertSame('permission', PermissionGroups::subjectFor('revoke-permission'));

        $this->assertSame('Force Delete', PermissionGroups::actionLabelFor('force-delete-car-model'));
        $this->assertSame('Index', PermissionGroups::actionLabelFor('index-car'));
        $this->assertSame('Secure Download', PermissionGroups::actionLabelFor('secure-download-document'));
    }

    public function test_every_seeded_permission_lands_in_a_group(): void
    {
        $grouped = collect(PermissionGroups::all())->flatMap(fn (array $options): array => array_keys($options));

        $this->assertSame(Permission::count(), $grouped->count());
        $this->assertArrayNotHasKey(PermissionGroups::OTHER, PermissionGroups::all());
    }

    public function test_the_edit_form_is_hydrated_with_the_roles_current_permissions(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $indexCar = Permission::where('name', 'index-car')->firstOrFail();

        Livewire::test(EditRole::class, ['record' => $role->getKey()])
            ->assertFormSet(fn (array $state): bool => in_array(
                $indexCar->getKey(),
                $state['permission_groups']['car'] ?? [],
            ));
    }

    public function test_saving_the_edit_form_syncs_the_selected_permissions(): void
    {
        $role = Role::where('name', 'super-user')->firstOrFail();
        $this->assertCount(0, $role->permissions);

        $indexCar = Permission::where('name', 'index-car')->firstOrFail();
        $showCar = Permission::where('name', 'show-car')->firstOrFail();
        $indexBrand = Permission::where('name', 'index-brand')->firstOrFail();

        Livewire::test(EditRole::class, ['record' => $role->getKey()])
            ->fillForm([
                'permission_groups' => [
                    'car' => [$indexCar->getKey(), $showCar->getKey()],
                    'brand' => [$indexBrand->getKey()],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            ['index-brand', 'index-car', 'show-car'],
            $role->fresh()->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_creating_a_role_attaches_the_selected_permissions_under_the_api_guard(): void
    {
        $indexCar = Permission::where('name', 'index-car')->firstOrFail();

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'fleet-manager',
                'permission_groups' => ['car' => [$indexCar->getKey()]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'fleet-manager')->firstOrFail();

        $this->assertSame('api', $role->guard_name);
        $this->assertSame(['index-car'], $role->permissions->pluck('name')->all());
    }
}
