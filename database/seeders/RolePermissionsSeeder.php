<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionsSeeder extends Seeder
{
    /**
     * All domain models that receive standard CRUD permissions.
     */
    private array $models = [
        'car',
        'fill-up',
        'trip',
        'parking-record',
        'service-center',
        'service',
        'item',
        'brand',
        'car-model',
        'document',
        'user',
        'role',
        'permission',
        'car-log',
        'fuel-price',
        'reminder',
        'cost',
        'issue',
    ];

    const INDEX        = 'index';
    const SHOW         = 'show';
    const CREATE       = 'create';
    const EDIT         = 'edit';
    const DELETE       = 'destroy';
    const FORCE_DELETE = 'force-delete';
    const RESTORE      = 'restore';

    const CRUD_PERMISSIONS = [
        self::INDEX,
        self::SHOW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
        self::FORCE_DELETE,
        self::RESTORE,
    ];

    const ADMIN      = 'admin';
    const SUPER_USER = 'super-user';
    const USER       = 'user';

    const DEFAULT_ROLES = [
        self::ADMIN,
        self::SUPER_USER,
        self::USER,
    ];

    private array $customPermissions = [
        'secure-download-document',
        'secure-download-issue',
        'assign-role',
        'revoke-role',
        'assign-permission',
        'revoke-permission',
        'index-permission',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createPermissions();
        $this->createRoles();

        $this->assignPermissionToRole(self::ADMIN, 'all');
    }

    private function createPermissions(): void
    {
        foreach ($this->models as $model) {
            foreach (self::CRUD_PERMISSIONS as $action) {
                Permission::firstOrCreate(['name' => $action . '-' . $model]);
            }
        }

        foreach ($this->customPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    private function createRoles(): void
    {
        foreach (self::DEFAULT_ROLES as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }

    private function assignPermissionToRole(string $roleName, array|string $permissions = 'all'): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        if (is_array($permissions)) {
            $role->givePermissionTo($permissions);
        } elseif (strtolower($permissions) === 'all') {
            $role->givePermissionTo(Permission::all());
        }
    }
}
