<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncPermissionsRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends BaseController
{
    public function __construct(private RoleRepository $roleRepository) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = $this->roleRepository->spatie()->paginate($this->perPage());

        return $this->paginated($roles, RoleResource::class);
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $role->load('permissions');

        return $this->success(new RoleResource($role));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roleRepository->create($request->validated());

        return $this->success(new RoleResource($role), 201, 'Role created successfully.');
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role = $this->roleRepository->update($request->validated(), $role->id);

        return $this->success(new RoleResource($role));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        abort_if(
            in_array($role->name, ['admin', 'super-user', 'user']),
            403,
            'Default system roles cannot be deleted.'
        );

        $this->roleRepository->delete($role->id);

        return $this->success([], 200, 'Role deleted successfully.');
    }

    public function syncPermissions(SyncPermissionsRequest $request, Role $role): JsonResponse
    {
        $this->authorize('syncPermissions', $role);

        $role->syncPermissions($request->validated()['permissions']);
        $role->load('permissions');

        return $this->success(new RoleResource($role));
    }

    public function revokePermission(Request $request, Role $role, Permission $permission): JsonResponse
    {
        $this->authorize('revokePermission', $role);

        $role->revokePermissionTo($permission);

        return $this->success([], 200, 'Permission revoked from role.');
    }
}
