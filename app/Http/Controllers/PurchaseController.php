<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Purchase::class);

        return PurchaseResource::collection(
            $this->purchaseService->indexQueryBuilder()->paginate(25)
        );
    }

    public function store(StorePurchaseRequest $request): PurchaseResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $purchase = $this->purchaseService->create($request->toDto(), $user);

        return new PurchaseResource($purchase);
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        $this->authorize('view', $purchase);

        return new PurchaseResource(
            $purchase->load([
                'supplier',
                'user',
                'items.material.category',
                'items.stockBatches',
            ])->loadCount('items')
        );
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): PurchaseResource
    {
        $updatedPurchase = $this->purchaseService->update($purchase, $request->toDto());

        return new PurchaseResource($updatedPurchase);
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        $this->authorize('delete', $purchase);
        $this->purchaseService->delete($purchase);

        return response()->json(null, HttpStatus::HTTP_NO_CONTENT);
    }
}
