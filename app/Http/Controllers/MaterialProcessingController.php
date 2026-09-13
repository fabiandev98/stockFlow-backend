<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialProcessing\StoreMaterialProcessingRequest;
use App\Http\Resources\MaterialProcessingResource;
use App\Models\User;
use App\Services\MaterialProcessingService;
use Symfony\Component\HttpFoundation\Response;

class MaterialProcessingController extends Controller
{
    public function store(StoreMaterialProcessingRequest $request, MaterialProcessingService $service): MaterialProcessingResource
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED, __('auth.failed'));
        }

        return new MaterialProcessingResource($service->create($request->toDto(), $user));
    }
}
