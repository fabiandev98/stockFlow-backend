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
        $materialCategory = $this->materialCategoryFromRoute();

        return $materialCategory !== null
            && ($this->user()?->can('update', $materialCategory) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $materialCategoryId = $this->materialCategoryFromRoute()?->getKey();

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('material_categories', 'name')->ignore($materialCategoryId),
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
