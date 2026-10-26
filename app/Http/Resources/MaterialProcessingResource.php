<?php

namespace App\Http\Resources;

use App\Models\MaterialProcessing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaterialProcessing */
class MaterialProcessingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation_uuid' => $this->operation_uuid,
            'source_material_id' => $this->source_material_id,
            'source_material' => new MaterialResource($this->whenLoaded('sourceMaterial')),
            'output_material_id' => $this->output_material_id,
            'output_material' => new MaterialResource($this->whenLoaded('outputMaterial')),
            'output_stock_batch_id' => $this->output_stock_batch_id,
            'output_stock_batch' => new StockBatchResource($this->whenLoaded('outputStockBatch')),
            'input_quantity' => $this->input_quantity,
            'output_quantity' => $this->output_quantity,
            'waste_quantity' => $this->waste_quantity,
            'yield_percentage' => $this->yield_percentage,
            'total_input_cost' => $this->total_input_cost,
            'output_unit_cost' => $this->output_unit_cost,
            'processed_at' => $this->processed_at,
            'expiration_date' => $this->expiration_date,
            'notes' => $this->notes,
            'user' => new UserResource($this->whenLoaded('user')),
            'inputs' => $this->whenLoaded('inputs', fn () => $this->inputs->map(fn ($input) => [
                'stock_batch_id' => $input->stock_batch_id,
                'stock_batch' => new StockBatchResource($input->stockBatch),
                'quantity' => $input->quantity,
                'unit_cost' => $input->unit_cost,
                'total_cost' => $input->total_cost,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
