<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductProductionUsage extends Model
{
    protected $fillable = ['product_production_id', 'material_id', 'stock_batch_id', 'quantity', 'unit_cost'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_cost' => 'decimal:4'];

    public function production(): BelongsTo
    {
        return $this->belongsTo(ProductProduction::class, 'product_production_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
