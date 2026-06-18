<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBatch extends Model
{
    /**
     * @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory>
     */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'purchase_item_id',
        'initial_quantity',
        'available_quantity',
        'unit_cost',
        'received_date',
        'expiration_date',
        'status',
    ];

    protected $casts = [
        'initial_quantity' => 'decimal:2',
        'available_quantity' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'received_date' => 'date',
        'expiration_date' => 'date',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<PurchaseItem, $this>
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    /**
     * @return HasMany<ProductStockMovement, $this>
     */
    public function productStockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }
}
