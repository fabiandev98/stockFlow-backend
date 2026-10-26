<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturnItem extends Model
{
    protected $fillable = ['sale_return_id', 'sale_item_id', 'quantity', 'disposition', 'unit_price', 'total_amount'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total_amount' => 'decimal:2'];

    /** @return BelongsTo<SaleReturn, $this> */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /** @return BelongsTo<SaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    /** @return HasMany<SaleReturnAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(SaleReturnAllocation::class);
    }
}
