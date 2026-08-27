<?php

namespace Tests\Feature;

use App\Data\Product\ProductData;
use App\Data\Sale\SaleData;
use App\Data\StockMovement\StockMovementData;
use App\Models\Material;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ProductProductionService;
use App\Services\ProductService;
use App\Services\SaleService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
