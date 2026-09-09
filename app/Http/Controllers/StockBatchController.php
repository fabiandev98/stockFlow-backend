<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockBatchResource;
use App\Models\StockBatch;
use App\Services\StockBatchService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockBatchController extends Controller
{
    public function __construct(
        protected StockBatchService $stockBatchService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockBatch::class);

        return StockBatchResource::collection(
            $this->stockBatchService->indexQueryBuilder()->paginate(
                min(250, max(1, $request->integer('per_page', 25)))
            )
        );
    }

    public function show(StockBatch $stockBatch): StockBatchResource
    {
        $this->authorize('view', $stockBatch);

        return new StockBatchResource(
            $stockBatch->load(['material.category', 'purchaseItem.purchase.supplier'])
        );
    }
}
