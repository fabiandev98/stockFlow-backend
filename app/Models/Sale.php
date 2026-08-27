<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /**
     * @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory>
     */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sale_date',
        'covers',
        'subtotal_amount',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'status',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'covers' => 'integer',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
