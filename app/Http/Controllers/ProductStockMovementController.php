<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductStockMovement\StoreProductStockMovementRequest;
use App\Http\Resources\ProductStockMovementResource;
use App\Models\ProductStockMovement;
use App\Models\User;
use App\Services\ProductStockMovementService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductStockMovementController extends Controller
{
    public function __construct(protected ProductStockMovementService $productStockMovementService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductStockMovement::class);

        return ProductStockMovementResource::collection($this->productStockMovementService->indexQueryBuilder()->paginate(25));
    }

    public function waste(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductStockMovement::class);

        return ProductStockMovementResource::collection($this->productStockMovementService->wasteQueryBuilder()->paginate(25));
    }

    public function store(StoreProductStockMovementRequest $request): ProductStockMovementResource
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED, __('auth.failed'));
        }

        return new ProductStockMovementResource($this->productStockMovementService->create($request->toDto(), $user));
    }

    public function show(ProductStockMovement $productStockMovement): ProductStockMovementResource
    {
        $this->authorize('view', $productStockMovement);

        return new ProductStockMovementResource($productStockMovement->load(['product.category', 'productBatch', 'user']));
    }
}
