<?php

namespace App\Services;

use App\Models\Material;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?string $startDate, ?string $endDate, int $expirationWindowDays = 7): array
    {
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::today()->endOfDay();
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : $end->copy()->startOfMonth();
        $today = Carbon::today();
        $expirationLimit = $today->copy()->addDays(max(1, $expirationWindowDays));

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'expiration_window_days' => max(1, $expirationWindowDays),
            ],
            'sales' => [
                'total' => number_format((float) Sale::query()->whereBetween('sale_date', [$start, $end])->sum('total_amount'), 2, '.', ''),
                'count' => Sale::query()->whereBetween('sale_date', [$start, $end])->count(),
                'items_sold' => (float) SaleItem::query()
                    ->whereHas('sale', fn ($query) => $query->whereBetween('sale_date', [$start, $end]))
                    ->sum('quantity'),
            ],
            'purchases' => [
                'total' => number_format((float) Purchase::query()->whereBetween('purchase_date', [$start, $end])->sum('total_cost'), 2, '.', ''),
                'count' => Purchase::query()->whereBetween('purchase_date', [$start, $end])->count(),
            ],
            'inventory' => [
                'low_stock_materials' => $this->lowStockMaterialsCount(),
                'inventory_value' => number_format($this->inventoryValue(), 2, '.', ''),
                'expired_batches' => $this->expiredBatchesCount($today),
                'expiring_batches' => $this->expiringBatchesCount($today, $expirationLimit),
            ],
            'top_products' => $this->topProducts($start, $end),
            'sales_by_day' => $this->salesByDay($start, $end),
        ];
    }

    private function lowStockMaterialsCount(): int
    {
        return Material::query()
            ->whereRaw(
                'COALESCE((SELECT SUM(stock_batches.available_quantity) FROM stock_batches WHERE stock_batches.material_id = materials.id), 0) <= materials.minimum_stock'
            )
            ->count();
    }

    private function inventoryValue(): float
    {
        $materialValue = (float) StockBatch::query()
            ->where('available_quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(available_quantity * unit_cost), 0) as total')
            ->value('total');

        $productValue = (float) ProductBatch::query()
            ->where('available_quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(available_quantity * unit_cost), 0) as total')
            ->value('total');

        return round($materialValue + $productValue, 2);
    }

    private function expiredBatchesCount(Carbon $today): int
    {
        return StockBatch::query()
            ->where('available_quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', $today)
            ->count()
            + ProductBatch::query()
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<', $today)
                ->count();
    }

    private function expiringBatchesCount(Carbon $today, Carbon $expirationLimit): int
    {
        return StockBatch::query()
            ->where('available_quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', $today)
            ->whereDate('expiration_date', '<=', $expirationLimit)
            ->count()
            + ProductBatch::query()
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>=', $today)
                ->whereDate('expiration_date', '<=', $expirationLimit)
                ->count();
    }

    /**
     * @return list<array{product_id: int, product_name: string, quantity: string, total: string}>
     */
    private function topProducts(Carbon $start, Carbon $end): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->groupBy('sale_items.product_id', 'products.name')
            ->orderByDesc(DB::raw('SUM(sale_items.quantity)'))
            ->limit(5)
            ->get([
                'sale_items.product_id',
                'products.name as product_name',
                DB::raw('SUM(sale_items.quantity) as quantity'),
                DB::raw('SUM(sale_items.total_price) as total'),
            ])
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'product_name' => (string) $item->product_name,
                'quantity' => number_format((float) $item->quantity, 2, '.', ''),
                'total' => number_format((float) $item->total, 2, '.', ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{date: string, total: string}>
     */
    private function salesByDay(Carbon $start, Carbon $end): array
    {
        return Sale::query()
            ->whereBetween('sale_date', [$start, $end])
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->orderBy(DB::raw('DATE(sale_date)'))
            ->get([
                DB::raw('DATE(sale_date) as date'),
                DB::raw('SUM(total_amount) as total'),
            ])
            ->map(fn ($item) => [
                'date' => (string) $item->date,
                'total' => number_format((float) $item->total, 2, '.', ''),
            ])
            ->values()
            ->all();
    }
}
