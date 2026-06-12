<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialCategory\StoreMaterialCategoryRequest;
use App\Http\Requests\MaterialCategory\UpdateMaterialCategoryRequest;
use App\Http\Resources\MaterialCategoryResource;
use App\Models\MaterialCategory;
use App\Services\MaterialCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class MaterialCategoryController extends Controller
{
    public function __construct(
        protected MaterialCategoryService $materialCategoryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MaterialCategory::class);

        return MaterialCategoryResource::collection(
            $this->materialCategoryService->indexQueryBuilder()->paginate(25)
        );
    }

    public function store(StoreMaterialCategoryRequest $request): MaterialCategoryResource
    {
        $materialCategory = $this->materialCategoryService->create($request->toDto());

        return new MaterialCategoryResource($materialCategory);
    }

    public function show(MaterialCategory $materialCategory): MaterialCategoryResource
    {
        $this->authorize('view', $materialCategory);

        return new MaterialCategoryResource($materialCategory->loadCount('materials'));
    }

    public function update(
        UpdateMaterialCategoryRequest $request,
        MaterialCategory $materialCategory,
    ): MaterialCategoryResource {
        $updatedMaterialCategory = $this->materialCategoryService->update(
            $materialCategory,
            $request->toDto(),
        );

        return new MaterialCategoryResource($updatedMaterialCategory);
    }

    public function destroy(MaterialCategory $materialCategory): JsonResponse
    {
        $this->authorize('delete', $materialCategory);
        $this->materialCategoryService->delete($materialCategory);

        return response()->json(null, HttpStatus::HTTP_NO_CONTENT);
    }
}
