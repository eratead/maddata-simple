<?php

namespace App\View\Components;

use App\Enums\HealthStatus;
use App\Services\Health\SystemHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

/**
 * The header status pill (spec: docs/specs/health-monitor-phases-3-4.md §4).
 *
 * This renders into layouts/app.blade.php, which EVERY authenticated page uses,
 * including client-facing ones. Two consequences, both deliberate:
 *
 *  - The admin gate is shouldRender(), not an @if in the layout, so no page can
 *    render it unguarded by accident. Non-admins get no markup at all — not a
 *    hidden element, which would ship system status into their DOM.
 *  - It reads pillStatus(), which is cache-only and never throws. Rendering a
 *    pill must never rebuild a snapshot, and must never be the reason an
 *    unrelated page 500s.
 */
final class HealthPill extends Component
{
    public function __construct(private readonly SystemHealthService $health) {}

    /** Same predicate as EnsureUserIsAdmin, so page access and pill visibility cannot disagree. */
    public function shouldRender(): bool
    {
        return auth()->user()?->hasPermission('is_admin') === true;
    }

    public function render(): View
    {
        try {
            $status = $this->health->pillStatus();
        } catch (Throwable $e) {
            // pillStatus() is contractually never-throwing, and this class is
            // still entitled not to trust it. Everything else in the monitor
            // fails one panel; this one would fail every page in the app. Same
            // belt-and-suspenders as SystemHealthService::runCheck().
            report($e);

            $status = HealthStatus::STALE;
        }

        return view('components.health-pill', ['status' => $status]);
    }
}
