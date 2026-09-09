<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'planned_quantity' => 'nullable|numeric|min:0.01',
            'produced_quantity' => 'required|numeric|min:0.01',
            'production_date' => 'required|date|before_or_equal:today',
            'expiration_date' => 'nullable|date|after_or_equal:production_date',
            'expiration_override_reason' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'usages' => 'required|array|min:1',
            'usages.*.stock_batch_id' => 'nullable|required_without:usages.*.product_batch_id|distinct|exists:stock_batches,id',
            'usages.*.product_batch_id' => 'nullable|required_without:usages.*.stock_batch_id|distinct|exists:product_batches,id',
            'usages.*.quantity' => 'required|numeric|min:0.01',
        ];
    }
}
