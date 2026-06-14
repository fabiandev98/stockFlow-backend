<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    /**
     * @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory>
     */
    use HasFactory;

    protected $fillable = [
        'material_id',
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
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @return BelongsTo<PurchaseItem, $this>
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
