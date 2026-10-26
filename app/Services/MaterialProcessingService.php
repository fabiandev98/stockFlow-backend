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
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MaterialProcessingService
{
    public function create(MaterialProcessingData $data, User $user): MaterialProcessing
    {
        return DB::transaction(function () use ($data, $user): MaterialProcessing {
            $outputMaterial = Material::query()->with('processingComponents.rawMaterial')->lockForUpdate()->findOrFail($data->output_material_id);
            if ($outputMaterial->processing_type !== 'processed') {
                $this->invalid('The output material must be classified as processed.');
            }
            if ($outputMaterial->processingComponents->isEmpty()) {
                $this->invalid('The processed material must have a raw material composition.');
            }

            $allocatedInputs = [];
            $totalCost = 0.0;
            $outputQuantity = (float) $data->output_quantity;
            $processedAt = Carbon::parse($data->processed_at);

            if ($data->inputs !== []) {
                $allocatedInputs = $this->validateSelectedInputs($data, $outputMaterial, $processedAt);
                $totalCost = array_sum(array_column($allocatedInputs, 'cost'));
            }

            foreach ($outputMaterial->processingComponents as $component) {
                $rawMaterial = $component->rawMaterial;
                if (! $rawMaterial instanceof Material) {
                    $this->invalid('A processed material has an invalid raw material composition.');
                }

                $requiredQuantity = round((float) $component->quantity_per_unit * $outputQuantity, 4);
                if ($data->inputs !== []) {
                    $selectedQuantity = array_sum(array_map(
                        fn (array $input): float => $input['batch']->material_id === $component->raw_material_id ? $input['quantity'] : 0,
                        $allocatedInputs,
                    ));
                    if ($selectedQuantity > 0 && $selectedQuantity + 0.0001 < $requiredQuantity) {
                        $this->invalid("The selected quantity for {$rawMaterial->name} is below the required quantity.");
                    }

                    continue;
                }

                $remainingQuantity = $requiredQuantity;
                $batches = StockBatch::query()
                    ->with('material')
                    ->where('material_id', $component->raw_material_id)
                    ->available()
                    ->validOn($processedAt)
                    ->fefo()
                    ->lockForUpdate()
                    ->get();

                foreach ($batches as $batch) {
                    if ($remainingQuantity <= 0) {
                        break;
                    }

                    $quantity = min($remainingQuantity, (float) $batch->available_quantity);
                    $cost = round($quantity * (float) $batch->unit_cost, 4);
                    $allocatedInputs[] = compact('batch', 'quantity', 'cost');
                    $totalCost += $cost;
                    $remainingQuantity = round($remainingQuantity - $quantity, 4);
                }

                if ($remainingQuantity > 0) {
                    $this->invalid("Insufficient available stock for {$rawMaterial->name}.");
                }
            }

            $unitCost = round($totalCost / $outputQuantity, 6);
            $singleRawMaterial = $outputMaterial->processingComponents->count() === 1
                ? $outputMaterial->processingComponents->first()->rawMaterial
                : null;
            $singleComponent = $singleRawMaterial instanceof Material
                && $singleRawMaterial->unit === $outputMaterial->unit;
            $inputQuantity = $singleComponent
                ? array_sum(array_column($allocatedInputs, 'quantity'))
                : null;
            $wasteQuantity = $singleComponent ? round($inputQuantity - $outputQuantity, 4) : null;
            $yield = $singleComponent && $inputQuantity > 0 ? round(($outputQuantity / $inputQuantity) * 100, 4) : null;
            $expirationDate = $data->expiration_date
                ? Carbon::parse($data->expiration_date)
                : ($outputMaterial->is_perishable && $outputMaterial->default_expiration_days
                    ? $processedAt->copy()->addDays($outputMaterial->default_expiration_days)
                    : null);

            $processing = MaterialProcessing::create([
                'operation_uuid' => $data->operation_uuid,
                'source_material_id' => $singleComponent ? $singleRawMaterial->id : null,
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

            foreach ($allocatedInputs as $input) {
                /** @var StockBatch $batch */
                $batch = $input['batch'];
                $batch->consume($input['quantity'], 4);
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

    /**
     * @param  array<int, MaterialProcessingData>  $items
     * @return array<int, MaterialProcessing>
     */
    public function createMany(array $items, User $user): array
    {
        return DB::transaction(function () use ($items, $user): array {
            $operationUuid = (string) Str::uuid();

            return array_map(fn (MaterialProcessingData $item): MaterialProcessing => $this->create(
                new MaterialProcessingData(
                    output_material_id: $item->output_material_id,
                    output_quantity: $item->output_quantity,
                    processed_at: $item->processed_at,
                    expiration_date: $item->expiration_date,
                    notes: $item->notes,
                    inputs: $item->inputs,
                    operation_uuid: $operationUuid,
                ),
                $user,
            ), $items);
        });
    }

    private function invalid(string $message): never
    {
        throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $message);
    }

    /**
     * @return array<int, array{batch: StockBatch, quantity: float, cost: float}>
     */
    private function validateSelectedInputs(MaterialProcessingData $data, Material $outputMaterial, Carbon $processedAt): array
    {
        $selectedBatchIds = array_map(fn (array $input): int => (int) $input['stock_batch_id'], $data->inputs);
        if (count($selectedBatchIds) !== count(array_unique($selectedBatchIds))) {
            $this->invalid('A batch can only be selected once for each processed material.');
        }
        $batches = StockBatch::query()
            ->whereIn('id', $selectedBatchIds)
            ->available()
            ->validOn($processedAt)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $rawMaterialIds = $outputMaterial->processingComponents->pluck('raw_material_id')->all();
        $allocatedInputs = [];

        foreach ($data->inputs as $input) {
            $batch = $batches->get((int) $input['stock_batch_id']);
            $quantity = round((float) $input['quantity'], 4);
            if (! $batch instanceof StockBatch
                || ! in_array($batch->material_id, $rawMaterialIds, true)
                || (float) $batch->available_quantity + 0.0001 < $quantity
            ) {
                $this->invalid('One or more selected batches are not valid for this processing.');
            }

            $allocatedInputs[] = [
                'batch' => $batch,
                'quantity' => $quantity,
                'cost' => round($quantity * (float) $batch->unit_cost, 4),
            ];
        }

        return $allocatedInputs;
    }
}
