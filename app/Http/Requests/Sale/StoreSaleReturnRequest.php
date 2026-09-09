<?php

namespace App\Http\Requests\Sale;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('sale') instanceof Sale && ($this->user()?->can('returnSale', $this->route('sale')) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'return_date' => 'required|date|before_or_equal:today',
            'reason' => 'required|string|min:3|max:2000',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer|distinct|exists:sale_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.disposition' => ['required', 'string', Rule::in(['restock', 'waste'])],
        ];
    }

    /**
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $sale = $this->route('sale');

            if ($sale instanceof Sale
                && $this->date('return_date')?->startOfDay()->isBefore($sale->sale_date->startOfDay())) {
                $validator->errors()->add('return_date', 'The return date cannot be before the sale date.');
            }
        }];
    }
}
