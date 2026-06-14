<?php

namespace App\Http\Requests\Supplier;

use App\Data\Supplier\SupplierData;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            && ($this->user()?->can('update', $supplier) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('suppliers', 'name')->ignore($supplier?->id),
            ],
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
