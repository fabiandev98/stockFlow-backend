<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedCurrentInventory extends Command
{
    protected $signature = 'inventory:seed-current-stock';

    protected $description = 'Create current demo purchase lots for materials without available, non-expired stock';

    public function handle(): int
    {
        $user = User::query()->first();
        if (! $user) {
            $this->error('Create a user before seeding current inventory.');

            return self::FAILURE;
        }

        $materials = Material::query()
            ->whereDoesntHave('stockBatches', function ($query) {
                $query->where('status', 'available')
                    ->where('available_quantity', '>', 0)
                    ->where(function ($query) {
                        $query->whereNull('expiration_date')
                            ->orWhereDate('expiration_date', '>=', today());
                    });
            })
            ->get();

        if ($materials->isEmpty()) {
            $this->info('All materials already have current stock.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($materials, $user) {
            $supplier = Supplier::query()->firstOrCreate(['name' => 'Demo replenishment supplier']);
            $purchase = Purchase::query()->create([
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'purchase_date' => today(),
                'total_cost' => 0,
                'notes' => 'Current inventory demo replenishment',
            ]);
            $totalCost = 0.0;

            foreach ($materials as $material) {
                $referenceBatch = $material->stockBatches()->latest('received_date')->first();
                $quantity = (float) ($referenceBatch?->initial_quantity ?? max(1, (float) $material->minimum_stock * 2));
                $unitCost = (float) ($referenceBatch?->unit_cost ?? 0);
                $expirationDate = $material->is_perishable
                    ? today()->addDays($material->default_expiration_days ?? 14)
                    : null;
                $item = PurchaseItem::query()->create([
                    'purchase_id' => $purchase->id,
                    'material_id' => $material->id,
                    'quantity' => $quantity,
                    'accepted_quantity' => $quantity,
                    'rejected_quantity' => 0,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($quantity * $unitCost, 2),
                    'expiration_date' => $expirationDate,
                ]);
                StockBatch::query()->create([
                    'material_id' => $material->id,
                    'purchase_item_id' => $item->id,
                    'initial_quantity' => $quantity,
                    'available_quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'received_date' => today(),
                    'expiration_date' => $expirationDate,
                    'status' => 'available',
                ]);
                $totalCost += $quantity * $unitCost;
            }

            $purchase->update(['total_cost' => round($totalCost, 2)]);
        });

        $this->info("Created current stock for {$materials->count()} materials.");

        return self::SUCCESS;
    }
}
