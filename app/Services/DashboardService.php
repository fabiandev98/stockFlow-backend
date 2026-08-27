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
        $salesQuery = Sale::query()
            ->completed()
            ->whereBetween('sale_date', [$start, $end]);

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'expiration_window_days' => max(1, $expirationWindowDays),
            ],
            'sales' => [
                'total' => number_format((float) (clone $salesQuery)->sum('total_amount'), 2, '.', ''),
                'count' => (clone $salesQuery)->count(),
                'items_sold' => (float) SaleItem::query()
                    ->whereHas('sale', fn ($query) => $query->completed()->whereBetween('sale_date', [$start, $end]))
                    ->sum('quantity'),
            ],
            'profitability' => $this->profitability($start, $end),
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

    /**
     * @return array{gross_sales: string, discounts: string, net_sales: string, taxes: string, cogs: string, gross_profit: string, food_cost_percentage: string, gross_margin_percentage: string, waste_cost: string, actual_food_cost: string, actual_food_cost_percentage: string, adjusted_gross_profit: string}
     */
    private function profitability(Carbon $start, Carbon $end): array
    {
        $sales = Sale::query()
            ->completed()
            ->whereBetween('sale_date', [$start, $end]);
        $grossSales = (float) (clone $sales)->sum('subtotal_amount');
        $discounts = (float) (clone $sales)->sum('discount_amount');
        $taxes = (float) (clone $sales)->sum('tax_amount');
        $netSales = round($grossSales - $discounts, 2);
        $materialCogs = (float) DB::table('stock_movements')
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.stock_batch_id')
            ->join('sale_items', 'sale_items.id', '=', 'stock_movements.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('stock_movements.type', 'sale')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->selectRaw('COALESCE(SUM(stock_movements.quantity * stock_batches.unit_cost), 0) as total')
            ->value('total');
        $productCogs = (float) DB::table('product_stock_movements')
            ->join('product_batches', 'product_batches.id', '=', 'product_stock_movements.product_batch_id')
            ->join('sale_items', 'sale_items.id', '=', 'product_stock_movements.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('product_stock_movements.type', 'sale')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->selectRaw('COALESCE(SUM(product_stock_movements.quantity * product_batches.unit_cost), 0) as total')
            ->value('total');
        $cogs = round($materialCogs + $productCogs, 2);
        $grossProfit = round($netSales - $cogs, 2);
        $wasteCost = (float) DB::table('stock_movements')
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.stock_batch_id')
            ->whereIn('stock_movements.type', ['waste', 'expired'])
            ->whereBetween('stock_movements.movement_date', [$start, $end])
            ->selectRaw('COALESCE(SUM(stock_movements.quantity * stock_batches.unit_cost), 0) as total')
            ->value('total');
        $actualFoodCost = round($cogs + $wasteCost, 2);
        $adjustedGrossProfit = round($netSales - $actualFoodCost, 2);

        return [
            'gross_sales' => number_format($grossSales, 2, '.', ''),
            'discounts' => number_format($discounts, 2, '.', ''),
            'net_sales' => number_format($netSales, 2, '.', ''),
            'taxes' => number_format($taxes, 2, '.', ''),
            'cogs' => number_format($cogs, 2, '.', ''),
            'gross_profit' => number_format($grossProfit, 2, '.', ''),
            'food_cost_percentage' => number_format($netSales > 0 ? $cogs / $netSales * 100 : 0, 2, '.', ''),
            'gross_margin_percentage' => number_format($netSales > 0 ? $grossProfit / $netSales * 100 : 0, 2, '.', ''),
            'waste_cost' => number_format($wasteCost, 2, '.', ''),
            'actual_food_cost' => number_format($actualFoodCost, 2, '.', ''),
            'actual_food_cost_percentage' => number_format($netSales > 0 ? $actualFoodCost / $netSales * 100 : 0, 2, '.', ''),
            'adjusted_gross_profit' => number_format($adjustedGrossProfit, 2, '.', ''),
        ];
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
            ->where('sales.status', 'completed')
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
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->groupBy(DB::raw('DATE(sales.sale_date)'))
            ->selectRaw('DATE(sales.sale_date) as date')
            ->selectRaw('SUM(sale_items.quantity) as items')
            ->pluck('items', 'date');

        $rows = DB::table('sales')
            ->where('status', 'completed')
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
