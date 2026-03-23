<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\CreativeFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CampaignChangeController extends Controller
{
    private function allowedCampaignIds(): ?array
    {
        $user = Auth::user();
        if ($user->hasPermission('is_admin')) {
            return null; // null = no restriction
        }

        return Campaign::whereIn('client_id', $user->accessibleClientIds())->pluck('id')->all();
    }

    public function index()
    {
        $allowedIds = $this->allowedCampaignIds();

        $query = Campaign::whereHas('activityLogs', function ($q) {
            $q->pending();
        })->withCount(['activityLogs' => function ($q) {
            $q->pending();
        }]);

        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds);
        }

        $campaigns = $query->get();

        return view('admin.campaign_changes.index', compact('campaigns'));
    }

    public function show(Campaign $campaign)
    {
        $allowedIds = $this->allowedCampaignIds();
        if ($allowedIds !== null && ! in_array($campaign->id, $allowedIds)) {
            abort(403);
        }
        $allLogs = $campaign->activityLogs()
            ->pending()
            ->with(['user', 'subject' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\CreativeFile::class => ['creative'],
                ]);
            }])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Filter to keep only the latest log for each (creative_id + width + height) tuple
        $logs = $allLogs->unique(function ($log) {
            $changes = $log->changes;
            if (is_array($changes)) {
                if (isset($changes['width'], $changes['height'], $changes['creative_id'])) {
                    $key = $changes['creative_id'].'_'.$changes['width'].'x'.$changes['height'];

                    return $key;
                }

                if (isset($changes['creative_optimization'])) {
                    return 'creative_optimization_change';
                }
            }

            // Fallback for logs without dimension data (e.g. old logs or non-file logs)
            return 'log_'.$log->id;
        });

        // Sort by Context Name
        $logs = $logs->sortBy(function ($log) {
            $contextName = '';
            if ($log->subject_type === \App\Models\CreativeFile::class && $log->subject && $log->subject->creative) {
                $contextName = 'Creative: '.$log->subject->creative->name;
            } elseif ($log->subject_type === \App\Models\Creative::class && $log->subject) {
                $contextName = 'Creative: '.$log->subject->name;
            } elseif ($log->subject_type === \App\Models\Campaign::class && $log->subject) {
                $contextName = 'Campaign: '.$log->subject->name;
            }

            return strtolower($contextName);
        });

        return view('admin.campaign_changes.show', compact('campaign', 'logs'));
    }

    public function download(ActivityLog $log)
    {
        $allowedIds = $this->allowedCampaignIds();
        if ($allowedIds !== null && ! in_array($log->campaign_id, $allowedIds)) {
            abort(403);
        }

        if ($log->subject_type !== CreativeFile::class || ! $log->subject) {
            return back()->with('error', 'File not found or invalid log type.');
        }

        $file = $log->subject;

        if (! Storage::disk('creatives')->exists($file->path)) {
            return back()->with('error', 'File does not exist on storage.');
        }

        return Storage::disk('creatives')->download($file->path, $file->name);
    }

    public function downloadAll(Campaign $campaign)
    {
        $allowedIds = $this->allowedCampaignIds();
        if ($allowedIds !== null && ! in_array($campaign->id, $allowedIds)) {
            abort(403);
        }

        $logs = $campaign->activityLogs()
            ->pending()
            ->where('subject_type', CreativeFile::class)
            ->with('subject')
            ->get();

        if ($logs->isEmpty()) {
            return back()->with('error', 'No files to download.');
        }

        // Limit number of files to prevent memory exhaustion
        $maxFiles = 200;
        $maxSizeBytes = 500 * 1024 * 1024; // 500MB

        $validLogs = $logs->filter(fn ($log) => $log->subject && Storage::disk('creatives')->exists($log->subject->path));

        if ($validLogs->count() > $maxFiles) {
            return back()->with('error', "Too many files ({$validLogs->count()}). Maximum is {$maxFiles} files per download.");
        }

        $totalSize = $validLogs->sum(fn ($log) => Storage::disk('creatives')->size($log->subject->path));

        if ($totalSize > $maxSizeBytes) {
            $sizeMb = round($totalSize / 1024 / 1024);

            return back()->with('error', "Total file size ({$sizeMb}MB) exceeds the 500MB limit.");
        }

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0750, true);
        }

        $zip = new ZipArchive;
        $fileName = 'campaign_'.$campaign->id.'_changes_'.\Illuminate\Support\Str::random(16).'.zip';
        $filePath = $tempDir.'/'.$fileName;

        if ($zip->open($filePath, ZipArchive::CREATE) === true) {
            foreach ($logs as $log) {
                if ($log->subject && Storage::disk('creatives')->exists($log->subject->path)) {
                    $absolutePath = Storage::disk('creatives')->path($log->subject->path);
                    $zip->addFile($absolutePath, $log->subject->name);
                }
            }
            $zip->close();
        }

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function markAsHandled(Request $request, Campaign $campaign)
    {
        $allowedIds = $this->allowedCampaignIds();
        if ($allowedIds !== null && ! in_array($campaign->id, $allowedIds)) {
            abort(403);
        }

        $validated = $request->validate([
            'log_ids' => ['nullable', 'array'],
            'log_ids.*' => ['integer'],
        ]);

        $logIds = $validated['log_ids'] ?? [];

        if (empty($logIds)) {
            // Mark all for campaign
            $campaign->activityLogs()->pending()->update(['status' => 'handled']);
        } else {
            // Mark selected — scoped to this campaign to prevent cross-campaign manipulation
            ActivityLog::where('campaign_id', $campaign->id)
                ->whereIn('id', $logIds)
                ->update(['status' => 'handled']);
        }

        return redirect()->route('admin.campaign_changes.index')->with('success', 'Changes marked as handled.');
    }
}
