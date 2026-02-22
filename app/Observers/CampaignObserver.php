<?php

namespace App\Observers;

use App\Models\Campaign;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CampaignObserver implements ShouldHandleEventsAfterCommit
{
    protected $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function updated(Campaign $campaign): void
    {
        if ($campaign->isDirty('creative_optimization')) {
            $newValue = $campaign->creative_optimization;
            $message = $newValue 
                ? 'Creative optimisation changed to CTR' 
                : 'Creative optimisation changed to equal weights';
            
            $this->logger->log('updated', $campaign, $message, [
                'creative_optimization' => $newValue
            ]);
        }
    }
}
