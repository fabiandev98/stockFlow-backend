<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sale::class);

        return SaleResource::collection(
            $this->saleService->indexQueryBuilder()->paginate(25)
        );
    }

    public function store(StoreSaleRequest $request): SaleResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $sale = $this->saleService->create($request->toDto(), $user);

        return new SaleResource($sale);
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorize('view', $sale);

        return new SaleResource(
            $sale->load([
                'user',
                'items.product.category',
                'items.stockMovements.material',
                'items.stockMovements.stockBatch',
                'items.productStockMovements.product',
                'items.productStockMovements.productBatch',
            ])->loadCount('items')
        );
    }
}
