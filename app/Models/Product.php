<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /**
     * @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory>
     */
    use HasFactory;

    protected $fillable = [
        'product_category_id',
        'name',
        'sale_price',
        'is_composed',
        'is_active',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'is_composed' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return HasMany<ProductComposition, $this>
     */
    public function compositions(): HasMany
    {
        return $this->hasMany(ProductComposition::class);
    }

    /**
     * @return HasMany<ProductBatch, $this>
     */
    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * @return HasMany<ProductStockMovement, $this>
     */
    public function productStockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
