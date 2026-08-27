<?php

namespace App\Http\Requests\MaterialCategory;

use App\Data\MaterialCategory\MaterialCategoryData;
use App\Models\MaterialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $materialCategory = $this->materialCategoryFromRoute();

        return $materialCategory !== null
            ? ($this->user()?->can('update', $materialCategory) ?? false)
            : ($this->user()?->can('create', MaterialCategory::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('material_categories', 'name')->ignore($this->materialCategoryFromRoute()),
            ],
        ];
    }

    public function toDto(): MaterialCategoryData
    {
        return MaterialCategoryData::from($this->validated());
    }

    private function materialCategoryFromRoute(): ?MaterialCategory
    {
        $materialCategory = $this->route('material_category');

        return $materialCategory instanceof MaterialCategory ? $materialCategory : null;
    }
}
