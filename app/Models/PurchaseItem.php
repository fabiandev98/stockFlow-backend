<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    /**
     * @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory>
     */
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'material_id',
        'product_id',
        'quantity',
        'accepted_quantity',
        'rejected_quantity',
        'usable_quantity',
        'processing_waste_reason',
        'yield_recorded_at',
        'yield_reviewer_id',
        'quality_issue_reason',
        'unit_cost',
        'total_cost',
        'expiration_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'accepted_quantity' => 'decimal:2',
        'rejected_quantity' => 'decimal:2',
        'usable_quantity' => 'decimal:2',
        'yield_recorded_at' => 'datetime',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'expiration_date' => 'date',
    ];

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function yieldReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'yield_reviewer_id');
    }

    /**
     * @return HasMany<StockBatch, $this>
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * @return HasMany<ProductBatch, $this>
     */
    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }
}
