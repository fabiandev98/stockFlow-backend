<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpStatus; // Alias for clarity

class RoleController extends Controller
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $query = $this->roleService->indexQueryBuilder();

        return RoleResource::collection(
            $query->paginate(25)
        );
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        // Authorization is handled by StoreRoleRequest
        /** @var \App\Models\User $user */
        $user = $request->user();

        $role = $this->roleService->createRole(
            $request->toDto(),
            $user,
        );

        return new RoleResource($role);
    }

    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return new RoleResource($role);
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role,
    ): RoleResource {
        // Authorization is handled by UpdateRoleRequest
        // Authorization is handled by StoreRoleRequest
        /** @var \App\Models\User $user */
        $user = $request->user();

        $updatedRole = $this->roleService->updateRole(
            $role,
            $request->toDto(),
            $user,
        );

        return new RoleResource($updatedRole);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);
        $this->roleService->deleteRole($role);

        return response()->json(null, HttpStatus::HTTP_NO_CONTENT);
    }
}
