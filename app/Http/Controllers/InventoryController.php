<?php

namespace App\Http\Controllers;

use App\Enums\DenebPermission;
use App\Http\Resources\InventoryMaterialResource;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
