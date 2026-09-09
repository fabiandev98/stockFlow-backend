<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStockMovement;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HistoricalKpiDemoSeeder extends Seeder
{
    /**
     * Adds deterministic demo data from February through August of the current year.
     * It intentionally never deletes operational data and can be run repeatedly.
     */
    public function run(): void
    {
        $user = User::query()->first();
        $supplier = Supplier::query()->where('email', 'orders@demo-supplier.test')->first();
        $product = Product::query()->where('name', 'Bottled water 600ml')->first();

        if (! $user || ! $supplier || ! $product) {
            $this->command?->warn('Historical KPI demo data requires StockFlowDemoSeeder data and a user.');

            return;
        }

        $year = Carbon::today()->year;
        $lastMonth = min(8, Carbon::today()->month);

        DB::transaction(function () use ($user, $supplier, $product, $year, $lastMonth): void {
            for ($month = 2; $month <= $lastMonth; $month++) {
                $this->seedMonth($user, $supplier, $product, Carbon::create($year, $month, 1));
            }
        });
    }

    private function seedMonth(User $user, Supplier $supplier, Product $product, Carbon $month): void
    {
        $marker = sprintf('KPI demo %s', $month->format('Y-m'));

        if (Purchase::query()->where('notes', $marker)->exists()) {
            return;
        }

        $receivedAt = $month->copy()->day(2);
        $purchasedQuantity = 360;
        $unitCost = 1650 + ($month->month * 25);
        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'purchase_date' => $receivedAt->toDateString(),
            'total_cost' => 0,
            'notes' => $marker,
        ]);
        $purchaseItem = $purchase->items()->create([
            'product_id' => $product->id,
            'material_id' => null,
            'quantity' => $purchasedQuantity,
            'accepted_quantity' => $purchasedQuantity,
            'rejected_quantity' => 0,
            'unit_cost' => $unitCost,
            'total_cost' => $purchasedQuantity * $unitCost,
            'expiration_date' => $month->copy()->endOfMonth()->toDateString(),
        ]);
        $batch = ProductBatch::query()->create([
            'product_id' => $product->id,
            'purchase_item_id' => $purchaseItem->id,
            'initial_quantity' => $purchasedQuantity,
            'available_quantity' => $purchasedQuantity,
            'unit_cost' => $unitCost,
            'received_date' => $receivedAt->toDateString(),
            'expiration_date' => $month->copy()->endOfMonth()->toDateString(),
            'status' => 'available',
        ]);
        $purchase->update(['total_cost' => $purchaseItem->total_cost]);

        $quantities = [18, 24, 20, 26, 22, 28, 19, 25];
        $soldQuantity = 0;

        foreach ($quantities as $index => $quantity) {
            $saleDate = $month->copy()->day(4 + ($index * 3));
            $this->seedSale($user, $product, $batch, $quantity, $saleDate, $marker);
            $soldQuantity += $quantity;
        }

        $wasteQuantity = 4 + ($month->month % 3);
        ProductStockMovement::query()->create([
            'product_id' => $product->id,
            'product_batch_id' => $batch->id,
            'user_id' => $user->id,
            'type' => $month->month % 2 === 0 ? 'expired' : 'waste',
            'quantity' => $wasteQuantity,
            'reason' => "{$marker}: demo waste",
            'movement_date' => $month->copy()->endOfMonth()->subDay(),
        ]);

        $remaining = $purchasedQuantity - $soldQuantity - $wasteQuantity;
        $batch->update([
            'available_quantity' => max(0, $remaining),
            'status' => $remaining > 0 ? 'expired' : 'depleted',
        ]);
    }

    private function seedSale(User $user, Product $product, ProductBatch $batch, int $quantity, Carbon $date, string $marker): void
    {
        $unitPrice = (float) $product->sale_price;
        $subtotal = $quantity * $unitPrice;
        $discount = $quantity >= 25 ? 1500 : 0;
        $sale = Sale::query()->create([
            'user_id' => $user->id,
            'sale_date' => $date->toDateString(),
            'covers' => max(1, (int) ceil($quantity / 3)),
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total_amount' => $subtotal - $discount,
            'notes' => "{$marker}: sale {$date->format('d')}",
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $subtotal,
        ]);
        ProductStockMovement::query()->create([
            'product_id' => $product->id,
            'product_batch_id' => $batch->id,
            'sale_item_id' => $item->id,
            'user_id' => $user->id,
            'type' => 'sale',
            'quantity' => $quantity,
            'reason' => "Demo sale #{$sale->id}",
            'movement_date' => $date,
        ]);
    }
}
