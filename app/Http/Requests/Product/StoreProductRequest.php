<?php

namespace App\Http\Requests\Product;

use App\Data\Product\ProductData;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_category_id' => 'nullable|exists:product_categories,id',
            'name' => 'required|string|min:2|max:120|unique:products,name',
            'sale_price' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'compositions' => 'required|array|min:1',
            'compositions.*.material_id' => 'required|exists:materials,id',
            'compositions.*.quantity_required' => 'required|numeric|min:0.01',
            'compositions.*.unit' => 'required|string|max:50',
        ];
    }

    public function toDto(): ProductData
    {
        return ProductData::from([
            ...$this->validated(),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
