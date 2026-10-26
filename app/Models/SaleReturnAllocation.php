<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnAllocation extends Model
{
    protected $fillable = ['sale_return_item_id', 'stock_movement_id', 'product_stock_movement_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:2'];

    /** @return BelongsTo<SaleReturnItem, $this> */
    public function saleReturnItem(): BelongsTo
    {
        return $this->belongsTo(SaleReturnItem::class);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return BelongsTo<ProductStockMovement, $this> */
    public function productStockMovement(): BelongsTo
    {
        return $this->belongsTo(ProductStockMovement::class);
    }
}
