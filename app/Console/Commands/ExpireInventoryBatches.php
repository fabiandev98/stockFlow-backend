<?php

namespace App\Console\Commands;

use App\Models\ProductBatch;
use App\Models\StockBatch;
use Illuminate\Console\Command;

class ExpireInventoryBatches extends Command
{
    protected $signature = 'inventory:expire-batches';

    protected $description = 'Mark inventory batches with remaining quantity as expired after their expiration date';

    public function handle(): int
    {
        $expiredMaterialBatches = StockBatch::query()
            ->where('available_quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', today())
            ->where('status', '!=', 'expired')
            ->update(['status' => 'expired']);

        $expiredProductBatches = ProductBatch::query()
            ->where('available_quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', today())
            ->where('status', '!=', 'expired')
            ->update(['status' => 'expired']);

        $this->info("Expired {$expiredMaterialBatches} material batches and {$expiredProductBatches} product batches.");

        return self::SUCCESS;
    }
}
