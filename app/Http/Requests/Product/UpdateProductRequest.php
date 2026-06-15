<?php

namespace App\Http\Requests\Product;

use App\Data\Product\ProductData;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->productFromRoute();

        return $product !== null
            && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->productFromRoute()?->getKey();

        return [
            'product_category_id' => 'nullable|exists:product_categories,id',
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('products', 'name')->ignore($productId),
            ],
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

    private function productFromRoute(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
