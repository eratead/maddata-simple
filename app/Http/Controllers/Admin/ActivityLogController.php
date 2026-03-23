<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->hasPermission('is_admin');

        $query = ActivityLog::with([
            'user',
            'campaign',
            'subject' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\CreativeFile::class => ['creative'],
                ]);
            },
        ]);

        // Non-admin: restrict to campaigns belonging to user's clients
        if (! $isAdmin) {
            $allowedCampaignIds = \App\Models\Campaign::whereIn('client_id', $user->accessibleClientIds())->pluck('id');

            $query->whereIn('campaign_id', $allowedCampaignIds);
        }

        // Check if ANY filter is applied
        $hasFilters = $request->hasAny(['action', 'user_id', 'campaign', 'date_start', 'date_end', 'search']);

        if (! $hasFilters) {
            // Default State: Show only new campaign creations
            $query->where('action', 'created')
                ->where('subject_type', 'App\Models\Campaign');
        } else {
            // Apply Filters
            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->filled('campaign')) {
                $campaignName = $request->input('campaign');
                $query->whereHas('campaign', function ($q) use ($campaignName) {
                    $q->where('name', 'like', '%'.$campaignName.'%');
                });
            }

            if ($request->filled('date_start')) {
                $query->whereDate('created_at', '>=', $request->input('date_start'));
            }

            if ($request->filled('date_end')) {
                $query->whereDate('created_at', '<=', $request->input('date_end'));
            }

            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('description', 'like', '%'.$searchTerm.'%')
                        ->orWhere('subject_type', 'like', '%'.$searchTerm.'%');
                });
            }
        }

        $logs = $query->latest()->paginate(50)->appends($request->query());

        // For the filter dropdown — admins see all users, others see only themselves
        $users = $isAdmin
            ? \App\Models\User::orderBy('name')->get()
            : collect([$user]);

        return view('admin.activity_logs.index', compact('logs', 'users', 'isAdmin'));
    }
}
