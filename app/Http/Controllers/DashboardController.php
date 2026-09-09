<?php

namespace App\Http\Controllers;

use App\Enums\DenebPermission;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $request->user()?->hasPermissionTo(DenebPermission::INVENTORY_READ) || abort(403);

        $start = $request->string('start_date')->toString() ?: null;
        $end = $request->string('end_date')->toString() ?: null;
        $window = max(1, (int) $request->integer('expiration_window_days', 7));
        $current = $this->dashboardService->summary($start, $end, $window);

        if (! $request->boolean('compare')) {
            return response()->json($current);
        }

        $currentStart = Carbon::parse($current['period']['start_date']);
        $currentEnd = Carbon::parse($current['period']['end_date']);
        $days = $currentStart->diffInDays($currentEnd) + 1;

        return response()->json([
            'current' => $current,
            'previous' => $this->dashboardService->summary(
                $currentStart->copy()->subDays($days)->toDateString(),
                $currentEnd->copy()->subDays($days)->toDateString(),
                $window,
            ),
        ]);
    }
}
