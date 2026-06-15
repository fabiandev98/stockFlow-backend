<?php

namespace App\Http\Requests\StockMovement;

use App\Data\StockMovement\StockMovementData;
use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockMovement::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stock_batch_id' => 'required|exists:stock_batches,id',
            'type' => [
                'required',
                'string',
                Rule::in([
                    'manual_in',
                    'manual_out',
                    'adjustment_in',
                    'adjustment_out',
                    'waste',
                    'expired',
                ]),
            ],
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:2000',
            'movement_date' => 'nullable|date',
        ];
    }

    public function toDto(): StockMovementData
    {
        return StockMovementData::from($this->validated());
    }
}
