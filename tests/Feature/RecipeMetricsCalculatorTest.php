<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Product;
use App\Models\StockBatch;
use App\Services\ProductAvailabilityCalculator;
use App\Services\RecipeCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeMetricsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_the_available_quantity_for_an_on_sale_recipe(): void
    {
        $flour = Material::query()->create(['name' => 'Flour', 'unit' => 'kg', 'minimum_stock' => 0, 'is_perishable' => false]);
        $butter = Material::query()->create(['name' => 'Butter', 'unit' => 'kg', 'minimum_stock' => 0, 'is_perishable' => false]);
        $product = Product::query()->create(['name' => 'Cookie', 'sale_price' => 5000, 'is_composed' => true, 'is_active' => true]);
        $recipe = $product->recipes()->create(['version' => 1, 'status' => 'active', 'yield_quantity' => 1, 'yield_unit' => 'u']);
        $recipe->ingredients()->createMany([
            ['material_id' => $flour->id, 'net_quantity' => 0.2, 'unit' => 'kg', 'position' => 0],
            ['material_id' => $butter->id, 'net_quantity' => 0.1, 'unit' => 'kg', 'position' => 1],
        ]);
        $this->createBatch($flour, 1, 1000);
        $this->createBatch($butter, 0.3, 2000);

        $available = app(ProductAvailabilityCalculator::class)->calculate($product->load('activeRecipe.ingredients'));

        $this->assertSame(2.0, $available);
    }

    public function test_it_calculates_recipe_cost_metrics_from_available_stock(): void
    {
        $material = Material::query()->create(['name' => 'Chocolate', 'unit' => 'kg', 'minimum_stock' => 0, 'is_perishable' => false]);
        $product = Product::query()->create(['name' => 'Brownie', 'sale_price' => 10000, 'is_composed' => true, 'is_active' => true]);
        $recipe = $product->recipes()->create([
            'version' => 1,
            'status' => 'active',
            'yield_quantity' => 2,
            'yield_unit' => 'u',
            'target_food_cost_percentage' => 30,
        ]);
        $recipe->ingredients()->create(['material_id' => $material->id, 'net_quantity' => 0.5, 'unit' => 'kg', 'position' => 0]);
        $this->createBatch($material, 2, 4000);

        $metrics = app(RecipeCostCalculator::class)->calculate($recipe->load(['ingredients', 'product']));

        $this->assertSame(2000.0, $metrics['theoretical_cost']);
        $this->assertSame(1000.0, $metrics['cost_per_unit']);
        $this->assertSame(10.0, $metrics['food_cost_percentage']);
        $this->assertSame(9000.0, $metrics['gross_margin_per_unit']);
        $this->assertSame(3333.33, $metrics['suggested_sale_price']);
    }

    private function createBatch(Material $material, float $quantity, float $unitCost): void
    {
        StockBatch::query()->create([
            'material_id' => $material->id,
            'initial_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => today(),
            'status' => 'available',
        ]);
    }
}
