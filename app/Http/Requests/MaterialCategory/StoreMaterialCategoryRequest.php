<?php

namespace App\Http\Requests\MaterialCategory;

use App\Data\MaterialCategory\MaterialCategoryData;
use App\Models\MaterialCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MaterialCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120|unique:material_categories,name',
        ];
    }

    public function toDto(): MaterialCategoryData
    {
        return MaterialCategoryData::from($this->validated());
    }
}
