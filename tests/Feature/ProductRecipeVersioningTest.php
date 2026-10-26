<?php

namespace Tests\Feature;

use App\Data\Product\ProductData;
use App\Models\Material;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRecipeVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_product_details_without_changing_composition_keeps_the_active_recipe_version(): void
    {
        $material = Material::query()->create([
            'name' => 'Flour',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'is_perishable' => false,
        ]);
        $products = app(ProductService::class);
        $product = $products->create($this->productData($material->id, 'Bread', 5000));

        $updated = $products->update($product, $this->productData($material->id, 'Artisan bread', 6000));

        $this->assertSame(1, $updated->recipes()->count());
        $this->assertSame(1, $updated->activeRecipe()->firstOrFail()->version);
        $this->assertSame('Artisan bread', $updated->name);
        $this->assertSame('6000.00', $updated->sale_price);
    }

    public function test_updating_a_product_composition_creates_a_new_recipe_version(): void
    {
        $firstMaterial = Material::query()->create([
            'name' => 'Flour',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'is_perishable' => false,
        ]);
        $secondMaterial = Material::query()->create([
            'name' => 'Butter',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'is_perishable' => false,
        ]);
        $products = app(ProductService::class);
        $product = $products->create($this->productData($firstMaterial->id, 'Bread', 5000));

        $updated = $products->update($product, new ProductData(
            product_category_id: null,
            name: 'Bread',
            sale_price: 5000,
            is_composed: true,
            is_active: true,
            compositions: [[
                'material_id' => $secondMaterial->id,
                'quantity_required' => 0.1,
            ]],
        ));

        $this->assertSame(2, $updated->recipes()->count());
        $this->assertSame(2, $updated->activeRecipe()->firstOrFail()->version);
        $this->assertSame($secondMaterial->id, $updated->activeRecipe()->firstOrFail()->ingredients()->firstOrFail()->material_id);
    }

    private function productData(int $materialId, string $name, float $salePrice): ProductData
    {
        return new ProductData(
            product_category_id: null,
            name: $name,
            sale_price: $salePrice,
            is_composed: true,
            is_active: true,
            compositions: [[
                'material_id' => $materialId,
                'quantity_required' => 0.2,
            ]],
        );
    }
}
