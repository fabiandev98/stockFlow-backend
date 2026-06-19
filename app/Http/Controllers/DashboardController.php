<?php

namespace App\Http\Controllers;

use App\Enums\DenebPermission;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $request->user()?->hasPermissionTo(DenebPermission::INVENTORY_READ) || abort(403);

        return response()->json(
            $this->dashboardService->summary(
                $request->string('start_date')->toString() ?: null,
                $request->string('end_date')->toString() ?: null,
                max(1, (int) $request->integer('expiration_window_days', 7))
            )
        );
    }
}
