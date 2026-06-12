<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\SignUpRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(
            $this->userService->indexQueryBuilder()
                ->paginate(25)
        );
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);
        $user->loadMissing('roles');

        return new UserResource($user);
    }

    public function store(StoreUserRequest $request): UserResource
    {
        // Authorization handled by the request
        $newUser = $this->userService->createUser(
            $request->toData()
        );

        return new UserResource($newUser);
    }

    public function signUp(SignUpRequest $request): UserResource
    {
        // Authorization handled by the request
        $newUser = $this->userService->signUpUser(
            $request->toData(),
        );

        return new UserResource($newUser);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
    ): UserResource {
        // Authorization handled by the request
        $updatedUser = $this->userService->updateUser(
            $user,
            $request->toData()
        );

        return new UserResource($updatedUser);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        $this->userService->deleteUser($user);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function showMe(Request $request): UserResource
    {
        $user = $request->user();
        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }
        $user->loadMissing('roles');

        return new UserResource($user);
    }

    public function updatePassword(
        UpdatePasswordRequest $request,
        User $user,
    ): JsonResponse {
        // Authorization is handled by UpdatePasswordRequest
        $this->userService->updatePassword($user, $request->toData());

        return response()->json(
            ['message' => 'Password updated successfully.'],
            Response::HTTP_OK,
        );
    }

    public function rolesBelow(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }
        $userRole = $user->role();
        if (! $userRole) {
            abort(
                Response::HTTP_FORBIDDEN,
                'User does not have an assigned role.',
            );
        }

        $query = Role::query();
        // Superadmin (hierarchy 0) can see all roles.
        // Others see roles with hierarchy > their own.
        if ($userRole->hierarchy !== 0) {
            $query->where('hierarchy', '>', $userRole->hierarchy);
        }
        $roles = $query->orderBy('hierarchy', 'asc')->get();

        return RoleResource::collection($roles);
    }
}
