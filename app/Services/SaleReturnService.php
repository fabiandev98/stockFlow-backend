<?php

namespace App\Services;

use App\Models\ProductBatch;
use App\Models\ProductStockMovement;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnAllocation;
use App\Models\SaleReturnItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaleReturnService
{
    /** @param array<string, mixed> $data */
    public function create(Sale $sale, array $data, User $user): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data, $user) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== 'completed') {
                throw new HttpException(Response::HTTP_CONFLICT, 'Only completed sales can be returned');
            }

            $returnDate = Carbon::parse($data['return_date'])->startOfDay();
            if ($returnDate->isBefore($sale->sale_date->startOfDay())) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'The return date cannot be before the sale date');
            }

            $sale->load(['items.stockMovements' => fn ($q) => $q->where('type', 'sale'), 'items.productStockMovements' => fn ($q) => $q->where('type', 'sale')]);
            $return = SaleReturn::create(['sale_id' => $sale->id, 'user_id' => $user->id, 'return_date' => $returnDate, 'reason' => $data['reason'], 'subtotal_amount' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0]);
            $subtotal = 0.0;

            foreach ($data['items'] as $dataItem) {
                $item = $sale->items->firstWhere('id', (int) $dataItem['sale_item_id']);
                if (! $item) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'A returned item does not belong to this sale');
                }
                $quantity = (float) $dataItem['quantity'];
                $returnedQuantity = (float) $item->returnItems()->sum('quantity');
                if ($quantity > (float) $item->quantity - $returnedQuantity) {
                    throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Return quantity exceeds the quantity still eligible for return');
                }
                /** @var SaleReturnItem $returnItem */
                $returnItem = $return->items()->create(['sale_item_id' => $item->id, 'quantity' => $quantity, 'disposition' => $dataItem['disposition'], 'unit_price' => $item->unit_price, 'total_amount' => round($quantity * (float) $item->unit_price, 2)]);
                $this->allocateMaterialMovements($returnItem, $item->stockMovements, $quantity, $dataItem['disposition'], $user, $returnDate, $data['reason']);
                $this->allocateProductMovements($returnItem, $item->productStockMovements, $quantity, $dataItem['disposition'], $user, $returnDate, $data['reason']);
                $subtotal += (float) $returnItem->total_amount;
            }

            $discount = $sale->subtotal_amount > 0 ? round($subtotal * (float) $sale->discount_amount / (float) $sale->subtotal_amount, 2) : 0;
            $tax = round(($subtotal - $discount) * (float) $sale->tax_rate / 100, 2);
            $return->update(['subtotal_amount' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'total_amount' => round($subtotal - $discount + $tax, 2)]);

            return $return->load(['user', 'items.saleItem.product', 'items.allocations']);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, StockMovement>  $movements
     */
    private function allocateMaterialMovements(SaleReturnItem $returnItem, \Illuminate\Database\Eloquent\Collection $movements, float $quantity, string $disposition, User $user, Carbon $date, string $reason): void
    {
        if ($movements->isEmpty()) {
            return;
        }

        $remaining = $quantity;
        foreach ($movements as $movement) {
            if ($remaining <= 0) {
                break;
            }
            $alreadyAllocated = (float) SaleReturnAllocation::query()->where('stock_movement_id', $movement->id)->sum('quantity');
            $allocation = min($remaining, (float) $movement->quantity - $alreadyAllocated);
            if ($allocation <= 0) {
                continue;
            }
            $batch = StockBatch::query()->lockForUpdate()->find($movement->stock_batch_id);
            if (! $batch instanceof StockBatch) {
                throw new HttpException(Response::HTTP_NOT_FOUND, 'The material batch for this return no longer exists');
            }
            if ($disposition === 'restock') {
                $batch->update([
                    'available_quantity' => round((float) $batch->available_quantity + $allocation, 2),
                    'status' => $this->restoredBatchStatus($batch->expiration_date),
                ]);
            }
            StockMovement::create(['material_id' => $movement->material_id, 'stock_batch_id' => $batch->id, 'sale_item_id' => $movement->sale_item_id, 'user_id' => $user->id, 'type' => $disposition === 'restock' ? 'sale_return' : 'waste', 'quantity' => $allocation, 'reason' => "Return #{$returnItem->sale_return_id}: {$reason}", 'movement_date' => $date]);
            $returnItem->allocations()->create(['stock_movement_id' => $movement->id, 'quantity' => $allocation]);
            $remaining = round($remaining - $allocation, 2);
        }
        if ($remaining > 0) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Unable to trace all material lots for this return');
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ProductStockMovement>  $movements
     */
    private function allocateProductMovements(SaleReturnItem $returnItem, \Illuminate\Database\Eloquent\Collection $movements, float $quantity, string $disposition, User $user, Carbon $date, string $reason): void
    {
        if ($movements->isEmpty()) {
            return;
        }

        $remaining = $quantity;
        foreach ($movements as $movement) {
            if ($remaining <= 0) {
                break;
            }
            $alreadyAllocated = (float) SaleReturnAllocation::query()->where('product_stock_movement_id', $movement->id)->sum('quantity');
            $allocation = min($remaining, (float) $movement->quantity - $alreadyAllocated);
            if ($allocation <= 0) {
                continue;
            }
            $batch = ProductBatch::query()->lockForUpdate()->find($movement->product_batch_id);
            if (! $batch instanceof ProductBatch) {
                throw new HttpException(Response::HTTP_NOT_FOUND, 'The product batch for this return no longer exists');
            }
            if ($disposition === 'restock') {
                $batch->update([
                    'available_quantity' => round((float) $batch->available_quantity + $allocation, 2),
                    'status' => $this->restoredBatchStatus($batch->expiration_date),
                ]);
            }
            ProductStockMovement::create(['product_id' => $movement->product_id, 'product_batch_id' => $batch->id, 'sale_item_id' => $movement->sale_item_id, 'user_id' => $user->id, 'type' => $disposition === 'restock' ? 'sale_return' : 'waste', 'quantity' => $allocation, 'reason' => "Return #{$returnItem->sale_return_id}: {$reason}", 'movement_date' => $date]);
            $returnItem->allocations()->create(['product_stock_movement_id' => $movement->id, 'quantity' => $allocation]);
            $remaining = round($remaining - $allocation, 2);
        }
        if ($remaining > 0) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Unable to trace all product lots for this return');
        }
    }

    private function restoredBatchStatus(?Carbon $expirationDate): string
    {
        return $expirationDate?->isBefore(today()) ? 'expired' : 'available';
    }
}
