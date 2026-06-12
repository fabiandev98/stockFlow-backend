<?php

namespace App\Http\Requests\User;

use App\Data\User\UserData;
use Illuminate\Foundation\Http\FormRequest;

class SignUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Summary of rules
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'email' => 'required|unique:users,email|email|max:191',
            'password' => 'required|string|max:191|min:8|confirmed',
            'password_confirmation' => 'required|string|max:191|min:8',
        ];
    }

    public function toData(): UserData
    {
        return UserData::from($this->validated());
    }
}
