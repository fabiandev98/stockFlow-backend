<?php

namespace App\Http\Requests\Role;

use App\Data\Role\RoleData;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->getRoleFromRoute(); // Gets the Role model instance

        return $this->user()?->can('update', $role) ?? false;
    }

    /**
     * Summary of rules
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->getRoleFromRoute();
        $roleId = $role?->getKey();

        $user = $this->user();
        if (! $user) {
            abort(Response::HTTP_FORBIDDEN, 'You can not use this action if there is not a user logged in');
        }

        $userRole = $user->role();
        $hierarchyCheck = $userRole ? $userRole->hierarchy : Role::MAX_ROLE_HIERARCHY - 1;

        $allPossiblePermissions = Role::getAllDenebPermissionsNames();

        return [
            'name' => [
                'required',
                Rule::unique('roles', 'name')->ignore($roleId),
                'min:2',
                'max:50',
            ],
            'hierarchy' => [
                'required',
                'numeric', // No min:1 here, can be same if other things change
                'gt:'.$hierarchyCheck,
            ],
            'description' => 'string|nullable',
            'permissions' => [
                'required',
                'array',
                'min:1',
            ],
            'permissions.*' => [
                'string',
                Rule::in($allPossiblePermissions),
            ],
        ];
    }

    public function toDto(): RoleData
    {
        return RoleData::from($this->validated());
    }

    private function getRoleFromRoute(): ?Role
    {
        $roleCandidate = $this->route('role');
        if ($roleCandidate && $roleCandidate instanceof Role) {
            return $roleCandidate;
        }

        return null;
    }
}
