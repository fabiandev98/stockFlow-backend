<?php

namespace App\Http\Requests\MaterialCategory;

use App\Data\MaterialCategory\MaterialCategoryData;
use App\Models\MaterialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $materialCategory = $this->route('material_category');

        return $materialCategory instanceof MaterialCategory
            && ($this->user()?->can('update', $materialCategory) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $materialCategory = $this->route('material_category');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('material_categories', 'name')->ignore($materialCategory?->id),
            ],
        ];
    }

    public function toDto(): MaterialCategoryData
    {
        return MaterialCategoryData::from($this->validated());
    }
}
