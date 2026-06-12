<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_category_id',
        'name',
        'unit',
        'minimum_stock',
        'is_perishable',
        'default_expiration_days',
    ];

    protected $casts = [
        'minimum_stock' => 'decimal:2',
        'is_perishable' => 'boolean',
        'default_expiration_days' => 'integer',
    ];

    /**
     * @return BelongsTo<MaterialCategory, Material>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    /**
     * @return HasMany<ProductComposition>
     */
    public function productCompositions(): HasMany
    {
        return $this->hasMany(ProductComposition::class);
    }

    /**
     * @return HasMany<PurchaseItem>
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<StockBatch>
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * @return HasMany<StockMovement>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
