<?php

namespace App\Http\Requests\Purchase;

use App\Data\Purchase\PurchaseData;
use App\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('purchase');

        return $purchase instanceof Purchase
            && ($this->user()?->can('update', $purchase) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'nullable|required_without:items.*.product_id|exists:materials,id',
            'items.*.product_id' => 'nullable|required_without:items.*.material_id|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.expiration_date' => 'nullable|date',
        ];
    }

    public function toDto(): PurchaseData
    {
        return PurchaseData::from($this->validated());
    }
}
