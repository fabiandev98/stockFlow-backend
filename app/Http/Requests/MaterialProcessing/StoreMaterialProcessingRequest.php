<?php

namespace App\Http\Requests\MaterialProcessing;

use App\Data\MaterialProcessing\MaterialProcessingData;
use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialProcessingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockMovement::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'output_material_id' => ['required', 'integer', 'exists:materials,id'],
            'output_quantity' => ['required', 'numeric', 'min:0.0001'],
            'processed_at' => ['required', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:processed_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'inputs' => ['nullable', 'array', 'min:1'],
            'inputs.*.stock_batch_id' => ['required', 'integer', 'distinct', 'exists:stock_batches,id'],
            'inputs.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    public function toDto(): MaterialProcessingData
    {
        return MaterialProcessingData::from($this->validated());
    }
}
