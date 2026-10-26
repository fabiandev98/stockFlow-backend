<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierNoteRequest;
use App\Http\Requests\Supplier\SupplierRequest;
use App\Http\Resources\SupplierFeedbackNoteResource;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Models\SupplierFeedbackNote;
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

    public function store(SupplierRequest $request): SupplierResource
    {
        $supplier = $this->supplierService->create($request->toDto());

        return new SupplierResource($supplier);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);

        return new SupplierResource($supplier->loadCount('purchases'));
    }

    public function quality(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return response()->json($this->supplierService->qualityMetrics($supplier));
    }

    public function notes(Supplier $supplier): AnonymousResourceCollection
    {
        $this->authorize('view', $supplier);

        return SupplierFeedbackNoteResource::collection($this->supplierService->feedbackNotes($supplier));
    }

    public function storeNote(StoreSupplierNoteRequest $request, Supplier $supplier): SupplierFeedbackNoteResource
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        return new SupplierFeedbackNoteResource(
            $this->supplierService->createFeedbackNote($supplier, $request->noteData(), $user)
        );
    }

    public function destroyNote(Supplier $supplier, SupplierFeedbackNote $supplierFeedbackNote): JsonResponse
    {
        $this->authorize('update', $supplier);

        $this->supplierService->deleteFeedbackNote($supplier, $supplierFeedbackNote);

        return response()->json(null, HttpStatus::HTTP_NO_CONTENT);
    }

    public function update(SupplierRequest $request, Supplier $supplier): SupplierResource
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
