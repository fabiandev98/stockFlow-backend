<?php

namespace App\Http\Requests\User;

use App\Data\User\UserData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class UpdatePasswordRequest extends FormRequest
{
    protected ?User $userToUpdate = null;

    protected function prepareForValidation()
    {
        $userParam = $this->route('user');
        if ($userParam instanceof User) {
            $this->userToUpdate = $userParam;
        } elseif ($userParam) {
            /**
             * @var User
             */
            $userCandidate = User::findOrFail($userParam);
            $this->userToUpdate = $userCandidate;
        }
    }

    public function authorize(): bool
    {
        if (! $this->userToUpdate) {
            return false;
        }

        return $this->user()?->can('update', $this->userToUpdate) ?? false;
    }

    /**
     * Summary of rules
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userToUpdate = $this->userToUpdate;

        if (! $userToUpdate) {
            abort(Response::HTTP_BAD_REQUEST, 'There must be a user to be updated.');
        }

        $requiresCurrentPassword = $this->user()?->id === $userToUpdate->id;

        return [
            'current_password' => [
                $requiresCurrentPassword ? 'required' : 'nullable',
                'string',
                'max:191',
                'min:6',
                function ($attribute, $value, $fail) use ($requiresCurrentPassword, $userToUpdate) {
                    if (! $requiresCurrentPassword || blank($value)) {
                        return;
                    }

                    if (
                        ! Hash::check($value, $userToUpdate->password)
                    ) {
                        $fail(trans('auth.password'));
                    }
                },
            ],
            'password' => 'required|string|max:191|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ];
    }

    public function toData(): UserData
    {
        return UserData::from($this->validated());
    }
}
