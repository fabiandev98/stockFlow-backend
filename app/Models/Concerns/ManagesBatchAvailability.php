<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

trait ManagesBatchAvailability
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', 'available')
            ->where('available_quantity', '>', 0);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeValidOn(Builder $query, DateTimeInterface|string|null $date = null): Builder
    {
        return $query->where(function (Builder $query) use ($date): void {
            $query->whereNull('expiration_date')
                ->orWhereDate('expiration_date', '>=', $date ?? today());
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiration_date')
            ->orderBy('received_date')
            ->orderBy('id');
    }

    public function consume(float $quantity, int $precision = 2): float
    {
        $remainingQuantity = round((float) $this->available_quantity - $quantity, $precision);

        if ($remainingQuantity < 0) {
            throw new LogicException('A batch cannot consume more than its available quantity.');
        }

        $this->update([
            'available_quantity' => $remainingQuantity,
            'status' => $remainingQuantity > 0 ? 'available' : 'depleted',
        ]);

        return $remainingQuantity;
    }

    public function restore(float $quantity, int $precision = 2): float
    {
        $availableQuantity = round((float) $this->available_quantity + $quantity, $precision);

        $this->update([
            'available_quantity' => $availableQuantity,
            'status' => $this->expiration_date?->isBefore(Carbon::today()) ? 'expired' : 'available',
        ]);

        return $availableQuantity;
    }
}
