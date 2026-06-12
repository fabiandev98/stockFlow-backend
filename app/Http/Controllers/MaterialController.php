<?php

namespace App\Http\Controllers;

use App\Http\Requests\Material\StoreMaterialRequest;
use App\Http\Requests\Material\UpdateMaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Services\MaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class MaterialController extends Controller
{
    public function __construct(
        protected MaterialService $materialService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Material::class);

        return MaterialResource::collection(
            $this->materialService->indexQueryBuilder()->paginate(25)
        );
    }

    public function store(StoreMaterialRequest $request): MaterialResource
    {
        $material = $this->materialService->create($request->toDto());

        return new MaterialResource($material);
    }

    public function show(Material $material): MaterialResource
    {
        $this->authorize('view', $material);

        return new MaterialResource($material->load('category'));
    }

    public function update(UpdateMaterialRequest $request, Material $material): MaterialResource
    {
        $updatedMaterial = $this->materialService->update($material, $request->toDto());

        return new MaterialResource($updatedMaterial);
    }

    public function destroy(Material $material): JsonResponse
    {
        $this->authorize('delete', $material);
        $this->materialService->delete($material);

        return response()->json(null, HttpStatus::HTTP_NO_CONTENT);
    }
}
