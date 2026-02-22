<?php

namespace App\Observers;

use App\Models\Creative;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CreativeObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Creative "created" event.
     */
    protected $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function created(Creative $creative): void
    {
        $this->logger->log('created', $creative, 'Created creative "' . $creative->name . '"');
    }

    public function updated(Creative $creative): void
    {
        $changes = $creative->getChanges();
        
        // Remove updated_at from changes to avoid noise
        unset($changes['updated_at']);

        if (!empty($changes)) {
            $description = 'Updated creative "' . $creative->name . '"';
            $changeDetails = [];
            foreach ($changes as $key => $value) {
                $changeDetails[] = $key . ': "' . $value . '"';
            }
            if (!empty($changeDetails)) {
                $description .= ': ' . implode(', ', $changeDetails);
            }
            
            $this->logger->log('updated', $creative, $description, $changes);
        }
    }

    public function deleted(Creative $creative): void
    {
        $this->logger->log('deleted', $creative, 'Deleted creative "' . $creative->name . '"');
    }
}
