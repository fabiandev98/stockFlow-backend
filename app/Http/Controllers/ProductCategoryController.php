<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductCategoryRequest;
use App\Http\Requests\Product\UpdateProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ProductCategoryController extends Controller
{
    public function __construct(
        protected ProductCategoryService $productCategoryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductCategory::class);

        return ProductCategoryResource::collection(
            $this->productCategoryService->indexQueryBuilder()->paginate(25)
        );
    }

    public function store(StoreProductCategoryRequest $request): ProductCategoryResource
    {
        $productCategory = $this->productCategoryService->create($request->toDto());

        return new ProductCategoryResource($productCategory);
    }

    public function show(ProductCategory $productCategory): ProductCategoryResource
    {
        $this->authorize('view', $productCategory);

        return new ProductCategoryResource($productCategory->loadCount('products'));
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $productCategory,
    ): ProductCategoryResource {
        $updatedProductCategory = $this->productCategoryService->update(
            $productCategory,
            $request->toDto(),
        );

        return new ProductCategoryResource($updatedProductCategory);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $this->authorize('delete', $productCategory);
        $this->productCategoryService->delete($productCategory);

        return response()->json(null, HttpStatus::HTTP_NO_CONTENT);
    }
}
