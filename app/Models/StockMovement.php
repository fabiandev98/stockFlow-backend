<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'stock_batch_id',
        'sale_item_id',
        'user_id',
        'type',
        'quantity',
        'reason',
        'movement_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'movement_date' => 'datetime',
    ];

    /**
     * @return BelongsTo<Material, StockMovement>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @return BelongsTo<StockBatch, StockMovement>
     */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    /**
     * @return BelongsTo<SaleItem, StockMovement>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    /**
     * @return BelongsTo<User, StockMovement>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
