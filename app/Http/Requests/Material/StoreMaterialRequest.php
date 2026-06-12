<?php

namespace App\Http\Requests\Material;

use App\Data\Material\MaterialData;
use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Material::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'material_category_id' => 'nullable|exists:material_categories,id',
            'name' => 'required|string|min:2|max:120|unique:materials,name',
            'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|numeric|min:0',
            'is_perishable' => 'sometimes|boolean',
            'default_expiration_days' => 'nullable|integer|min:1|required_if:is_perishable,true',
        ];
    }

    public function toDto(): MaterialData
    {
        return MaterialData::from([
            ...$this->validated(),
            'is_perishable' => $this->boolean('is_perishable', false),
        ]);
    }
}
