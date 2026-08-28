<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepository;
use Illuminate\Http\JsonResponse;

class PermissionController extends BaseController
{
    public function __construct(private PermissionRepository $permissionRepository) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $permissions = $this->permissionRepository->spatie()->paginate($this->perPage());

        return $this->paginated($permissions, PermissionResource::class);
    }
}
