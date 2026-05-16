<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreMonitorRequest;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonitorController extends BaseApiController
{
    /**
     * GET /api/monitors
     *
     * Lists all monitors with uptime computed in a single query
     * via the withUptime() scope.
     */
    public function index(): JsonResponse
    {
        $monitors = Monitor::query()
            ->withUptime()
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $monitors->toResourceCollection(),
        ]);
    }

    /**
     * POST /api/monitors
     *
     * Registers a new URL to monitor. Returns 201 with the
     * created resource, status = pending, uptime = null.
     */
    public function store(StoreMonitorRequest $request): JsonResponse
    {
        $monitor = Monitor::create($request->validated());

        // Reload with the uptime aggregates so the response shape is consistent
        // with the index endpoint, even though a brand-new monitor has no checks yet.
        $monitor = Monitor::query()->withUptime()->findOrFail($monitor->id);

        return response()->json([
            'data' => $monitor->toResource(),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/monitors/{id}/history
     *
     * Paginated check history for a monitor, newest first.
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $monitor = Monitor::find($id); // Route model binding wasn't used as per spec

        if (! $monitor) {
            return response()->json([
                'message' => 'Monitor not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $default = config('monitoring.pagination.history_per_page');
        $max = config('monitoring.pagination.history_max_per_page');

        $perPage = (int) $request->query('per_page', $default);
        $perPage = max(1, min($perPage, $max));

        $checks = $monitor->checks()
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $checks->toResourceCollection(),
            'meta' => [
                'current_page' => $checks->currentPage(),
                'per_page' => $checks->perPage(),
                'total' => $checks->total(),
            ],
        ]);
    }
}
