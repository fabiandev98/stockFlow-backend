<?php

namespace App\Services;

use App\Data\Purchase\PurchaseData;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\StockBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchaseService
{
    /**
     * @return QueryBuilder<Purchase>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            Purchase::query()
                ->with(['supplier', 'user'])
                ->withCount('items')
                ->latest('purchase_date')
        )
            ->allowedFilters([
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('notes', 'LIKE', "%$value%")
                        ->orWhereHas('supplier', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        })
                        ->orWhereHas('items.material', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        })
                        ->orWhereHas('items.product', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        });
                }),
            ]);
    }

    public function create(PurchaseData $data, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $purchase = Purchase::create([
                'supplier_id' => $data->supplier_id,
                'user_id' => $user->id,
                'purchase_date' => $data->purchase_date,
                'total_cost' => 0,
                'notes' => $data->notes,
            ]);

            $this->createItemsAndBatches($purchase, $data);

            return $this->freshPurchase($purchase);
        });
    }

    public function update(Purchase $purchase, PurchaseData $data): Purchase
    {
        $this->ensurePurchaseCanBeRebuilt($purchase);

        return DB::transaction(function () use ($purchase, $data) {
            $purchase->update([
                'supplier_id' => $data->supplier_id,
                'purchase_date' => $data->purchase_date,
                'notes' => $data->notes,
            ]);

            $this->deleteItemsAndBatches($purchase);
            $this->createItemsAndBatches($purchase, $data);

            return $this->freshPurchase($purchase);
        });
    }

    public function delete(Purchase $purchase): void
    {
        $this->ensurePurchaseCanBeRebuilt($purchase);

        DB::transaction(function () use ($purchase) {
            $this->deleteItemsAndBatches($purchase);
            $purchase->delete();
        });
    }

    private function createItemsAndBatches(Purchase $purchase, PurchaseData $data): void
    {
        $totalCost = 0;

        foreach ($data->items as $item) {
            $quantity = (float) $item['quantity'];
            $unitCost = (float) $item['unit_cost'];
            $itemTotal = round($quantity * $unitCost, 2);

            if (! empty($item['product_id'])) {
                $this->createProductItemAndBatch($purchase, $data, $item, $quantity, $unitCost, $itemTotal);
                $totalCost += $itemTotal;

                continue;
            }

            $this->createMaterialItemAndBatch($purchase, $data, $item, $quantity, $unitCost, $itemTotal);
            $totalCost += $itemTotal;
        }

        $purchase->update(['total_cost' => round($totalCost, 2)]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createMaterialItemAndBatch(
        Purchase $purchase,
        PurchaseData $data,
        array $item,
        float $quantity,
        float $unitCost,
        float $itemTotal,
    ): void {
        if (empty($item['material_id'])) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Purchase item must reference a material or a simple product'
            );
        }

        $material = $this->findMaterial((int) $item['material_id']);
        $expirationDate = $this->resolveExpirationDate($material, $data->purchase_date, $item);

        $purchaseItem = $purchase->items()->create([
            'material_id' => $material->id,
            'product_id' => null,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $itemTotal,
            'expiration_date' => $expirationDate,
        ]);

        StockBatch::create([
            'material_id' => $material->id,
            'purchase_item_id' => $purchaseItem->id,
            'initial_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => $data->purchase_date,
            'expiration_date' => $expirationDate,
            'status' => 'available',
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createProductItemAndBatch(
        Purchase $purchase,
        PurchaseData $data,
        array $item,
        float $quantity,
        float $unitCost,
        float $itemTotal,
    ): void {
        if (! empty($item['material_id'])) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Purchase item can reference either a material or a product, not both'
            );
        }

        $product = $this->findSimpleProduct((int) $item['product_id']);
        $expirationDate = $this->nullableString($item['expiration_date'] ?? null);

        $purchaseItem = $purchase->items()->create([
            'material_id' => null,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $itemTotal,
            'expiration_date' => $expirationDate,
        ]);

        ProductBatch::create([
            'product_id' => $product->id,
            'purchase_item_id' => $purchaseItem->id,
            'initial_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => $data->purchase_date,
            'expiration_date' => $expirationDate,
            'status' => 'available',
        ]);
    }

    private function findMaterial(int $materialId): Material
    {
        return Material::query()->findOrFail($materialId);
    }

    private function findSimpleProduct(int $productId): Product
    {
        $product = Product::query()->findOrFail($productId);

        if ($product->is_composed) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "Product '{$product->name}' is composed and must be produced from materials"
            );
        }

        return $product;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveExpirationDate(Material $material, string $purchaseDate, array $item): ?string
    {
        if (! $material->is_perishable) {
            return $this->nullableString($item['expiration_date'] ?? null);
        }

        if (! empty($item['expiration_date'])) {
            return $this->nullableString($item['expiration_date']);
        }

        if ($material->default_expiration_days) {
            return Carbon::parse($purchaseDate)
                ->addDays($material->default_expiration_days)
                ->toDateString();
        }

        throw new HttpException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "Expiration date is required for perishable material '{$material->name}'"
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function ensurePurchaseCanBeRebuilt(Purchase $purchase): void
    {
        $itemIds = $purchase->items()->pluck('id');

        if ($itemIds->isEmpty()) {
            return;
        }

        if (
            StockBatch::whereIn('purchase_item_id', $itemIds)
                ->whereHas('stockMovements')
                ->exists()
        ) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                'Cannot modify a purchase with stock batches that already have movements'
            );
        }

        if (
            ProductBatch::whereIn('purchase_item_id', $itemIds)
                ->whereHas('productStockMovements')
                ->exists()
        ) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                'Cannot modify a purchase with product batches that already have movements'
            );
        }

        if (
            StockBatch::whereIn('purchase_item_id', $itemIds)
                ->whereColumn('available_quantity', '!=', 'initial_quantity')
                ->exists()
        ) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                'Cannot modify a purchase with stock batches that have already changed quantity'
            );
        }

        if (
            ProductBatch::whereIn('purchase_item_id', $itemIds)
                ->whereColumn('available_quantity', '!=', 'initial_quantity')
                ->exists()
        ) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                'Cannot modify a purchase with product batches that have already changed quantity'
            );
        }
    }

    private function deleteItemsAndBatches(Purchase $purchase): void
    {
        $itemIds = $purchase->items()->pluck('id');

        if ($itemIds->isNotEmpty()) {
            StockBatch::whereIn('purchase_item_id', $itemIds)->delete();
            ProductBatch::whereIn('purchase_item_id', $itemIds)->delete();
            $purchase->items()->delete();
        }
    }

    private function freshPurchase(Purchase $purchase): Purchase
    {
        return $purchase->fresh([
            'supplier',
            'user',
            'items.material.category',
            'items.product.category',
            'items.stockBatches',
            'items.productBatches',
        ]) ?? $purchase;
    }
}
