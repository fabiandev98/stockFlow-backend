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
                'total_amount' => 0,
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

                if ($product->is_composed) {
                    $this->consumeProductMaterials($saleItem, $product, $quantity, $user);
                } else {
                    $this->consumeSimpleProductBatches($saleItem, $product, $quantity, $user);
                }

                $totalAmount += $totalPrice;
            }

            $sale->update(['total_amount' => round($totalAmount, 2)]);

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

        if ($product->is_composed && $product->compositions->isEmpty()) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "Product {$product->name} has no composition"
            );
        }
    }

    private function consumeSimpleProductBatches(
        SaleItem $saleItem,
        Product $product,
        int $soldQuantity,
        User $user,
    ): void {
        $availableQuantity = $this->availableProductQuantity($product->id);

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
            ->orderByRaw('expiration_date is null')
            ->orderBy('expiration_date')
            ->orderBy('received_date')
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
                'movement_date' => now(),
            ]);

            $remainingQuantity = round($remainingQuantity - $quantityToConsume, 2);
        }
    }

    private function availableProductQuantity(int $productId): float
    {
        return (float) ProductBatch::query()
            ->where('product_id', $productId)
            ->where('status', 'available')
            ->sum('available_quantity');
    }

    private function consumeProductMaterials(
        SaleItem $saleItem,
        Product $product,
        int $soldQuantity,
        User $user,
    ): void {
        foreach ($product->compositions as $composition) {
            $requiredQuantity = (float) $composition->quantity_required * $soldQuantity;
            $availableQuantity = $this->availableMaterialQuantity((int) $composition->material_id);

            if ($requiredQuantity > $availableQuantity) {
                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    "Insufficient stock for {$composition->material->name}"
                );
            }

            $this->consumeMaterialBatches(
                $saleItem,
                (int) $composition->material_id,
                $requiredQuantity,
                $user,
            );
        }
    }

    private function availableMaterialQuantity(int $materialId): float
    {
        return (float) StockBatch::query()
            ->where('material_id', $materialId)
            ->where('status', 'available')
            ->sum('available_quantity');
    }

    private function consumeMaterialBatches(
        SaleItem $saleItem,
        int $materialId,
        float $requiredQuantity,
        User $user,
    ): void {
        $remainingQuantity = $requiredQuantity;

        $batches = StockBatch::query()
            ->where('material_id', $materialId)
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->orderByRaw('expiration_date is null')
            ->orderBy('expiration_date')
            ->orderBy('received_date')
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
                'movement_date' => now(),
            ]);

            $remainingQuantity = round($remainingQuantity - $quantityToConsume, 2);
        }
    }

    private function freshSale(Sale $sale): Sale
    {
        return $sale->fresh([
            'user',
            'items.product.category',
            'items.stockMovements.material',
            'items.stockMovements.stockBatch',
            'items.productStockMovements.product',
            'items.productStockMovements.productBatch',
        ])?->loadCount('items') ?? $sale;
    }
}
