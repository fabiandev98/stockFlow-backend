<?php

namespace App\Http\Requests\Product;

use App\Data\Product\ProductCategoryData;
use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120|unique:product_categories,name',
        ];
    }

    public function toDto(): ProductCategoryData
    {
        return ProductCategoryData::from($this->validated());
    }
}
