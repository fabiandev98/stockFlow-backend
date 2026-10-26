<?php

namespace App\Http\Requests\MaterialProcessing;

use App\Data\MaterialProcessing\MaterialProcessingData;
use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialProcessingBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockMovement::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'processed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.output_material_id' => ['required', 'integer', 'distinct', 'exists:materials,id'],
            'items.*.output_quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.expiration_date' => ['nullable', 'date', 'after_or_equal:processed_at'],
            'items.*.inputs' => ['required', 'array', 'min:1'],
            'items.*.inputs.*.stock_batch_id' => ['required', 'integer', 'exists:stock_batches,id'],
            'items.*.inputs.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    /** @return array<int, MaterialProcessingData> */
    public function toDtos(): array
    {
        $data = $this->validated();

        return array_map(fn (array $item): MaterialProcessingData => new MaterialProcessingData(
            output_material_id: $item['output_material_id'],
            output_quantity: (float) $item['output_quantity'],
            processed_at: $data['processed_at'],
            expiration_date: $item['expiration_date'] ?? null,
            notes: $data['notes'] ?? null,
            inputs: $item['inputs'],
        ), $data['items']);
    }
}
