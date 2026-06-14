<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockBatchResource;
use App\Models\StockBatch;
use App\Services\StockBatchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockBatchController extends Controller
{
    public function __construct(
        protected StockBatchService $stockBatchService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockBatch::class);

        return StockBatchResource::collection(
            $this->stockBatchService->indexQueryBuilder()->paginate(25)
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
