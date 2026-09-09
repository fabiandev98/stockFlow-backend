<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RestaurantDemoSeeder extends Seeder
{
    /**
     * A complete, non-destructive restaurant dataset for demonstrations.
     * It can be rerun safely: each historical month has its own marker.
     */
    public function run(): void
    {
        $user = User::query()->first();

        if (! $user) {
            $this->command?->warn('Restaurant demo data requires an existing user.');

            return;
        }

        DB::transaction(function () use ($user): void {
            $suppliers = $this->seedSuppliers();
            $materials = $this->seedMaterials();
            $products = $this->seedProducts($materials);
            $year = Carbon::today()->year;

            for ($month = 2; $month <= min(8, Carbon::today()->month); $month++) {
                $this->seedMonth($user, $suppliers['food'], $materials, $products, Carbon::create($year, $month, 1));
            }
        });
    }

    /** @return array<string, Supplier> */
    private function seedSuppliers(): array
    {
        return [
            'food' => Supplier::query()->updateOrCreate(['email' => 'compras@sabores-demo.test'], ['name' => 'Alimentos La Plaza', 'contact_name' => 'Marta Ruiz', 'phone' => '+57 300 555 0101']),
            'beverages' => Supplier::query()->updateOrCreate(['email' => 'ventas@bebidas-demo.test'], ['name' => 'Bebidas del Centro', 'contact_name' => 'Carlos Peña', 'phone' => '+57 300 555 0102']),
            'packaging' => Supplier::query()->updateOrCreate(['email' => 'pedidos@empaques-demo.test'], ['name' => 'Empaques Express', 'contact_name' => 'Ana Torres', 'phone' => '+57 300 555 0103']),
        ];
    }

    /** @return array<string, Material> */
    private function seedMaterials(): array
    {
        $categories = [
            'proteins' => MaterialCategory::query()->firstOrCreate(['name' => 'Proteínas']),
            'produce' => MaterialCategory::query()->firstOrCreate(['name' => 'Frutas y verduras']),
            'dry' => MaterialCategory::query()->firstOrCreate(['name' => 'Abarrotes']),
            'dairy' => MaterialCategory::query()->firstOrCreate(['name' => 'Lácteos']),
        ];
        $items = [
            'beef' => ['Carne molida', 'kg', 'proteins', 12, true, 5],
            'chicken' => ['Pechuga de pollo', 'kg', 'proteins', 10, true, 5],
            'bun' => ['Pan brioche', 'u', 'dry', 80, true, 7],
            'lettuce' => ['Lechuga', 'kg', 'produce', 5, true, 4],
            'tomato' => ['Tomate', 'kg', 'produce', 8, true, 6],
            'potato' => ['Papa', 'kg', 'produce', 20, true, 14],
            'rice' => ['Arroz', 'kg', 'dry', 12, false, null],
            'oil' => ['Aceite vegetal', 'l', 'dry', 8, false, null],
            'cheese' => ['Queso cheddar', 'kg', 'dairy', 4, true, 12],
            'salt' => ['Sal', 'kg', 'dry', 2, false, null],
        ];
        $materials = [];
        foreach ($items as $key => [$name, $unit, $category, $minimum, $perishable, $expiration]) {
            $materials[$key] = Material::query()->updateOrCreate(['name' => $name], [
                'material_category_id' => $categories[$category]->id,
                'unit' => $unit,
                'minimum_stock' => $minimum,
                'is_perishable' => $perishable,
                'default_expiration_days' => $expiration,
            ]);
        }

        return $materials;
    }

    /** @param array<string, Material> $materials
     * @return array<string, Product> */
    private function seedProducts(array $materials): array
    {
        $categories = [
            'starters' => ProductCategory::query()->firstOrCreate(['name' => 'Entradas']),
            'mains' => ProductCategory::query()->firstOrCreate(['name' => 'Platos principales']),
            'beverages' => ProductCategory::query()->firstOrCreate(['name' => 'Bebidas']),
            'desserts' => ProductCategory::query()->firstOrCreate(['name' => 'Postres']),
        ];
        $products = [
            'burger' => $this->product('Hamburguesa clásica', $categories['mains']->id, 24900, true),
            'bowl' => $this->product('Bowl de pollo', $categories['mains']->id, 22900, true),
            'fries' => $this->product('Papas rústicas', $categories['starters']->id, 10900, true),
            'water' => $this->product('Agua mineral 600 ml', $categories['beverages']->id, 4000, false),
            'soda' => $this->product('Gaseosa 400 ml', $categories['beverages']->id, 5500, false),
            'dessert' => $this->product('Cheesecake porción', $categories['desserts']->id, 12500, false),
        ];
        $this->recipe($products['burger'], [[$materials['beef'], .18], [$materials['bun'], 1], [$materials['lettuce'], .04], [$materials['tomato'], .05], [$materials['cheese'], .03]]);
        $this->recipe($products['bowl'], [[$materials['chicken'], .22], [$materials['rice'], .10], [$materials['lettuce'], .05], [$materials['tomato'], .06]]);
        $this->recipe($products['fries'], [[$materials['potato'], .25], [$materials['oil'], .03], [$materials['salt'], .005]]);

        return $products;
    }

    private function product(string $name, int $categoryId, float $price, bool $composed): Product
    {
        return Product::query()->updateOrCreate(['name' => $name], [
            'product_category_id' => $categoryId,
            'sale_price' => $price,
            'is_composed' => $composed,
            // The database requires a production mode for every product.
            // Simple products also sell directly from their purchased batch.
            'production_mode' => 'on_sale',
            'is_active' => true,
        ]);
    }

    /** @param array<int, array{0: Material, 1: float}> $ingredients */
    private function recipe(Product $product, array $ingredients): void
    {
        $product->compositions()->delete();
        foreach ($ingredients as [$material, $quantity]) {
            $product->compositions()->create(['material_id' => $material->id, 'quantity_required' => $quantity, 'unit' => $material->unit]);
        }
    }

    /** @param array<string, Material> $materials
     * @param array<string, Product> $products */
    private function seedMonth(User $user, Supplier $supplier, array $materials, array $products, Carbon $month): void
    {
        $marker = 'Restaurant demo '.$month->format('Y-m');
        if (Purchase::query()->where('notes', $marker)->exists()) {
            return;
        }

        $purchase = Purchase::query()->create(['supplier_id' => $supplier->id, 'user_id' => $user->id, 'purchase_date' => $month->copy()->day(2)->toDateString(), 'total_cost' => 0, 'notes' => $marker]);
        $stock = [];
        $rawItems = [
            'beef' => [45, 29000], 'chicken' => [40, 19000], 'bun' => [350, 1200], 'lettuce' => [22, 6500], 'tomato' => [28, 4800],
            'potato' => [85, 2800], 'rice' => [30, 4200], 'oil' => [15, 9000], 'cheese' => [14, 28000], 'salt' => [4, 2500],
        ];
        foreach ($rawItems as $key => [$quantity, $unitCost]) {
            $rejectedQuantity = in_array($key, ['lettuce', 'tomato'], true) ? 1.5 : 0;
            $acceptedQuantity = $quantity - $rejectedQuantity;
            $item = $purchase->items()->create(['material_id' => $materials[$key]->id, 'product_id' => null, 'quantity' => $quantity, 'accepted_quantity' => $acceptedQuantity, 'rejected_quantity' => $rejectedQuantity, 'quality_issue_reason' => $rejectedQuantity > 0 ? 'Quality below agreed freshness standard' : null, 'unit_cost' => $unitCost, 'total_cost' => $quantity * $unitCost, 'expiration_date' => $month->copy()->endOfMonth()->toDateString()]);
            $stock[$key] = StockBatch::query()->create(['material_id' => $materials[$key]->id, 'purchase_item_id' => $item->id, 'initial_quantity' => $acceptedQuantity, 'available_quantity' => $acceptedQuantity, 'unit_cost' => $unitCost, 'received_date' => $month->copy()->day(2)->toDateString(), 'expiration_date' => $month->copy()->endOfMonth()->toDateString(), 'status' => 'available']);
        }
        $productBatches = [];
        foreach (['water' => [240, 1800], 'soda' => [220, 2600], 'dessert' => [90, 5200]] as $key => [$quantity, $unitCost]) {
            $rejectedQuantity = $key === 'soda' ? 2 : 0;
            $acceptedQuantity = $quantity - $rejectedQuantity;
            $item = $purchase->items()->create(['material_id' => null, 'product_id' => $products[$key]->id, 'quantity' => $quantity, 'accepted_quantity' => $acceptedQuantity, 'rejected_quantity' => $rejectedQuantity, 'quality_issue_reason' => $rejectedQuantity > 0 ? 'Damaged packaging on delivery' : null, 'unit_cost' => $unitCost, 'total_cost' => $quantity * $unitCost, 'expiration_date' => $month->copy()->endOfMonth()->toDateString()]);
            $productBatches[$key] = ProductBatch::query()->create(['product_id' => $products[$key]->id, 'purchase_item_id' => $item->id, 'initial_quantity' => $acceptedQuantity, 'available_quantity' => $acceptedQuantity, 'unit_cost' => $unitCost, 'received_date' => $month->copy()->day(2)->toDateString(), 'expiration_date' => $month->copy()->endOfMonth()->toDateString(), 'status' => 'available']);
        }
        $purchase->update(['total_cost' => $purchase->items()->sum('total_cost')]);

        for ($day = 4; $day <= 25; $day += 3) {
            $this->seedSale($user, $products, $stock, $productBatches, $month->copy()->day($day), $marker, 2 + ($day % 3));
        }
        $this->seedWaste($user, $materials, $products, $stock, $productBatches, $month, $marker);
    }

    /** @param array<string, Product> $products
     * @param array<string, StockBatch> $stock
     * @param array<string, ProductBatch> $productBatches */
    private function seedSale(User $user, array $products, array &$stock, array &$productBatches, Carbon $date, string $marker, int $multiplier): void
    {
        $lines = [['burger', $multiplier], ['bowl', max(1, $multiplier - 1)], ['fries', $multiplier], ['water', $multiplier + 2], ['soda', $multiplier], ['dessert', 1]];
        $subtotal = collect($lines)->sum(fn (array $line) => $products[$line[0]]->sale_price * $line[1]);
        $discount = $multiplier === 4 ? 3000 : 0;
        $sale = Sale::query()->create(['user_id' => $user->id, 'sale_date' => $date->toDateString(), 'covers' => $multiplier + 2, 'subtotal_amount' => $subtotal, 'discount_amount' => $discount, 'tax_rate' => 0, 'tax_amount' => 0, 'total_amount' => $subtotal - $discount, 'notes' => "{$marker}: service {$date->format('d')}"]);
        foreach ($lines as [$key, $quantity]) {
            $product = $products[$key];
            $item = $sale->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $product->sale_price, 'total_price' => $product->sale_price * $quantity]);
            if ($product->is_composed) {
                foreach ($product->compositions as $composition) {
                    $batch = collect($stock)->first(
                        fn (StockBatch $stockBatch): bool => $stockBatch->material_id === $composition->material_id,
                    );

                    if (! $batch) {
                        continue;
                    }
                    $used = (float) $composition->quantity_required * $quantity;
                    $batch->decrement('available_quantity', $used);
                    StockMovement::query()->create(['material_id' => $composition->material_id, 'stock_batch_id' => $batch->id, 'sale_item_id' => $item->id, 'user_id' => $user->id, 'type' => 'sale', 'quantity' => $used, 'reason' => "Demo sale #{$sale->id}", 'movement_date' => $date]);
                }
            } else {
                $batch = $productBatches[$key];
                $batch->decrement('available_quantity', $quantity);
                ProductStockMovement::query()->create(['product_id' => $product->id, 'product_batch_id' => $batch->id, 'sale_item_id' => $item->id, 'user_id' => $user->id, 'type' => 'sale', 'quantity' => $quantity, 'reason' => "Demo sale #{$sale->id}", 'movement_date' => $date]);
            }
        }
    }

    /** @param array<string, Material> $materials
     * @param array<string, Product> $products
     * @param array<string, StockBatch> $stock
     * @param array<string, ProductBatch> $productBatches */
    private function seedWaste(User $user, array $materials, array $products, array &$stock, array &$productBatches, Carbon $month, string $marker): void
    {
        $materialKey = $month->month % 2 === 0 ? 'lettuce' : 'tomato';
        $quantity = 1.5;
        $batch = $stock[$materialKey];
        $batch->decrement('available_quantity', $quantity);
        StockMovement::query()->create(['material_id' => $materials[$materialKey]->id, 'stock_batch_id' => $batch->id, 'user_id' => $user->id, 'type' => $month->month % 2 === 0 ? 'expired' : 'waste', 'quantity' => $quantity, 'reason' => "{$marker}: kitchen waste", 'movement_date' => $month->copy()->endOfMonth()->subDay()]);
        $drinkBatch = $productBatches['soda'];
        $drinkBatch->decrement('available_quantity', 3);
        ProductStockMovement::query()->create(['product_id' => $products['soda']->id, 'product_batch_id' => $drinkBatch->id, 'user_id' => $user->id, 'type' => 'waste', 'quantity' => 3, 'reason' => "{$marker}: damaged beverages", 'movement_date' => $month->copy()->endOfMonth()->subDays(2)]);
    }
}
