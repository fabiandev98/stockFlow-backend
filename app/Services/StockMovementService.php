<?php

namespace App\Services;

use App\Data\StockMovement\StockMovementData;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StockMovementService
{
    private const INCREASE_TYPES = [
        'manual_in',
        'adjustment_in',
    ];

    private const DECREASE_TYPES = [
        'manual_out',
        'adjustment_out',
        'waste',
        'expired',
    ];

    /**
     * @return QueryBuilder<StockMovement>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            StockMovement::query()
                ->with(['material.category', 'stockBatch', 'user'])
                ->latest('movement_date')
        )
            ->allowedFilters([
                'type',
                AllowedFilter::exact('material_id'),
                AllowedFilter::exact('stock_batch_id'),
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('type', 'LIKE', "%$value%")
                        ->orWhere('reason', 'LIKE', "%$value%")
                        ->orWhereHas('material', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        })
                        ->orWhereHas('user', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        });
                }),
            ]);
    }

    public function create(StockMovementData $data, User $user): StockMovement
    {
        return DB::transaction(function () use ($data, $user) {
            $stockBatch = StockBatch::query()
                ->whereKey($data->stock_batch_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyQuantityChange($stockBatch, $data);

            $stockMovement = StockMovement::create([
                'material_id' => $stockBatch->material_id,
                'stock_batch_id' => $stockBatch->id,
                'user_id' => $user->id,
                'type' => $data->type,
                'quantity' => $data->quantity,
                'reason' => $data->reason,
                'movement_date' => $data->movement_date ?? now(),
            ]);

            return $stockMovement->load(['material.category', 'stockBatch', 'user']);
        });
    }

    private function applyQuantityChange(StockBatch $stockBatch, StockMovementData $data): void
    {
        $currentQuantity = (float) $stockBatch->available_quantity;
        $movementQuantity = $data->quantity;

        if (in_array($data->type, self::INCREASE_TYPES, true)) {
            $newQuantity = $currentQuantity + $movementQuantity;
        } elseif (in_array($data->type, self::DECREASE_TYPES, true)) {
            if ($movementQuantity > $currentQuantity) {
                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'Movement quantity can not be greater than available stock'
                );
            }

            $newQuantity = $currentQuantity - $movementQuantity;
        } else {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid stock movement type'
            );
        }

        $stockBatch->update([
            'available_quantity' => round($newQuantity, 2),
            'status' => $this->resolveBatchStatus($data->type, $newQuantity),
        ]);
    }

    private function resolveBatchStatus(string $type, float $availableQuantity): string
    {
        if ($availableQuantity > 0) {
            return 'available';
        }

        return $type === 'expired' ? 'expired' : 'depleted';
    }
}
