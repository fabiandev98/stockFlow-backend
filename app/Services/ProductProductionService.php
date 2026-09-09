<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductProduction;
use App\Models\ProductStockMovement;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductProductionService
{
    /** @param array<string, mixed> $data */
    public function create(Product $product, array $data, User $user): ProductProduction
    {
        return DB::transaction(function () use ($product, $data, $user) {
            $product = Product::query()->with('compositions')->lockForUpdate()->findOrFail($product->id);
            if (! $product->is_composed || $product->production_mode !== 'batch') {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'This product is not configured for batch production');
            }
            $productionDate = Carbon::parse($data['production_date'])->startOfDay();
            /** @var list<array{stock_batch_id?: int, product_batch_id?: int, quantity: float|int|string}> $usageData */
            $usageData = $data['usages'];
            $usages = collect($usageData);

            if ($usages->contains(fn (array $usage) => empty($usage['stock_batch_id']) === empty($usage['product_batch_id']))) {
                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'Each production usage must have exactly one source batch'
                );
            }

            $stockBatchIds = $usages->pluck('stock_batch_id')->filter()->map(fn ($id) => (int) $id);
            $productBatchIds = $usages->pluck('product_batch_id')->filter()->map(fn ($id) => (int) $id);
            if ($stockBatchIds->duplicates()->isNotEmpty() || $productBatchIds->duplicates()->isNotEmpty()) {
                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'A source batch can only be used once per production'
                );
            }

            $batches = StockBatch::query()
                ->whereKey($stockBatchIds->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $productBatches = ProductBatch::query()
                ->whereKey($productBatchIds->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $earliestIngredientExpiration = null;
            $totalCost = 0.0;
            $preparedUsages = [];
            foreach ($usages as $usage) {
                $quantity = (float) $usage['quantity'];
                $isMaterialBatch = ! empty($usage['stock_batch_id']);
                $batch = $isMaterialBatch
                    ? $batches->get((int) $usage['stock_batch_id'])
                    : $productBatches->get((int) ($usage['product_batch_id'] ?? 0));
                if (! $batch) {
                    throw new HttpException(Response::HTTP_NOT_FOUND, 'The selected source batch does not exist');
                }
                if (! $isMaterialBatch && $batch->product_id === $product->id) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'A production cannot consume its own product batch');
                }
                if ($quantity > (float) $batch->available_quantity) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'A used quantity exceeds its available stock');
                }
                if ($batch->expiration_date && $batch->expiration_date->isBefore($productionDate)) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'An expired source batch cannot be used in production');
                }
                if ($batch->expiration_date && (! $earliestIngredientExpiration || $batch->expiration_date->isBefore($earliestIngredientExpiration))) {
                    $earliestIngredientExpiration = $batch->expiration_date;
                }
                $totalCost += $quantity * (float) $batch->unit_cost;
                $preparedUsages[] = [$batch, $quantity, $isMaterialBatch];
            }
            $suggestedExpiration = $product->shelf_life_days !== null ? $productionDate->copy()->addDays($product->shelf_life_days) : null;
            if ($earliestIngredientExpiration && (! $suggestedExpiration || $earliestIngredientExpiration->isBefore($suggestedExpiration))) {
                $suggestedExpiration = $earliestIngredientExpiration;
            }
            $expiration = isset($data['expiration_date']) ? Carbon::parse($data['expiration_date']) : $suggestedExpiration;
            if ($earliestIngredientExpiration && $expiration && $expiration->isAfter($earliestIngredientExpiration) && empty($data['expiration_override_reason'])) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'An override reason is required when expiration exceeds the earliest ingredient expiration');
            }
            $production = ProductProduction::create(['product_id' => $product->id, 'user_id' => $user->id, 'planned_quantity' => $data['planned_quantity'] ?? null, 'produced_quantity' => $data['produced_quantity'], 'production_date' => $productionDate, 'suggested_expiration_date' => $suggestedExpiration, 'expiration_date' => $expiration, 'expiration_override_reason' => $data['expiration_override_reason'] ?? null, 'notes' => $data['notes'] ?? null]);
            foreach ($preparedUsages as [$batch, $quantity, $isMaterialBatch]) {
                $batch->update(['available_quantity' => round((float) $batch->available_quantity - $quantity, 2), 'status' => (float) $batch->available_quantity - $quantity > 0 ? 'available' : 'depleted']);
                if ($isMaterialBatch) {
                    $production->usages()->create(['material_id' => $batch->material_id, 'stock_batch_id' => $batch->id, 'quantity' => $quantity, 'unit_cost' => $batch->unit_cost]);
                    StockMovement::create(['material_id' => $batch->material_id, 'stock_batch_id' => $batch->id, 'user_id' => $user->id, 'type' => 'production_out', 'quantity' => $quantity, 'reason' => "Production #{$production->id}", 'movement_date' => $productionDate]);
                } else {
                    $production->usages()->create(['source_product_id' => $batch->product_id, 'product_batch_id' => $batch->id, 'quantity' => $quantity, 'unit_cost' => $batch->unit_cost]);
                    ProductStockMovement::create(['product_id' => $batch->product_id, 'product_batch_id' => $batch->id, 'user_id' => $user->id, 'type' => 'production_out', 'quantity' => $quantity, 'reason' => "Production #{$production->id}", 'movement_date' => $productionDate]);
                }
            }
            ProductBatch::create(['product_id' => $product->id, 'product_production_id' => $production->id, 'initial_quantity' => $data['produced_quantity'], 'available_quantity' => $data['produced_quantity'], 'unit_cost' => round($totalCost / (float) $data['produced_quantity'], 4), 'received_date' => $productionDate, 'expiration_date' => $expiration, 'status' => 'available']);

            return $production->load(['product', 'usages.material', 'usages.stockBatch', 'usages.sourceProduct', 'usages.productBatch']);
        });
    }
}
