<?php

namespace Tests\Feature;

use App\Data\Product\ProductData;
use App\Data\ProductStockMovement\ProductStockMovementData;
use App\Data\Sale\SaleData;
use App\Data\StockMovement\StockMovementData;
use App\Http\Controllers\SaleController;
use App\Http\Requests\Sale\CancelSaleRequest;
use App\Http\Resources\ProductProductionResource;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStockMovement;
use App\Models\StockBatch;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ProductProductionService;
use App\Services\ProductService;
use App\Services\ProductStockMovementService;
use App\Services\RecipeService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_uses_fefo_and_calculates_discount_and_tax(): void
    {
        $user = User::factory()->create();
        $cream = Material::query()->create([
            'name' => 'Cream',
            'unit' => 'l',
            'minimum_stock' => 0,
            'is_perishable' => true,
            'default_expiration_days' => 30,
        ]);
        $product = $this->createComposedProduct($cream, 1.5, 20000);

        $laterExpirationBatch = $this->createMaterialBatch(
            $cream,
            receivedDate: '2026-08-01',
            expirationDate: '2026-09-25',
            quantity: 2,
        );
        $earlierExpirationBatch = $this->createMaterialBatch(
            $cream,
            receivedDate: '2026-08-20',
            expirationDate: '2026-09-01',
            quantity: 5,
        );

        $sale = app(SaleService::class)->create(
            new SaleData(
                sale_date: '2026-08-25',
                notes: null,
                items: [['product_id' => $product->id, 'quantity' => 1]],
                covers: 2,
                discount_amount: 2000,
                tax_rate: 10,
            ),
            $user,
        );

        $movement = $sale->items->firstOrFail()->stockMovements->firstOrFail();

        $this->assertSame($earlierExpirationBatch->id, $movement->stock_batch_id);
        $this->assertSame('1.50', $movement->quantity);
        $this->assertSame('2026-08-25', $movement->movement_date->toDateString());
        $this->assertSame('2.00', $laterExpirationBatch->fresh()->available_quantity);
        $this->assertSame('3.50', $earlierExpirationBatch->fresh()->available_quantity);
        $this->assertSame(2, $sale->covers);
        $this->assertSame('20000.00', $sale->subtotal_amount);
        $this->assertSame('2000.00', $sale->discount_amount);
        $this->assertSame('10.00', $sale->tax_rate);
        $this->assertSame('1800.00', $sale->tax_amount);
        $this->assertSame('19800.00', $sale->total_amount);
    }

    public function test_sale_uses_fifo_when_batches_have_no_expiration_date(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create([
            'name' => 'Salt',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'is_perishable' => false,
        ]);
        $product = $this->createComposedProduct($material, 1, 1000);

        $newerBatch = $this->createMaterialBatch($material, '2026-08-20', null, 5);
        $olderBatch = $this->createMaterialBatch($material, '2026-08-01', null, 5);

        $sale = app(SaleService::class)->create(
            new SaleData(
                sale_date: '2026-08-25',
                notes: null,
                items: [['product_id' => $product->id, 'quantity' => 1]],
            ),
            $user,
        );

        $movement = $sale->items->firstOrFail()->stockMovements->firstOrFail();

        $this->assertSame($olderBatch->id, $movement->stock_batch_id);
        $this->assertSame('5.00', $newerBatch->fresh()->available_quantity);
        $this->assertSame('4.00', $olderBatch->fresh()->available_quantity);
    }

    public function test_expired_batches_are_not_available_for_a_sale(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create([
            'name' => 'Milk',
            'unit' => 'l',
            'minimum_stock' => 0,
            'is_perishable' => true,
            'default_expiration_days' => 7,
        ]);
        $product = $this->createComposedProduct($material, 1, 5000);
        $expiredBatch = $this->createMaterialBatch($material, '2026-08-01', '2026-08-24', 5);

        try {
            app(SaleService::class)->create(
                new SaleData(
                    sale_date: '2026-08-25',
                    notes: null,
                    items: [['product_id' => $product->id, 'quantity' => 1]],
                ),
                $user,
            );

            $this->fail('An expired batch should not be consumed.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('5.00', $expiredBatch->fresh()->available_quantity);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_simple_product_sale_does_not_create_partial_movements_when_locked_stock_is_insufficient(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Sparkling water', 'sale_price' => 3000, 'is_composed' => false, 'is_active' => true,
        ]);
        $batch = $this->createProductBatch($product, 1, 1000, '2026-08-25');

        try {
            app(SaleService::class)->create(new SaleData(
                sale_date: '2026-08-25', notes: null, items: [['product_id' => $product->id, 'quantity' => 2]],
            ), $user);

            $this->fail('A sale with insufficient stock must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('1.00', $batch->fresh()->available_quantity);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('product_stock_movements', 0);
    }

    public function test_product_composition_always_uses_the_material_base_unit(): void
    {
        $material = Material::query()->create([
            'name' => 'Flour',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'is_perishable' => false,
        ]);

        $product = app(ProductService::class)->create(new ProductData(
            product_category_id: null,
            name: 'Bread',
            sale_price: 3000,
            is_composed: true,
            is_active: true,
            compositions: [[
                'material_id' => $material->id,
                'quantity_required' => 0.2,
                'unit' => 'g',
            ]],
        ));

        $this->assertSame('kg', $product->compositions->firstOrFail()->unit);
    }

    public function test_cancelling_a_sale_restores_its_lot_and_excludes_it_from_profitability(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create([
            'name' => 'Butter',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'is_perishable' => false,
        ]);
        $product = $this->createComposedProduct($material, 1, 5000);
        $batch = $this->createMaterialBatch($material, '2026-08-01', null, 3);

        $sale = app(SaleService::class)->create(
            new SaleData(
                sale_date: '2026-08-25',
                notes: null,
                items: [['product_id' => $product->id, 'quantity' => 2]],
            ),
            $user,
        );

        $this->assertSame('1.00', $batch->fresh()->available_quantity);

        $cancelledSale = app(SaleService::class)->cancel($sale, 'Pedido duplicado', $user);

        $this->assertSame('cancelled', $cancelledSale->status);
        $this->assertSame('Pedido duplicado', $cancelledSale->cancellation_reason);
        $this->assertSame('3.00', $batch->fresh()->available_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_batch_id' => $batch->id,
            'type' => 'sale_reversal',
            'quantity' => 2,
        ]);

        $summary = app(DashboardService::class)->summary('2026-08-25', '2026-08-25');

        $this->assertSame('0.00', $summary['sales']['total']);
        $this->assertSame('0.00', $summary['profitability']['cogs']);
        $this->assertSame('0.00', $summary['profitability']['gross_profit']);
    }

    public function test_waste_is_reflected_in_actual_food_cost(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create([
            'name' => 'Cheese',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'is_perishable' => true,
        ]);
        $product = $this->createComposedProduct($material, 1, 5000);
        $batch = $this->createMaterialBatch($material, '2026-08-01', '2026-09-01', 5);

        app(SaleService::class)->create(
            new SaleData(
                sale_date: '2026-08-25',
                notes: null,
                items: [['product_id' => $product->id, 'quantity' => 2]],
            ),
            $user,
        );
        app(StockMovementService::class)->create(new StockMovementData(
            stock_batch_id: $batch->id,
            type: 'waste',
            quantity: 1,
            reason: 'Producto dañado durante preparación',
            movement_date: '2026-08-25 12:00:00',
        ), $user);

        $summary = app(DashboardService::class)->summary('2026-08-25', '2026-08-25');

        $this->assertSame('2000.00', $summary['profitability']['cogs']);
        $this->assertSame('1000.00', $summary['profitability']['waste_cost']);
        $this->assertSame('3000.00', $summary['profitability']['actual_food_cost']);
        $this->assertSame('30.00', $summary['profitability']['actual_food_cost_percentage']);
        $this->assertSame('7000.00', $summary['profitability']['adjusted_gross_profit']);
    }

    public function test_batch_production_uses_real_material_quantities_and_allows_an_expiration_override(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create([
            'name' => 'Cream cheese', 'unit' => 'kg', 'minimum_stock' => 0, 'is_perishable' => true,
        ]);
        $product = app(ProductService::class)->create(new ProductData(
            product_category_id: null, name: 'Cheesecake', sale_price: 12000, is_composed: true, is_active: true,
            compositions: [['material_id' => $material->id, 'quantity_required' => 1, 'unit' => 'kg']],
            production_mode: 'batch', shelf_life_days: 4,
        ));
        $batch = $this->createMaterialBatch($material, '2026-08-20', '2026-08-27', 3);

        $production = app(ProductProductionService::class)->create($product, [
            'planned_quantity' => 10, 'produced_quantity' => 8, 'production_date' => '2026-08-25',
            'expiration_date' => '2026-08-28', 'expiration_override_reason' => 'Producto refrigerado y validado',
            'usages' => [['stock_batch_id' => $batch->id, 'quantity' => 1.5]],
        ], $user);

        $this->assertSame('2026-08-27', $production->suggested_expiration_date->toDateString());
        $this->assertSame('2026-08-28', $production->expiration_date->toDateString());
        $this->assertSame('1.50', $batch->fresh()->available_quantity);
        $this->assertDatabaseHas('product_batches', ['product_id' => $product->id, 'product_production_id' => $production->id, 'available_quantity' => 8]);
        $this->assertDatabaseHas('stock_movements', ['stock_batch_id' => $batch->id, 'type' => 'production_out', 'quantity' => 1.5]);
        $this->assertSame('Cream cheese', ProductProductionResource::make($production)
            ->response(Request::create('/'))
            ->getData(true)['usages'][0]['material']['name']);
    }

    public function test_batch_production_rejects_repeated_material_batches(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create([
            'name' => 'Cocoa', 'unit' => 'kg', 'minimum_stock' => 0, 'is_perishable' => false,
        ]);
        $product = app(ProductService::class)->create(new ProductData(
            product_category_id: null, name: 'Brownie mix', sale_price: 9000, is_composed: true, is_active: true,
            compositions: [['material_id' => $material->id, 'quantity_required' => 1, 'unit' => 'kg']],
            production_mode: 'batch', shelf_life_days: null,
        ));
        $batch = $this->createMaterialBatch($material, '2026-08-20', null, 10);

        try {
            app(ProductProductionService::class)->create($product, [
                'produced_quantity' => 2, 'production_date' => '2026-08-25',
                'usages' => [
                    ['stock_batch_id' => $batch->id, 'quantity' => 6],
                    ['stock_batch_id' => $batch->id, 'quantity' => 6],
                ],
            ], $user);

            $this->fail('A batch cannot be added twice to the same production.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('10.00', $batch->fresh()->available_quantity);
        $this->assertDatabaseCount('product_productions', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_product_waste_reduces_its_batch_and_is_reflected_in_profitability(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Bottled juice', 'sale_price' => 5000, 'is_composed' => false, 'is_active' => true,
        ]);
        $batch = $this->createProductBatch($product, 5, 1200, '2026-08-25');

        app(ProductStockMovementService::class)->create(new ProductStockMovementData(
            product_batch_id: $batch->id,
            type: 'waste',
            quantity: 2,
            reason: 'Broken bottles',
            movement_date: '2026-08-25 12:00:00',
        ), $user);

        $this->assertSame('3.00', $batch->fresh()->available_quantity);
        $this->assertSame('available', $batch->fresh()->status);
        $this->assertDatabaseHas('product_stock_movements', [
            'product_id' => $product->id,
            'product_batch_id' => $batch->id,
            'type' => 'waste',
            'quantity' => 2,
            'reason' => 'Broken bottles',
        ]);

        $summary = app(DashboardService::class)->summary('2026-08-25', '2026-08-25');

        $this->assertSame('2400.00', $summary['profitability']['waste_cost']);
        $this->assertSame('2400.00', $summary['profitability']['actual_food_cost']);
        $this->assertSame('-2400.00', $summary['profitability']['adjusted_gross_profit']);
    }

    public function test_product_waste_can_not_exceed_available_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Soda', 'sale_price' => 3000, 'is_composed' => false, 'is_active' => true,
        ]);
        $batch = $this->createProductBatch($product, 1, 1000, '2026-08-25');

        try {
            app(ProductStockMovementService::class)->create(new ProductStockMovementData(
                product_batch_id: $batch->id,
                type: 'waste',
                quantity: 1.01,
                reason: 'Broken bottle',
                movement_date: null,
            ), $user);

            $this->fail('Waste must not exceed available product stock.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('1.00', $batch->fresh()->available_quantity);
        $this->assertDatabaseCount('product_stock_movements', 0);
    }

    public function test_product_waste_query_excludes_sale_movements(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Sparkling water', 'sale_price' => 3000, 'is_composed' => false, 'is_active' => true,
        ]);
        $batch = $this->createProductBatch($product, 5, 1000, '2026-08-25');

        ProductStockMovement::query()->create([
            'product_id' => $product->id, 'product_batch_id' => $batch->id, 'user_id' => $user->id,
            'type' => 'sale', 'quantity' => 1, 'reason' => 'Sale #1', 'movement_date' => '2026-08-25',
        ]);
        ProductStockMovement::query()->create([
            'product_id' => $product->id, 'product_batch_id' => $batch->id, 'user_id' => $user->id,
            'type' => 'waste', 'quantity' => 1, 'reason' => 'Damaged bottle', 'movement_date' => '2026-08-25',
        ]);
        ProductStockMovement::query()->create([
            'product_id' => $product->id, 'product_batch_id' => $batch->id, 'user_id' => $user->id,
            'type' => 'expired', 'quantity' => 1, 'reason' => 'Expired batch', 'movement_date' => '2026-08-25',
        ]);

        $types = app(ProductStockMovementService::class)->wasteQueryBuilder()->pluck('type')->all();
        sort($types);

        $this->assertSame(['expired', 'waste'], $types);
    }

    public function test_partial_sale_return_restocks_the_exact_product_batch_and_updates_profitability(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Juice', 'sale_price' => 5000, 'is_composed' => false, 'is_active' => true]);
        $batch = $this->createProductBatch($product, 5, 1200, '2026-08-25');
        $sale = app(SaleService::class)->create(new SaleData(
            sale_date: '2026-08-25', notes: null, items: [['product_id' => $product->id, 'quantity' => 3]],
        ), $user);

        $return = app(SaleReturnService::class)->create($sale, [
            'return_date' => '2026-08-26', 'reason' => 'Customer changed their mind',
            'items' => [['sale_item_id' => $sale->items->firstOrFail()->id, 'quantity' => 1, 'disposition' => 'restock']],
        ], $user);

        $this->assertSame('3.00', $batch->fresh()->available_quantity);
        $this->assertSame('5000.00', $return->total_amount);
        $this->assertDatabaseHas('product_stock_movements', ['product_batch_id' => $batch->id, 'type' => 'sale_return', 'quantity' => 1]);
        $this->assertDatabaseCount('sale_return_allocations', 1);
        $summary = app(DashboardService::class)->summary('2026-08-25', '2026-08-25');
        $this->assertSame('10000.00', $summary['sales']['total']);
        $this->assertSame('2400.00', $summary['profitability']['cogs']);
    }

    public function test_expired_product_batches_are_restocked_as_expired_by_a_return(): void
    {
        $user = User::factory()->create();
        $saleDate = now()->subDays(2)->toDateString();
        $product = Product::query()->create(['name' => 'Yogurt', 'sale_price' => 5000, 'is_composed' => false, 'is_active' => true]);
        $batch = $this->createProductBatch($product, 2, 1200, $saleDate, now()->subDay()->toDateString());
        $sale = app(SaleService::class)->create(new SaleData(
            sale_date: $saleDate, notes: null, items: [['product_id' => $product->id, 'quantity' => 1]],
        ), $user);

        app(SaleReturnService::class)->create($sale, [
            'return_date' => now()->toDateString(), 'reason' => 'Customer returned it',
            'items' => [['sale_item_id' => $sale->items->firstOrFail()->id, 'quantity' => 1, 'disposition' => 'restock']],
        ], $user);

        $this->assertSame('2.00', $batch->fresh()->available_quantity);
        $this->assertSame('expired', $batch->fresh()->status);
        $this->assertDatabaseHas('product_stock_movements', ['product_batch_id' => $batch->id, 'type' => 'sale_return', 'quantity' => 1]);
    }

    public function test_sale_return_date_can_not_be_before_the_sale_date(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create(['name' => 'Tea', 'sale_price' => 2500, 'is_composed' => false, 'is_active' => true]);
        $batch = $this->createProductBatch($product, 2, 800, '2026-08-25');
        $sale = app(SaleService::class)->create(new SaleData(
            sale_date: '2026-08-25', notes: null, items: [['product_id' => $product->id, 'quantity' => 1]],
        ), $user);

        try {
            app(SaleReturnService::class)->create($sale, [
                'return_date' => '2026-08-24', 'reason' => 'Invalid date',
                'items' => [['sale_item_id' => $sale->items->firstOrFail()->id, 'quantity' => 1, 'disposition' => 'restock']],
            ], $user);

            $this->fail('A return before its sale must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('1.00', $batch->fresh()->available_quantity);
        $this->assertDatabaseCount('sale_returns', 0);
    }

    public function test_sale_cancel_action_uses_the_cancel_sale_request(): void
    {
        $parameterType = (new \ReflectionMethod(SaleController::class, 'cancel'))
            ->getParameters()[0]
            ->getType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        $this->assertSame(CancelSaleRequest::class, $parameterType->getName());
    }

    public function test_activating_a_recipe_versions_it_and_syncs_product_consumption(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create(['name' => 'Trimmed beef', 'unit' => 'kg', 'minimum_stock' => 0, 'is_perishable' => true]);
        $product = Product::query()->create(['name' => 'Beef burger', 'sale_price' => 25000, 'is_composed' => false, 'is_active' => true]);
        $recipes = app(RecipeService::class);

        $first = $recipes->activate($product, [
            'yield_quantity' => 1,
            'yield_unit' => 'u',
            'target_food_cost_percentage' => 35,
            'ingredients' => [['material_id' => $material->id, 'net_quantity' => .18]],
        ], $user);
        $second = $recipes->activate($product, [
            'yield_quantity' => 1,
            'yield_unit' => 'u',
            'ingredients' => [['material_id' => $material->id, 'net_quantity' => .2]],
        ], $user);

        $this->assertSame(1, $first->version);
        $this->assertSame('archived', $first->fresh()->status);
        $this->assertSame(2, $second->version);
        $this->assertSame('active', $second->status);
        $this->assertSame('0.2000', $second->ingredients->firstOrFail()->net_quantity);
        $this->assertSame('0.20', $product->fresh()->compositions()->firstOrFail()->quantity_required);
    }

    private function createComposedProduct(Material $material, float $quantityRequired, float $salePrice): Product
    {
        $product = Product::query()->create([
            'name' => 'Product '.$material->name,
            'sale_price' => $salePrice,
            'is_composed' => true,
            'is_active' => true,
        ]);

        $product->compositions()->create([
            'material_id' => $material->id,
            'quantity_required' => $quantityRequired,
            'unit' => $material->unit,
        ]);

        return $product;
    }

    private function createMaterialBatch(
        Material $material,
        string $receivedDate,
        ?string $expirationDate,
        float $quantity,
    ): StockBatch {
        return StockBatch::query()->create([
            'material_id' => $material->id,
            'initial_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => 1000,
            'received_date' => $receivedDate,
            'expiration_date' => $expirationDate,
            'status' => 'available',
        ]);
    }

    private function createProductBatch(
        Product $product,
        float $quantity,
        float $unitCost,
        string $receivedDate,
        ?string $expirationDate = null,
    ): ProductBatch {
        return ProductBatch::query()->create([
            'product_id' => $product->id,
            'initial_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => $receivedDate,
            'expiration_date' => $expirationDate,
            'status' => 'available',
        ]);
    }
}
