<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockMovement\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function __construct(
        protected StockMovementService $stockMovementService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockMovement::class);

        return StockMovementResource::collection(
            $this->stockMovementService->indexQueryBuilder()->paginate(25)
        );
    }

    public function store(StoreStockMovementRequest $request): StockMovementResource
    {
        $stockMovement = $this->stockMovementService->create(
            $request->toDto(),
            $request->user()
        );

        return new StockMovementResource($stockMovement);
    }

    public function show(StockMovement $stockMovement): StockMovementResource
    {
        $this->authorize('view', $stockMovement);

        return new StockMovementResource(
            $stockMovement->load(['material.category', 'stockBatch', 'user'])
        );
    }
}
