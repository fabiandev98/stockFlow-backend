<?php

namespace Database\Seeders;

use App\Enums\DenebPermission;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class StockFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('deneb:permissions-sync');

        DB::transaction(function () {
            $this->seedRoles();

            $user = User::query()->first();

            if (! $user) {
                $this->command?->warn('No users found. Run php artisan deneb:init before demo data if you want purchases and sales assigned to a user.');

                return;
            }

            $materials = $this->seedMaterials();
            $supplier = $this->seedSuppliers();
            $this->seedPurchase($user, $supplier, $materials);
            $products = $this->seedProducts($materials);
            $this->seedSale($user, $products);
        });
    }

    private function seedRoles(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'display_name' => 'Administrator',
                'description' => 'Manages daily operations without changing critical system permissions.',
                'permissions' => [
                    DenebPermission::MATERIAL_CATEGORIES_CREATE,
                    DenebPermission::MATERIAL_CATEGORIES_READ,
                    DenebPermission::MATERIAL_CATEGORIES_UPDATE,
                    DenebPermission::MATERIAL_CATEGORIES_DELETE,
                    DenebPermission::MATERIALS_CREATE,
                    DenebPermission::MATERIALS_READ,
                    DenebPermission::MATERIALS_UPDATE,
                    DenebPermission::MATERIALS_DELETE,
                    DenebPermission::PRODUCT_CATEGORIES_CREATE,
                    DenebPermission::PRODUCT_CATEGORIES_READ,
                    DenebPermission::PRODUCT_CATEGORIES_UPDATE,
                    DenebPermission::PRODUCT_CATEGORIES_DELETE,
                    DenebPermission::PRODUCTS_CREATE,
                    DenebPermission::PRODUCTS_READ,
                    DenebPermission::PRODUCTS_UPDATE,
                    DenebPermission::PRODUCTS_DELETE,
                    DenebPermission::SUPPLIERS_CREATE,
                    DenebPermission::SUPPLIERS_READ,
                    DenebPermission::SUPPLIERS_UPDATE,
                    DenebPermission::SUPPLIERS_DELETE,
                    DenebPermission::PURCHASES_CREATE,
                    DenebPermission::PURCHASES_READ,
                    DenebPermission::PURCHASES_UPDATE,
                    DenebPermission::PURCHASES_DELETE,
                    DenebPermission::SALES_CREATE,
                    DenebPermission::SALES_READ,
                    DenebPermission::STOCK_BATCHES_READ,
                    DenebPermission::STOCK_MOVEMENTS_CREATE,
                    DenebPermission::STOCK_MOVEMENTS_READ,
                    DenebPermission::INVENTORY_READ,
                ],
            ],
            [
                'name' => 'Inventory',
                'display_name' => 'Inventory',
                'description' => 'Manages materials, stock batches, inventory alerts, and stock movements.',
                'permissions' => [
                    DenebPermission::MATERIAL_CATEGORIES_READ,
                    DenebPermission::MATERIALS_CREATE,
                    DenebPermission::MATERIALS_READ,
                    DenebPermission::MATERIALS_UPDATE,
                    DenebPermission::SUPPLIERS_READ,
                    DenebPermission::PURCHASES_READ,
                    DenebPermission::STOCK_BATCHES_READ,
                    DenebPermission::STOCK_MOVEMENTS_CREATE,
                    DenebPermission::STOCK_MOVEMENTS_READ,
                    DenebPermission::INVENTORY_READ,
                ],
            ],
            [
                'name' => 'Sales',
                'display_name' => 'Sales',
                'description' => 'Registers sales and reviews product and sales information.',
                'permissions' => [
                    DenebPermission::PRODUCT_CATEGORIES_READ,
                    DenebPermission::PRODUCTS_READ,
                    DenebPermission::SALES_CREATE,
                    DenebPermission::SALES_READ,
                    DenebPermission::INVENTORY_READ,
                ],
            ],
            [
                'name' => 'Auditor',
                'display_name' => 'Auditor',
                'description' => 'Read-only access for operational review and traceability.',
                'permissions' => [
                    DenebPermission::MATERIAL_CATEGORIES_READ,
                    DenebPermission::MATERIALS_READ,
                    DenebPermission::PRODUCT_CATEGORIES_READ,
                    DenebPermission::PRODUCTS_READ,
                    DenebPermission::SUPPLIERS_READ,
                    DenebPermission::PURCHASES_READ,
                    DenebPermission::SALES_READ,
                    DenebPermission::STOCK_BATCHES_READ,
                    DenebPermission::STOCK_MOVEMENTS_READ,
                    DenebPermission::INVENTORY_READ,
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => 'web'],
                [
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'],
                    'hierarchy' => Role::getMaxHierarchy() + 1,
                ],
            );

            $role->update([
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
            ]);

            $role->syncPermissions(
                collect($roleData['permissions'])
                    ->map(fn (DenebPermission $permission): Permission => Permission::query()->firstOrCreate([
                        'name' => $permission->value,
                        'guard_name' => 'web',
                    ]))
                    ->all()
            );
        }
    }

    /**
     * @return array<string, Material>
     */
    private function seedMaterials(): array
    {
        $categories = [
            'Proteins' => MaterialCategory::query()->firstOrCreate(['name' => 'Proteins']),
            'Vegetables' => MaterialCategory::query()->firstOrCreate(['name' => 'Vegetables']),
            'Packaging' => MaterialCategory::query()->firstOrCreate(['name' => 'Packaging']),
            'Dry goods' => MaterialCategory::query()->firstOrCreate(['name' => 'Dry goods']),
        ];

        return [
            'beef' => Material::query()->updateOrCreate(
                ['name' => 'Beef'],
                [
                    'material_category_id' => $categories['Proteins']->id,
                    'unit' => 'kg',
                    'minimum_stock' => 5,
                    'is_perishable' => true,
                    'default_expiration_days' => 7,
                ],
            ),
            'bun' => Material::query()->updateOrCreate(
                ['name' => 'Burger bun'],
                [
                    'material_category_id' => $categories['Dry goods']->id,
                    'unit' => 'u',
                    'minimum_stock' => 20,
                    'is_perishable' => true,
                    'default_expiration_days' => 5,
                ],
            ),
            'lettuce' => Material::query()->updateOrCreate(
                ['name' => 'Lettuce'],
                [
                    'material_category_id' => $categories['Vegetables']->id,
                    'unit' => 'kg',
                    'minimum_stock' => 2,
                    'is_perishable' => true,
                    'default_expiration_days' => 4,
                ],
            ),
            'box' => Material::query()->updateOrCreate(
                ['name' => 'Takeaway box'],
                [
                    'material_category_id' => $categories['Packaging']->id,
                    'unit' => 'u',
                    'minimum_stock' => 30,
                    'is_perishable' => false,
                    'default_expiration_days' => null,
                ],
            ),
        ];
    }

    private function seedSuppliers(): Supplier
    {
        return Supplier::query()->updateOrCreate(
            ['email' => 'orders@demo-supplier.test'],
            [
                'name' => 'Demo Supplier',
                'contact_name' => 'Laura Gomez',
                'phone' => '+57 300 000 0000',
            ],
        );
    }

    /**
     * @param  array<string, Material>  $materials
     */
    private function seedPurchase(User $user, Supplier $supplier, array $materials): void
    {
        if (Purchase::query()->where('notes', 'Demo initial stock purchase')->exists()) {
            return;
        }

        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'purchase_date' => Carbon::today()->toDateString(),
            'total_cost' => 0,
            'notes' => 'Demo initial stock purchase',
        ]);

        $items = [
            ['material' => $materials['beef'], 'quantity' => 12, 'unit_cost' => 28000, 'expiration_days' => 7],
            ['material' => $materials['bun'], 'quantity' => 80, 'unit_cost' => 900, 'expiration_days' => 5],
            ['material' => $materials['lettuce'], 'quantity' => 6, 'unit_cost' => 4500, 'expiration_days' => 4],
            ['material' => $materials['box'], 'quantity' => 100, 'unit_cost' => 650, 'expiration_days' => null],
        ];

        $totalCost = 0;

        foreach ($items as $item) {
            /** @var Material $material */
            $material = $item['material'];
            $quantity = (float) $item['quantity'];
            $unitCost = (float) $item['unit_cost'];
            $itemTotal = round($quantity * $unitCost, 2);
            $expirationDate = $item['expiration_days']
                ? Carbon::today()->addDays((int) $item['expiration_days'])->toDateString()
                : null;

            $purchaseItem = $purchase->items()->create([
                'material_id' => $material->id,
                'product_id' => null,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $itemTotal,
                'expiration_date' => $expirationDate,
            ]);

            StockBatch::query()->create([
                'material_id' => $material->id,
                'purchase_item_id' => $purchaseItem->id,
                'initial_quantity' => $quantity,
                'available_quantity' => $quantity,
                'unit_cost' => $unitCost,
                'received_date' => Carbon::today()->toDateString(),
                'expiration_date' => $expirationDate,
                'status' => 'available',
            ]);

            $totalCost += $itemTotal;
        }

        $purchase->update(['total_cost' => round($totalCost, 2)]);
    }

    /**
     * @param  array<string, Material>  $materials
     * @return array<string, Product>
     */
    private function seedProducts(array $materials): array
    {
        $preparedFoodCategory = ProductCategory::query()->firstOrCreate(['name' => 'Prepared food']);
        $beverageCategory = ProductCategory::query()->firstOrCreate(['name' => 'Beverages']);

        $burger = Product::query()->updateOrCreate(
            ['name' => 'Classic burger'],
            [
                'product_category_id' => $preparedFoodCategory->id,
                'sale_price' => 22000,
                'is_composed' => true,
                'is_active' => true,
            ],
        );

        $burger->compositions()->delete();
        $burger->compositions()->createMany([
            ['material_id' => $materials['beef']->id, 'quantity_required' => 0.18, 'unit' => 'kg'],
            ['material_id' => $materials['bun']->id, 'quantity_required' => 1, 'unit' => 'u'],
            ['material_id' => $materials['lettuce']->id, 'quantity_required' => 0.05, 'unit' => 'kg'],
            ['material_id' => $materials['box']->id, 'quantity_required' => 1, 'unit' => 'u'],
        ]);

        $water = Product::query()->updateOrCreate(
            ['name' => 'Bottled water 600ml'],
            [
                'product_category_id' => $beverageCategory->id,
                'sale_price' => 3500,
                'is_composed' => false,
                'is_active' => true,
            ],
        );
        $water->compositions()->delete();
        $this->seedSimpleProductBatch($water);

        return [
            'burger' => $burger,
            'water' => $water,
        ];
    }

    private function seedSimpleProductBatch(Product $product): void
    {
        if (ProductBatch::query()->where('product_id', $product->id)->exists()) {
            return;
        }

        $purchase = Purchase::query()->where('notes', 'Demo simple product stock purchase')->first();

        if (! $purchase) {
            $user = User::query()->first();
            $supplier = Supplier::query()->where('email', 'orders@demo-supplier.test')->first();

            if (! $user || ! $supplier) {
                return;
            }

            $purchase = Purchase::query()->create([
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'purchase_date' => Carbon::today()->toDateString(),
                'total_cost' => 0,
                'notes' => 'Demo simple product stock purchase',
            ]);
        }

        $quantity = 48;
        $unitCost = 1800;
        $itemTotal = round($quantity * $unitCost, 2);

        $purchaseItem = $purchase->items()->create([
            'material_id' => null,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $itemTotal,
            'expiration_date' => Carbon::today()->addMonths(6)->toDateString(),
        ]);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'purchase_item_id' => $purchaseItem->id,
            'initial_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => Carbon::today()->toDateString(),
            'expiration_date' => Carbon::today()->addMonths(6)->toDateString(),
            'status' => 'available',
        ]);

        $purchase->update([
            'total_cost' => round((float) $purchase->items()->sum('total_cost'), 2),
        ]);
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function seedSale(User $user, array $products): void
    {
        if (Sale::query()->where('notes', 'Demo sale consuming product composition')->exists()) {
            return;
        }

        /** @var Product $burger */
        $burger = $products['burger'];
        $quantity = 2;
        $unitPrice = (float) $burger->sale_price;

        $sale = Sale::query()->create([
            'user_id' => $user->id,
            'sale_date' => Carbon::today()->toDateString(),
            'total_amount' => round($quantity * $unitPrice, 2),
            'notes' => 'Demo sale consuming product composition',
        ]);

        $saleItem = $sale->items()->create([
            'product_id' => $burger->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => round($quantity * $unitPrice, 2),
        ]);

        foreach ($burger->compositions as $composition) {
            $requiredQuantity = round((float) $composition->quantity_required * $quantity, 2);

            $batch = StockBatch::query()
                ->where('material_id', $composition->material_id)
                ->where('status', 'available')
                ->where('available_quantity', '>=', $requiredQuantity)
                ->orderByRaw('expiration_date is null')
                ->orderBy('expiration_date')
                ->first();

            if (! $batch) {
                continue;
            }

            $newAvailableQuantity = round((float) $batch->available_quantity - $requiredQuantity, 2);

            $batch->update([
                'available_quantity' => $newAvailableQuantity,
                'status' => $newAvailableQuantity > 0 ? 'available' : 'depleted',
            ]);

            $saleItem->stockMovements()->create([
                'material_id' => $composition->material_id,
                'stock_batch_id' => $batch->id,
                'user_id' => $user->id,
                'type' => 'sale',
                'quantity' => $requiredQuantity,
                'reason' => "Demo sale #{$sale->id}",
                'movement_date' => now(),
            ]);
        }
    }
}
