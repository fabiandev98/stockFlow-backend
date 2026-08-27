<?php

namespace App\Services;

use App\Data\Sale\SaleData;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStockMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaleService
{
    /**
     * @return QueryBuilder<Sale>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            Sale::query()
                ->with('user')
                ->withCount('items')
                ->latest('sale_date')
        )
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('notes', 'LIKE', "%$value%")
                        ->orWhereHas('user', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        });
                }),
            ]);
    }

    public function create(SaleData $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user) {
            $sale = Sale::create([
                'user_id' => $user->id,
                'sale_date' => $data->sale_date,
                'covers' => $data->covers,
                'subtotal_amount' => 0,
                'discount_amount' => 0,
                'tax_rate' => $data->tax_rate,
                'tax_amount' => 0,
                'total_amount' => 0,
                'status' => 'completed',
                'notes' => $data->notes,
            ]);

            $totalAmount = 0;

            foreach ($data->items as $item) {
                $product = Product::query()
                    ->with('compositions.material')
                    ->whereKey((int) $item['product_id'])
                    ->firstOrFail();

                $this->ensureProductCanBeSold($product);

                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->sale_price;
                $totalPrice = $quantity * $unitPrice;

                $saleItem = $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);

                if ($product->is_composed && $product->production_mode === 'on_sale') {
                    $this->consumeProductMaterials($saleItem, $product, $quantity, $user, $data->sale_date);
                } else {
                    $this->consumeSimpleProductBatches($saleItem, $product, $quantity, $user, $data->sale_date);
                }

                $totalAmount += $totalPrice;
            }

            $subtotalAmount = round($totalAmount, 2);
            $discountAmount = round($data->discount_amount, 2);

            if ($discountAmount > $subtotalAmount) {
                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'Discount amount can not be greater than the sale subtotal'
                );
            }

            $taxableAmount = round($subtotalAmount - $discountAmount, 2);
            $taxAmount = round($taxableAmount * $data->tax_rate / 100, 2);

            $sale->update([
                'subtotal_amount' => $subtotalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => round($taxableAmount + $taxAmount, 2),
            ]);

            return $this->freshSale($sale);
        });
    }

    private function ensureProductCanBeSold(Product $product): void
    {
        if (! $product->is_active) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "Product {$product->name} is not active"
            );
        }

        if ($product->is_composed && $product->production_mode === 'on_sale' && $product->compositions->isEmpty()) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "Product {$product->name} has no composition"
            );
        }
    }

    public function cancel(Sale $sale, string $reason, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $user) {
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($lockedSale->status !== 'completed') {
                throw new HttpException(
                    Response::HTTP_CONFLICT,
                    'Only completed sales can be cancelled'
                );
            }

            $lockedSale->load([
                'items.stockMovements' => fn ($query) => $query->where('type', 'sale'),
                'items.productStockMovements' => fn ($query) => $query->where('type', 'sale'),
            ]);

            foreach ($lockedSale->items as $saleItem) {
                $this->restoreMaterialStock($saleItem, $user, $reason);
                $this->restoreProductStock($saleItem, $user, $reason);
            }

            $lockedSale->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $user->id,
                'cancellation_reason' => $reason,
            ]);

            return $this->freshSale($lockedSale);
        });
    }

    private function restoreMaterialStock(SaleItem $saleItem, User $user, string $reason): void
    {
        foreach ($saleItem->stockMovements as $movement) {
            $batch = StockBatch::query()->lockForUpdate()->find($movement->stock_batch_id);

            if (! $batch) {
                throw new HttpException(Response::HTTP_CONFLICT, 'The original material batch is unavailable');
            }

            $availableQuantity = round((float) $batch->available_quantity + (float) $movement->quantity, 2);
            $batch->update([
                'available_quantity' => $availableQuantity,
                'status' => $this->restoredBatchStatus($batch->expiration_date),
            ]);

            StockMovement::create([
                'material_id' => $movement->material_id,
                'stock_batch_id' => $batch->id,
                'sale_item_id' => $saleItem->id,
                'user_id' => $user->id,
                'type' => 'sale_reversal',
                'quantity' => $movement->quantity,
                'reason' => "Cancellation of sale #{$saleItem->sale_id}: {$reason}",
                'movement_date' => now(),
            ]);
        }
    }

    private function restoreProductStock(SaleItem $saleItem, User $user, string $reason): void
    {
        foreach ($saleItem->productStockMovements as $movement) {
            $batch = ProductBatch::query()->lockForUpdate()->find($movement->product_batch_id);

            if (! $batch) {
                throw new HttpException(Response::HTTP_CONFLICT, 'The original product batch is unavailable');
            }

            $availableQuantity = round((float) $batch->available_quantity + (float) $movement->quantity, 2);
            $batch->update([
                'available_quantity' => $availableQuantity,
                'status' => $this->restoredBatchStatus($batch->expiration_date),
            ]);

            ProductStockMovement::create([
                'product_id' => $movement->product_id,
                'product_batch_id' => $batch->id,
                'sale_item_id' => $saleItem->id,
                'user_id' => $user->id,
                'type' => 'sale_reversal',
                'quantity' => $movement->quantity,
                'reason' => "Cancellation of sale #{$saleItem->sale_id}: {$reason}",
                'movement_date' => now(),
            ]);
        }
    }

    private function restoredBatchStatus(?Carbon $expirationDate): string
    {
        return $expirationDate && $expirationDate->isBefore(today())
            ? 'expired'
            : 'available';
    }

    private function consumeSimpleProductBatches(
        SaleItem $saleItem,
        Product $product,
        int $soldQuantity,
        User $user,
        string $saleDate,
    ): void {
        $availableQuantity = $this->availableProductQuantity($product->id, $saleDate);

        if ($soldQuantity > $availableQuantity) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "Insufficient stock for {$product->name}"
            );
        }

        $remainingQuantity = (float) $soldQuantity;

        $batches = ProductBatch::query()
            ->where('product_id', $product->id)
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->where(function (Builder $query) use ($saleDate) {
                $query->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', $saleDate);
            })
            ->orderByRaw('expiration_date is null')
            ->orderBy('expiration_date')
            ->orderBy('received_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $batchAvailable = (float) $batch->available_quantity;
            $quantityToConsume = min($remainingQuantity, $batchAvailable);
            $newAvailableQuantity = round($batchAvailable - $quantityToConsume, 2);

            $batch->update([
                'available_quantity' => $newAvailableQuantity,
                'status' => $newAvailableQuantity > 0 ? 'available' : 'depleted',
            ]);

            ProductStockMovement::create([
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'sale_item_id' => $saleItem->id,
                'user_id' => $user->id,
                'type' => 'sale',
                'quantity' => round($quantityToConsume, 2),
                'reason' => "Sale #{$saleItem->sale_id}",
                'movement_date' => $saleDate,
            ]);

            $remainingQuantity = round($remainingQuantity - $quantityToConsume, 2);
        }
    }

    private function availableProductQuantity(int $productId, string $saleDate): float
    {
        return (float) ProductBatch::query()
            ->where('product_id', $productId)
            ->where('status', 'available')
            ->where(function (Builder $query) use ($saleDate) {
                $query->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', $saleDate);
            })
            ->sum('available_quantity');
    }

    private function consumeProductMaterials(
        SaleItem $saleItem,
        Product $product,
        int $soldQuantity,
        User $user,
        string $saleDate,
    ): void {
        foreach ($product->compositions as $composition) {
            $requiredQuantity = (float) $composition->quantity_required * $soldQuantity;
            $availableQuantity = $this->availableMaterialQuantity((int) $composition->material_id, $saleDate);

            if ($requiredQuantity > $availableQuantity) {
                $materialName = $composition->material
                    ? $composition->material->name
                    : "material ID {$composition->material_id}";

                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    "Insufficient stock for {$materialName}"
                );
            }

            $this->consumeMaterialBatches(
                $saleItem,
                (int) $composition->material_id,
                $requiredQuantity,
                $user,
                $saleDate,
            );
        }
    }

    private function availableMaterialQuantity(int $materialId, string $saleDate): float
    {
        return (float) StockBatch::query()
            ->where('material_id', $materialId)
            ->where('status', 'available')
            ->where(function (Builder $query) use ($saleDate) {
                $query->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', $saleDate);
            })
            ->sum('available_quantity');
    }

    private function consumeMaterialBatches(
        SaleItem $saleItem,
        int $materialId,
        float $requiredQuantity,
        User $user,
        string $saleDate,
    ): void {
        $remainingQuantity = $requiredQuantity;

        $batches = StockBatch::query()
            ->where('material_id', $materialId)
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->where(function (Builder $query) use ($saleDate) {
                $query->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', $saleDate);
            })
            ->orderByRaw('expiration_date is null')
            ->orderBy('expiration_date')
            ->orderBy('received_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $batchAvailable = (float) $batch->available_quantity;
            $quantityToConsume = min($remainingQuantity, $batchAvailable);
            $newAvailableQuantity = round($batchAvailable - $quantityToConsume, 2);

            $batch->update([
                'available_quantity' => $newAvailableQuantity,
                'status' => $newAvailableQuantity > 0 ? 'available' : 'depleted',
            ]);

            StockMovement::create([
                'material_id' => $materialId,
                'stock_batch_id' => $batch->id,
                'sale_item_id' => $saleItem->id,
                'user_id' => $user->id,
                'type' => 'sale',
                'quantity' => round($quantityToConsume, 2),
                'reason' => "Sale #{$saleItem->sale_id}",
                'movement_date' => $saleDate,
            ]);

            $remainingQuantity = round($remainingQuantity - $quantityToConsume, 2);
        }
    }

    private function freshSale(Sale $sale): Sale
    {
        return $sale->fresh([
            'user',
            'cancelledBy',
            'items.product.category',
            'items.stockMovements.material',
            'items.stockMovements.stockBatch',
            'items.productStockMovements.product',
            'items.productStockMovements.productBatch',
        ])?->loadCount('items') ?? $sale;
    }
}
