<?php

namespace App\Http\Requests\ProductStockMovement;

use App\Data\ProductStockMovement\ProductStockMovementData;
use App\Models\ProductStockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductStockMovement::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_batch_id' => 'required|exists:product_batches,id',
            'type' => ['required', 'string', Rule::in(['waste', 'expired'])],
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:2000',
            'movement_date' => 'nullable|date',
        ];
    }

    public function toDto(): ProductStockMovementData
    {
        return ProductStockMovementData::from($this->validated());
    }
}
