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

    /** @return array{rating: string, issue_type: string|null, note: string|null} */
    public function noteData(): array
    {
        $data = $this->validated();

        return [
            'rating' => (string) $data['rating'],
            'issue_type' => isset($data['issue_type']) ? (string) $data['issue_type'] : null,
            'note' => isset($data['note']) ? (string) $data['note'] : null,
        ];
    }
}
