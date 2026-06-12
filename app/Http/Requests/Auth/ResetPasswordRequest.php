<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'email' => 'required|email|max:191|exists:users,email',
            'password' => 'required|string|min:8|max:191|confirmed',
            'password_confirmation' => 'required|string|min:8|max:191',
        ];
    }
}
