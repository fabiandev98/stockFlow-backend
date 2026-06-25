<?php

namespace App\Services;

use App\Models\Material;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
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
                'items_purchased' => (float) PurchaseItem::query()
                    ->whereHas('purchase', fn ($query) => $query->whereBetween('purchase_date', [$start, $end]))
                    ->sum('quantity'),
            ],
            'inventory' => [
                'low_stock_materials' => $this->lowStockMaterialsCount(),
                'inventory_value' => number_format($this->inventoryValue(), 2, '.', ''),
                'expired_batches' => $this->expiredBatchesCount($today),
                'expiring_batches' => $this->expiringBatchesCount($today, $expirationLimit),
            ],
            'top_products' => $this->topProducts($start, $end),
            'sales_by_day' => $this->salesByDay($start, $end),
            'purchases_by_day' => $this->purchasesByDay($start, $end),
            'top_suppliers' => $this->topSuppliers($start, $end),
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
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->groupBy('sale_items.product_id', 'products.name')
            ->orderByDesc(DB::raw('SUM(sale_items.quantity)'))
            ->limit(5)
            ->select('sale_items.product_id', 'products.name as product_name')
            ->selectRaw('SUM(sale_items.quantity) as quantity')
            ->selectRaw('SUM(sale_items.total_price) as total')
            ->get()
            ->map(fn ($item) => [
                'product_id' => (int) data_get($item, 'product_id'),
                'product_name' => (string) data_get($item, 'product_name'),
                'quantity' => number_format((float) data_get($item, 'quantity'), 2, '.', ''),
                'total' => number_format((float) data_get($item, 'total'), 2, '.', ''),
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * @return list<array{date: string, total: string, count: int, items: string}>
     */
    private function salesByDay(Carbon $start, Carbon $end): array
    {
        $itemsByDay = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->groupBy(DB::raw('DATE(sales.sale_date)'))
            ->selectRaw('DATE(sales.sale_date) as date')
            ->selectRaw('SUM(sale_items.quantity) as items')
            ->pluck('items', 'date');

        $rows = DB::table('sales')
            ->whereBetween('sale_date', [$start, $end])
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->orderBy(DB::raw('DATE(sale_date)'))
            ->selectRaw('DATE(sale_date) as date')
            ->selectRaw('SUM(total_amount) as total')
            ->selectRaw('COUNT(*) as count')
            ->get()
            ->map(fn ($item) => [
                'date' => (string) data_get($item, 'date'),
                'total' => number_format((float) data_get($item, 'total'), 2, '.', ''),
                'count' => (int) data_get($item, 'count'),
                'items' => number_format((float) $itemsByDay->get((string) data_get($item, 'date'), 0), 2, '.', ''),
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * @return list<array{date: string, total: string, count: int, items: string}>
     */
    private function purchasesByDay(Carbon $start, Carbon $end): array
    {
        $itemsByDay = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->whereBetween('purchases.purchase_date', [$start, $end])
            ->groupBy(DB::raw('DATE(purchases.purchase_date)'))
            ->selectRaw('DATE(purchases.purchase_date) as date')
            ->selectRaw('SUM(purchase_items.quantity) as items')
            ->pluck('items', 'date');

        $rows = DB::table('purchases')
            ->whereBetween('purchase_date', [$start, $end])
            ->groupBy(DB::raw('DATE(purchase_date)'))
            ->orderBy(DB::raw('DATE(purchase_date)'))
            ->selectRaw('DATE(purchase_date) as date')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(total_cost) as total')
            ->get()
            ->map(fn ($item) => [
                'date' => (string) data_get($item, 'date'),
                'total' => number_format((float) data_get($item, 'total'), 2, '.', ''),
                'count' => (int) data_get($item, 'count'),
                'items' => number_format((float) $itemsByDay->get((string) data_get($item, 'date'), 0), 2, '.', ''),
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * @return list<array{supplier_id: int|null, supplier_name: string|null, purchases_count: int, total: string}>
     */
    private function topSuppliers(Carbon $start, Carbon $end): array
    {
        $rows = DB::table('purchases')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereBetween('purchases.purchase_date', [$start, $end])
            ->groupBy('purchases.supplier_id', 'suppliers.name')
            ->orderByDesc(DB::raw('SUM(purchases.total_cost)'))
            ->limit(5)
            ->select('purchases.supplier_id', 'suppliers.name as supplier_name')
            ->selectRaw('COUNT(purchases.id) as purchases_count')
            ->selectRaw('SUM(purchases.total_cost) as total')
            ->get()
            ->map(fn ($item) => [
                'supplier_id' => data_get($item, 'supplier_id') !== null
                    ? (int) data_get($item, 'supplier_id')
                    : null,
                'supplier_name' => data_get($item, 'supplier_name') !== null
                    ? (string) data_get($item, 'supplier_name')
                    : null,
                'purchases_count' => (int) data_get($item, 'purchases_count'),
                'total' => number_format((float) data_get($item, 'total'), 2, '.', ''),
            ])
            ->all();

        return array_values($rows);
    }
}
