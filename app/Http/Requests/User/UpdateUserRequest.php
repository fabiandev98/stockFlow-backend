<?php

namespace App\Http\Requests\User;

use App\Data\User\UserData;
use App\Models\User;
use App\Rules\UserRoleRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserRequest extends FormRequest
{
    private ?User $userToUpdate = null;

    protected function prepareForValidation(): void
    {
        $this->userToUpdate = $this->route('user') instanceof User
            ? $this->route('user')
            : User::findOrFail($this->route('user'));
    }

    public function authorize(): bool
    {
        return $this->userToUpdate
            && $this->user()?->can('update', $this->userToUpdate);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->userToUpdate) {
            abort(Response::HTTP_BAD_REQUEST, 'There must be a user to be updated.');
        }

        $currentUser = $this->user();
        if (! $currentUser) {
            abort(Response::HTTP_FORBIDDEN, 'There must be an authenticated user for this action.');
        }

        $currentUserRole = $currentUser->role();

        return [
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191|unique:users,email,'.
                $this->userToUpdate->id,
            'role_id' => empty($currentUserRole) ? [
                'role_id' => 'prohibited',
            ] : [
                'nullable',
                'bail',
                'numeric',
                'min:1',
                'exists:roles,id',
                new UserRoleRule($currentUserRole),
            ],
        ];
    }

    public function toData(): UserData
    {
        return UserData::from($this->validated());
    }
}
