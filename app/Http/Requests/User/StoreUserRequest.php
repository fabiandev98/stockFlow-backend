<?php

namespace App\Http\Requests\User;

use App\Data\User\UserData;
use App\Models\User;
use App\Rules\UserRoleRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
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

        $roleRules = $user->role()
            ? ['nullable', 'bail', 'numeric', 'min:1', 'exists:roles,id',
                new UserRoleRule($user->role())]
            : 'prohibited';

        return [
            'name' => 'required|string|max:191',
            'email' => 'required|unique:users,email|email|max:191',
            'password' => 'required|string|max:191|min:8|confirmed',
            'password_confirmation' => 'required|string|max:191|min:8',
            'role_id' => $roleRules,
        ];
    }

    public function toData(): UserData
    {
        return UserData::from($this->validated());
    }
}
