<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchAvailabilityRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_consuming_and_restoring_a_material_batch_keeps_its_quantity_and_status_consistent(): void
    {
        $material = Material::query()->create([
            'name' => 'Milk', 'unit' => 'l', 'minimum_stock' => 0, 'is_perishable' => true,
        ]);
        $batch = StockBatch::query()->create([
            'material_id' => $material->id,
            'initial_quantity' => 2,
            'available_quantity' => 2,
            'unit_cost' => 1000,
            'received_date' => today()->subDays(2),
            'expiration_date' => today()->subDay(),
            'status' => 'available',
        ]);

        $batch->consume(2);
        $this->assertSame('0.00', $batch->fresh()->available_quantity);
        $this->assertSame('depleted', $batch->fresh()->status);

        $batch->restore(1);
        $this->assertSame('1.00', $batch->fresh()->available_quantity);
        $this->assertSame('expired', $batch->fresh()->status);
    }

    public function test_product_batch_uses_the_same_availability_rules(): void
    {
        $product = Product::query()->create([
            'name' => 'Bottled juice', 'sale_price' => 5000, 'is_composed' => false, 'is_active' => true,
        ]);
        $batch = ProductBatch::query()->create([
            'product_id' => $product->id,
            'initial_quantity' => 3,
            'available_quantity' => 3,
            'unit_cost' => 1200,
            'received_date' => today(),
            'status' => 'available',
        ]);

        $batch->consume(1);

        $this->assertSame('2.00', $batch->fresh()->available_quantity);
        $this->assertSame('available', $batch->fresh()->status);
    }

    public function test_product_inventory_helpers_distinguish_composed_and_simple_products(): void
    {
        $onSale = Product::query()->create([
            'name' => 'Soup', 'sale_price' => 10000, 'is_composed' => true, 'is_active' => true,
        ]);
        $simpleProduct = Product::query()->create([
            'name' => 'Cheesecake', 'sale_price' => 15000, 'is_composed' => false, 'is_active' => true,
        ]);

        $this->assertTrue($onSale->isPreparedOnSale());
        $this->assertFalse($onSale->usesProductBatches());
        $this->assertTrue($simpleProduct->usesProductBatches());
    }
}
