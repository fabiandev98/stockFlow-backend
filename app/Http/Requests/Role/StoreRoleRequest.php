<?php

namespace App\Http\Requests\Role;

use App\Data\Role\RoleData;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * Summary of rules
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        if (! $user) {
            abort(Response::HTTP_FORBIDDEN, 'You can not use this action if there is not a user logged in');
        }

        $userRole = $user->role();
        $hierarchyCheck = $userRole ? $userRole->hierarchy : Role::MAX_ROLE_HIERARCHY - 1;
        $allPossiblePermissions = Role::getAllDenebPermissionsNames();

        return [
            'name' => 'required|unique:roles,name|min:2|max:50',
            'hierarchy' => [
                'required',
                'numeric',
                'min:1',
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
}
