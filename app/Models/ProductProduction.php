<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductProduction extends Model
{
    protected $fillable = ['product_id', 'user_id', 'planned_quantity', 'produced_quantity', 'production_date', 'suggested_expiration_date', 'expiration_date', 'expiration_override_reason', 'notes'];

    protected $casts = ['planned_quantity' => 'decimal:2', 'produced_quantity' => 'decimal:2', 'production_date' => 'date', 'suggested_expiration_date' => 'date', 'expiration_date' => 'date'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ProductProductionUsage::class);
    }

    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }
}
