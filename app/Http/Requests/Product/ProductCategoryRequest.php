<?php

namespace App\Http\Requests\Product;

use App\Data\Product\ProductCategoryData;
use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $productCategory = $this->productCategoryFromRoute();

        return $productCategory !== null
            ? ($this->user()?->can('update', $productCategory) ?? false)
            : ($this->user()?->can('create', ProductCategory::class) ?? false);
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
                Rule::unique('product_categories', 'name')->ignore($this->productCategoryFromRoute()),
            ],
        ];
    }

    public function toDto(): ProductCategoryData
    {
        return ProductCategoryData::from($this->validated());
    }

    private function productCategoryFromRoute(): ?ProductCategory
    {
        $productCategory = $this->route('product_category');

        return $productCategory instanceof ProductCategory ? $productCategory : null;
    }
}
