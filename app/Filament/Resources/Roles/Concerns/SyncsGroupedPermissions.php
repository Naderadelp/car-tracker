<?php

namespace App\Filament\Resources\Roles\Concerns;

use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Support\PermissionGroups;
use App\Models\Permission;

/**
 * Bridges the grouped CheckboxLists in RoleForm to spatie's permissions
 * relationship.
 *
 * The form paints one CheckboxList per permission subject, so the state is a
 * nested array keyed by subject rather than something Filament can bind to a
 * relationship directly. These hooks flatten it on save and rebuild it on fill.
 */
trait SyncsGroupedPermissions
{
    /** @var list<int|string> */
    protected array $selectedPermissionIds = [];

    protected function extractPermissionIds(array $data): array
    {
        $this->selectedPermissionIds = collect($data[RoleForm::STATE_KEY] ?? [])
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        unset($data[RoleForm::STATE_KEY]);

        return $data;
    }

    protected function syncSelectedPermissions(): void
    {
        $permissions = Permission::query()
            ->whereIn('id', $this->selectedPermissionIds)
            ->get();

        $this->record->syncPermissions($permissions);
    }

    /**
     * @return array<string, list<int|string>>
     */
    protected function groupPermissionIds(iterable $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $grouped[PermissionGroups::subjectFor($permission->name)][] = $permission->getKey();
        }

        return $grouped;
    }
}
