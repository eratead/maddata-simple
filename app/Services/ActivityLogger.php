<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Mail\ActivityDigestMail;
use Carbon\Carbon;

class ActivityLogger
{
    public function log(string $action, Model $model, ?string $description = null, ?array $changes = null)
    {
        $campaignId = null;

        if ($model instanceof \App\Models\Campaign) {
            $campaignId = $model->id;
        } elseif (method_exists($model, 'campaign')) {
            $campaignId = $model->campaign_id;
        } elseif (method_exists($model, 'creative')) {
             // For CreativeFile, it belongs to Creative which belongs to Campaign
             $campaignId = $model->creative->campaign_id ?? null;
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'campaign_id' => $campaignId,
            'subject_type' => get_class($model),
            'subject_id' => $model->id,
            'action' => $action,
            'description' => $description,
            'changes' => $changes,
        ]);

        $this->checkAndSendDigest();
    }

    protected function checkAndSendDigest()
    {
        $lastSent = Cache::get('last_activity_digest_sent_at');

        if (!$lastSent || Carbon::parse($lastSent)->diffInHours(now()) >= 2) {
            
            // Fetch activities since last sent or last 2 hours
            $since = $lastSent ? Carbon::parse($lastSent) : now()->subHours(2);
            $logs = ActivityLog::with(['user', 'campaign.client', 'subject'])
                ->where('created_at', '>', $since)
                ->get();
            
            if ($logs->isNotEmpty()) {
                // Get users who opted in
                $recipients = User::where('receive_activity_notifications', true)
                    ->pluck('email');
                
                if ($recipients->isNotEmpty()) {
                    Mail::to($recipients)->send(new ActivityDigestMail($logs));
                }
            }

            Cache::put('last_activity_digest_sent_at', now());
        }
    }
}
