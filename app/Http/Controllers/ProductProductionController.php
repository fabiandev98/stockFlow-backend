<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductProductionRequest;
use App\Http\Resources\ProductProductionResource;
use App\Models\Product;
use App\Services\ProductProductionService;

class ProductProductionController extends Controller
{
    public function store(StoreProductProductionRequest $request, Product $product, ProductProductionService $service): ProductProductionResource
    {
        return new ProductProductionResource($service->create($product, $request->validated(), $request->user()));
    }
}
