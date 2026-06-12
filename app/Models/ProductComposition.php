<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComposition extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'material_id',
        'quantity_required',
        'unit',
    ];

    protected $casts = [
        'quantity_required' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Product, ProductComposition>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Material, ProductComposition>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
