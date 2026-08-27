<?php

namespace App\Http\Requests\Sale;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class CancelSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale
            && ($this->user()?->can('cancel', $sale) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:2000',
        ];
    }
}
