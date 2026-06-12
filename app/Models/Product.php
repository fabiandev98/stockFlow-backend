<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_category_id',
        'name',
        'sale_price',
        'is_active',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<ProductComposition>
     */
    public function compositions(): HasMany
    {
        return $this->hasMany(ProductComposition::class);
    }

    /**
     * @return HasMany<SaleItem>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
