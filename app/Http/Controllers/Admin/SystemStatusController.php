<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemStatusController extends Controller
{
    public function index()
    {
        $adminOnlyMode = (bool) Cache::get('admin_only_login', false);

        $sessions = DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.id')
            ->select(
                'sessions.id as session_id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name',
                'users.email',
                'users.is_admin',
            )
            ->whereNotNull('sessions.user_id')
            ->orderBy('sessions.last_activity', 'desc')
            ->get()
            ->map(function ($session) {
                $session->last_activity_at = \Carbon\Carbon::createFromTimestamp($session->last_activity)
                    ->timezone('Asia/Jerusalem');
                $session->browser = $this->parseBrowser($session->user_agent);

                return $session;
            });

        // Group by user to show unique users with session count
        $users = $sessions->groupBy('user_id')->map(function ($userSessions) {
            $first = $userSessions->first();

            return (object) [
                'user_id' => $first->user_id,
                'name' => $first->name,
                'email' => $first->email,
                'is_admin' => $first->is_admin,
                'session_count' => $userSessions->count(),
                'last_activity_at' => $userSessions->max('last_activity_at'),
                'ip_address' => $first->ip_address,
                'browser' => $first->browser,
            ];
        })->sortByDesc('last_activity_at')->values();

        return view('admin.system_status.index', compact('adminOnlyMode', 'users'));
    }

    public function toggleAdminOnly()
    {
        $current = (bool) Cache::get('admin_only_login', false);
        Cache::forever('admin_only_login', ! $current);

        $status = ! $current ? 'enabled' : 'disabled';

        return redirect()->route('admin.system-status.index')
            ->with('success', "Admin-only mode {$status}.");
    }

    public function terminateAll()
    {
        $adminIds = User::where('is_admin', true)->pluck('id');

        DB::table('sessions')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', $adminIds)
            ->delete();

        return redirect()->route('admin.system-status.index')
            ->with('success', 'All non-admin sessions terminated.');
    }

    public function terminateUser(User $user)
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->route('admin.system-status.index')
            ->with('success', "Sessions for {$user->name} terminated.");
    }

    private function parseBrowser(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Unknown';
        }

        if (str_contains($userAgent, 'Firefox')) {
            return 'Firefox';
        }
        if (str_contains($userAgent, 'Edg')) {
            return 'Edge';
        }
        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome';
        }
        if (str_contains($userAgent, 'Safari')) {
            return 'Safari';
        }

        return 'Other';
    }
}
