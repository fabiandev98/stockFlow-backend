<?php

namespace App\Http\Controllers;

use App\Enums\DenebPermission;
use App\Http\Resources\InventoryMaterialResource;
use App\Http\Resources\StockBatchResource;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    public function materials(Request $request): AnonymousResourceCollection
    {
        $request->user()?->hasPermissionTo(DenebPermission::INVENTORY_READ) || abort(403);

        return InventoryMaterialResource::collection(
            $this->inventoryService->materialSummaryQueryBuilder()->paginate(25)
        );
    }

    public function alerts(Request $request): JsonResource
    {
        $request->user()?->hasPermissionTo(DenebPermission::INVENTORY_READ) || abort(403);

        $alerts = $this->inventoryService->alerts(
            max(1, (int) $request->integer('expiration_window_days', 7))
        );

        return new JsonResource([
            'low_stock_materials' => InventoryMaterialResource::collection($alerts['low_stock_materials']),
            'expired_batches' => StockBatchResource::collection($alerts['expired_batches']),
            'expiring_batches' => StockBatchResource::collection($alerts['expiring_batches']),
        ]);
    }
}
