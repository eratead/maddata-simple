<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Health\SystemHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * The /admin/monitor page (spec: docs/specs/health-monitor-phases-3-4.md §3-4).
 *
 * Thin on purpose. No thresholds, no formatting, no queries — those live in the
 * check classes and the service, which are the things that are tested for never
 * throwing. If this controller ever needs a conditional, it is in the wrong place.
 *
 * There is deliberately no API Resource: HealthSnapshot::toArray() is already
 * the documented JSON contract, and wrapping it would make two places to change
 * one shape.
 */
final class MonitorController extends Controller
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function index(): View
    {
        return view('admin.monitor', [
            'snapshot' => $this->health->snapshot()->toArray(),
            'kpiKeys' => (array) config('health.ui.kpi_keys', []),
            'pollSeconds' => (int) config('health.ui.poll_seconds', 30),
            'staleSeconds' => (int) config('health.ui.stale_seconds', 180),
        ]);
    }

    /** Polled every few seconds by the page. Normally a single cache read. */
    public function data(): JsonResponse
    {
        return response()->json($this->health->snapshot()->toArray());
    }

    /**
     * "Refresh now". POST rather than GET because it mutates cache state, and a
     * GET that mutates is prefetchable by a browser link preview.
     *
     * Goes through refreshOnDemand(), never refresh(), so two admins clicking at
     * once cannot stampede the probes — see the service.
     */
    public function refresh(): JsonResponse
    {
        return response()->json($this->health->refreshOnDemand()->toArray());
    }
}
