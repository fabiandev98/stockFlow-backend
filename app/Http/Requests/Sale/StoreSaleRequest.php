<?php

namespace App\Http\Requests\Sale;

use App\Data\Sale\SaleData;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Sale::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sale_date' => 'required|date|before_or_equal:today',
            'covers' => 'nullable|integer|min:1',
            'discount_amount' => 'sometimes|numeric|min:0',
            'tax_rate' => 'sometimes|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }

    public function toDto(): SaleData
    {
        return SaleData::from([
            ...$this->validated(),
            'covers' => $this->integer('covers') ?: null,
            'discount_amount' => (float) $this->input('discount_amount', 0),
            'tax_rate' => (float) $this->input('tax_rate', 0),
        ]);
    }
}
