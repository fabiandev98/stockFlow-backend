<?php

namespace App\Http\Requests\Auth;

use App\Enums\DenebPermission;
use Illuminate\Foundation\Http\FormRequest;

class CreateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return
            $user->hasPermissionTo(DenebPermission::MANAGE_API_KEYS_CREATE) ||
            $user->hasPermissionTo(DenebPermission::API_KEYS_CREATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key_name' => 'required|string|max:191',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|max:191',
        ];
    }
}
