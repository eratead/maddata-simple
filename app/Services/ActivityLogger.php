<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
    }
}
