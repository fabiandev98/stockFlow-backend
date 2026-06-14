<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        return SupplierResource::collection(
            $this->supplierService->indexQueryBuilder()->paginate(25)
        );
    }

    public function store(StoreSupplierRequest $request): SupplierResource
    {
        $supplier = $this->supplierService->create($request->toDto());

        return new SupplierResource($supplier);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);

        return new SupplierResource($supplier->loadCount('purchases'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $updatedSupplier = $this->supplierService->update($supplier, $request->toDto());

        return new SupplierResource($updatedSupplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);
        $this->supplierService->delete($supplier);

        return response()->json(null, HttpStatus::HTTP_NO_CONTENT);
    }
}
