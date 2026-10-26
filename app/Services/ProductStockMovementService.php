<?php

namespace App\Services;

use App\Data\ProductStockMovement\ProductStockMovementData;
use App\Models\ProductBatch;
use App\Models\ProductStockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductStockMovementService
{
    /** @return QueryBuilder<ProductStockMovement> */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            ProductStockMovement::query()
                ->with(['product.category', 'productBatch', 'user'])
                ->latest('movement_date')
        )->allowedFilters([
            'type',
            AllowedFilter::callback('waste_only', function (Builder $query, $value) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                    $query->whereIn('type', ['waste', 'expired']);
                }
            }),
            AllowedFilter::callback('start_date', fn (Builder $query, $value) => $query->whereDate('movement_date', '>=', $value)),
            AllowedFilter::callback('end_date', fn (Builder $query, $value) => $query->whereDate('movement_date', '<=', $value)),
            AllowedFilter::exact('product_id'),
            AllowedFilter::exact('product_batch_id'),
            AllowedFilter::callback('global', function (Builder $query, $value) {
                $query
                    ->where('type', 'LIKE', "%$value%")
                    ->orWhere('reason', 'LIKE', "%$value%")
                    ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->where('name', 'LIKE', "%$value%"))
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'LIKE', "%$value%"));
            }),
        ]);
    }

    /** @return QueryBuilder<ProductStockMovement> */
    public function wasteQueryBuilder(): QueryBuilder
    {
        $query = $this->indexQueryBuilder();
        $query->whereIn('type', ['waste', 'expired']);

        return $query;
    }

    public function create(ProductStockMovementData $data, User $user): ProductStockMovement
    {
        return DB::transaction(function () use ($data, $user) {
            $batch = ProductBatch::query()
                ->whereKey($data->product_batch_id)
                ->lockForUpdate()
                ->firstOrFail();

            $availableQuantity = (float) $batch->available_quantity;

            if ($data->quantity > $availableQuantity) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Movement quantity can not be greater than available stock');
            }

            $remainingQuantity = round($availableQuantity - $data->quantity, 2);
            $batch->update([
                'available_quantity' => $remainingQuantity,
                'status' => $this->resolveBatchStatus($batch->expiration_date, $remainingQuantity, $data->type),
            ]);

            $movement = ProductStockMovement::query()->create([
                'product_id' => $batch->product_id,
                'product_batch_id' => $batch->id,
                'user_id' => $user->id,
                'type' => $data->type,
                'quantity' => $data->quantity,
                'reason' => $data->reason,
                'movement_date' => $data->movement_date ?? now(),
            ]);

            return $movement->load(['product.category', 'productBatch', 'user']);
        });
    }

    private function resolveBatchStatus(?Carbon $expirationDate, float $remainingQuantity, string $movementType): string
    {
        if ($expirationDate?->isBefore(today())) {
            return 'expired';
        }

        return $remainingQuantity > 0 ? 'available' : ($movementType === 'expired' ? 'expired' : 'depleted');
    }
}
