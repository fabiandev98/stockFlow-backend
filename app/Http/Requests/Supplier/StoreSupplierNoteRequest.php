<?php

namespace App\Http\Requests\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier && ($this->user()?->can('update', $supplier) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rating' => 'required|in:good,regular,bad',
            'issue_type' => 'nullable|in:quality,shortage,packaging,expiration,other',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
