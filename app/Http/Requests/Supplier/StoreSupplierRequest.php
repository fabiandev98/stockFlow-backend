<?php

namespace App\Http\Requests\Supplier;

use App\Data\Supplier\SupplierData;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Supplier::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120|unique:suppliers,name',
            'contact_name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function toDto(): SupplierData
    {
        return SupplierData::from($this->validated());
    }
}
