<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnAllocation extends Model
{
    protected $fillable = ['sale_return_item_id', 'stock_movement_id', 'product_stock_movement_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function saleReturnItem(): BelongsTo
    {
        return $this->belongsTo(SaleReturnItem::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function productStockMovement(): BelongsTo
    {
        return $this->belongsTo(ProductStockMovement::class);
    }
}
