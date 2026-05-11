<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\AssignRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserRoleController extends BaseController
{
    public function index(User $user): JsonResponse
    {
        $this->authorize('index-user');

        $roles = $user->roles()->with('permissions')->paginate();

        return $this->paginated($roles, RoleResource::class);
    }

    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        $user->assignRole($request->validated()['role']);
        $user->load('roles');

        return $this->success(RoleResource::collection($user->roles), 201, 'Role assigned successfully.');
    }

    public function destroy(User $user, Role $role): JsonResponse
    {
        $this->authorize('revoke-role');

        $user->removeRole($role);

        return $this->success([], 200, 'Role revoked successfully.');
    }
}
