<?php

namespace App\Services;

use App\Data\MaterialProcessing\MaterialProcessingData;
use App\Models\Material;
use App\Models\MaterialProcessing;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MaterialProcessingService
{
    public function create(MaterialProcessingData $data, User $user): MaterialProcessing
    {
        return DB::transaction(function () use ($data, $user): MaterialProcessing {
            $outputMaterial = Material::query()->lockForUpdate()->findOrFail($data->output_material_id);
            if ($outputMaterial->processing_type !== 'processed') {
                $this->invalid('The output material must be classified as processed.');
            }

            $batches = [];
            $sourceMaterial = null;
            $inputQuantity = 0.0;
            $totalCost = 0.0;

            foreach ($data->inputs as $input) {
                $batch = StockBatch::query()->with('material')->lockForUpdate()->findOrFail($input['stock_batch_id']);
                $quantity = (float) $input['quantity'];

                if ($batch->status !== 'available' || $quantity > (float) $batch->available_quantity) {
                    $this->invalid('One of the selected batches does not have enough available stock.');
                }
                if ($batch->expiration_date !== null && $batch->expiration_date->isPast()) {
                    $this->invalid('Expired batches cannot be processed.');
                }
                if ($batch->material->processing_type !== 'raw') {
                    $this->invalid('Only raw materials can be used as processing input.');
                }
                if ($batch->material->unit !== $outputMaterial->unit) {
                    $this->invalid('Input and output materials must use the same unit.');
                }
                if ($sourceMaterial !== null && $sourceMaterial->id !== $batch->material_id) {
                    $this->invalid('All input batches must belong to the same raw material.');
                }

                $sourceMaterial ??= $batch->material;
                $cost = round($quantity * (float) $batch->unit_cost, 4);
                $inputQuantity += $quantity;
                $totalCost += $cost;
                $batches[] = ['batch' => $batch, 'quantity' => $quantity, 'cost' => $cost];
            }

            if ($sourceMaterial === null || $sourceMaterial->id === $outputMaterial->id) {
                $this->invalid('A processed material must come from a different raw material.');
            }

            $outputQuantity = (float) $data->output_quantity;
            if ($outputQuantity > $inputQuantity) {
                $this->invalid('Processed output cannot exceed the raw quantity used.');
            }

            $wasteQuantity = round($inputQuantity - $outputQuantity, 4);
            $yield = round(($outputQuantity / $inputQuantity) * 100, 4);
            $unitCost = round($totalCost / $outputQuantity, 6);
            $processedAt = Carbon::parse($data->processed_at);
            $expirationDate = $data->expiration_date
                ? Carbon::parse($data->expiration_date)
                : ($outputMaterial->is_perishable && $outputMaterial->default_expiration_days
                    ? $processedAt->copy()->addDays($outputMaterial->default_expiration_days)
                    : null);

            $processing = MaterialProcessing::create([
                'source_material_id' => $sourceMaterial->id,
                'output_material_id' => $outputMaterial->id,
                'user_id' => $user->id,
                'input_quantity' => $inputQuantity,
                'output_quantity' => $outputQuantity,
                'waste_quantity' => $wasteQuantity,
                'yield_percentage' => $yield,
                'total_input_cost' => $totalCost,
                'output_unit_cost' => $unitCost,
                'processed_at' => $processedAt->toDateString(),
                'expiration_date' => $expirationDate?->toDateString(),
                'notes' => $data->notes,
            ]);

            foreach ($batches as $input) {
                /** @var StockBatch $batch */
                $batch = $input['batch'];
                $remaining = round((float) $batch->available_quantity - $input['quantity'], 4);
                $batch->update(['available_quantity' => $remaining, 'status' => $remaining > 0 ? 'available' : 'depleted']);
                $processing->inputs()->create([
                    'stock_batch_id' => $batch->id,
                    'quantity' => $input['quantity'],
                    'unit_cost' => $batch->unit_cost,
                    'total_cost' => $input['cost'],
                ]);
                StockMovement::create([
                    'material_id' => $batch->material_id,
                    'stock_batch_id' => $batch->id,
                    'material_processing_id' => $processing->id,
                    'user_id' => $user->id,
                    'type' => 'processing_out',
                    'quantity' => $input['quantity'],
                    'reason' => 'Material processing',
                    'movement_date' => $processedAt,
                ]);
            }

            $outputBatch = StockBatch::create([
                'material_id' => $outputMaterial->id,
                'material_processing_id' => $processing->id,
                'initial_quantity' => $outputQuantity,
                'available_quantity' => $outputQuantity,
                'unit_cost' => $unitCost,
                'received_date' => $processedAt->toDateString(),
                'expiration_date' => $expirationDate?->toDateString(),
                'status' => 'available',
            ]);
            $processing->update(['output_stock_batch_id' => $outputBatch->id]);
            StockMovement::create([
                'material_id' => $outputMaterial->id,
                'stock_batch_id' => $outputBatch->id,
                'material_processing_id' => $processing->id,
                'user_id' => $user->id,
                'type' => 'processing_in',
                'quantity' => $outputQuantity,
                'reason' => 'Material processing output',
                'movement_date' => $processedAt,
            ]);

            return $processing->fresh(['sourceMaterial.category', 'outputMaterial.category', 'outputStockBatch.material', 'inputs.stockBatch.material', 'user']) ?? $processing;
        });
    }

    private function invalid(string $message): never
    {
        throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $message);
    }
}
