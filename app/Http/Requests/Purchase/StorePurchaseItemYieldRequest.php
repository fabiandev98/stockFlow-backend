<?php

namespace App\Http\Requests\Purchase;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseItemYieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchase = $this->route('purchase');
        $item = $this->route('purchaseItem');

        return $purchase instanceof Purchase
            && $item instanceof PurchaseItem
            && $item->purchase_id === $purchase->id
            && ($this->user()?->can('update', $purchase) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PurchaseItem|null $item */
        $item = $this->route('purchaseItem');
        $acceptedQuantity = (float) ($item?->accepted_quantity ?? 0);

        return [
            'usable_quantity' => ['required', 'numeric', 'min:0', 'max:'.$acceptedQuantity],
            'processing_waste_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var PurchaseItem|null $item */
            $item = $this->route('purchaseItem');

            if ((float) $this->input('usable_quantity') < (float) ($item?->accepted_quantity ?? 0)
                && blank($this->input('processing_waste_reason'))) {
                $validator->errors()->add('processing_waste_reason', __('validation.required'));
            }
        }];
    }
}
