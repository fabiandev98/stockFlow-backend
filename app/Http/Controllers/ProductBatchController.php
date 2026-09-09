<?php

namespace App\Http\Controllers;

use App\Enums\DenebPermission;
use App\Http\Resources\ProductBatchResource;
use App\Models\ProductBatch;
use App\Services\ProductBatchService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductBatchController extends Controller
{
    public function __construct(protected ProductBatchService $productBatchService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->user()?->hasPermissionTo(DenebPermission::STOCK_BATCHES_READ) || abort(403);

        return ProductBatchResource::collection($this->productBatchService->indexQueryBuilder()->paginate(
            min(250, max(1, $request->integer('per_page', 25)))
        ));
    }

    public function show(Request $request, ProductBatch $productBatch): ProductBatchResource
    {
        $request->user()?->hasPermissionTo(DenebPermission::STOCK_BATCHES_READ) || abort(403);

        return new ProductBatchResource($productBatch->load(['product.category', 'purchaseItem.purchase.supplier', 'production']));
    }
}
