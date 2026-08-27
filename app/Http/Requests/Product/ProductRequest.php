<?php

namespace App\Http\Requests\Product;

use App\Data\Product\ProductData;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->productFromRoute();

        return $product !== null
            ? ($this->user()?->can('update', $product) ?? false)
            : ($this->user()?->can('create', Product::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_category_id' => 'nullable|exists:product_categories,id',
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('products', 'name')->ignore($this->productFromRoute()),
            ],
            'sale_price' => 'required|numeric|min:0',
            'is_composed' => 'sometimes|boolean',
            'production_mode' => 'sometimes|in:on_sale,batch',
            'shelf_life_days' => 'nullable|integer|min:0|max:3650',
            'is_active' => 'sometimes|boolean',
            'compositions' => 'nullable|array',
            'compositions.*.material_id' => 'required|exists:materials,id',
            'compositions.*.quantity_required' => 'required|numeric|min:0.01',
            'compositions.*.unit' => 'sometimes|string|max:50',
        ];
    }

    public function toDto(): ProductData
    {
        return ProductData::from([
            ...$this->validated(),
            'is_composed' => $this->boolean('is_composed', true),
            'production_mode' => $this->input('production_mode', 'on_sale'),
            'shelf_life_days' => $this->input('shelf_life_days'),
            'is_active' => $this->boolean('is_active', true),
            'compositions' => $this->validated('compositions', []),
        ]);
    }

    private function productFromRoute(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
