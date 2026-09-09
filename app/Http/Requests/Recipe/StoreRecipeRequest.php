<?php

namespace App\Http\Requests\Recipe;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'yield_quantity' => ['required', 'numeric', 'min:0.01'],
            'yield_unit' => ['required', 'string', 'max:50'],
            'target_food_cost_percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.material_id' => ['required', 'integer', 'exists:materials,id', 'distinct'],
            'ingredients.*.net_quantity' => ['required', 'numeric', 'min:0.0001'],
            'ingredients.*.expected_yield_percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ];
    }
}
