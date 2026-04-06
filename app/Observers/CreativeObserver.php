<?php

namespace App\Observers;

use App\Models\Creative;
use App\Models\CreativeFile;
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

    public function deleting(Creative $creative): void
    {
        // Manually delete each file via Eloquent so that CreativeFileObserver::deleted
        // fires for every file — ensuring physical disk cleanup. The DB-level cascade on
        // creative_files.creative_id is now redundant but remains as a safety net.
        $creative->files->each(fn (CreativeFile $file) => $file->delete());
    }

    public function created(Creative $creative): void
    {
        $this->logger->log('created', $creative, 'Created creative "'.$creative->name.'"');
    }

    public function updated(Creative $creative): void
    {
        $changes = $creative->getChanges();

        // Remove updated_at from changes to avoid noise
        unset($changes['updated_at']);

        if (! empty($changes)) {
            $description = 'Updated creative "'.$creative->name.'"';
            $changeDetails = [];
            foreach ($changes as $key => $value) {
                $changeDetails[] = $key.': "'.$value.'"';
            }
            if (! empty($changeDetails)) {
                $description .= ': '.implode(', ', $changeDetails);
            }

            $this->logger->log('updated', $creative, $description, $changes);
        }
    }

    public function deleted(Creative $creative): void
    {
        $this->logger->log('deleted', $creative, 'Deleted creative "'.$creative->name.'"');
    }
}
