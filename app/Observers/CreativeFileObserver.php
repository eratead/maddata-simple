<?php

namespace App\Observers;

use App\Models\CreativeFile;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CreativeFileObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the CreativeFile "created" event.
     */
    protected $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function created(CreativeFile $creativeFile): void
    {
        $this->logger->log('created', $creativeFile, 'Uploaded file "' . $creativeFile->name . '"');
    }

    public function updated(CreativeFile $creativeFile): void
    {
        $changes = $creativeFile->getChanges();
        if (!empty($changes)) {
            $this->logger->log('updated', $creativeFile, 'Updated file "' . $creativeFile->name . '"', $changes);
        }
    }

    public function deleted(CreativeFile $creativeFile): void
    {
        $this->logger->log('deleted', $creativeFile, 'Deleted file "' . $creativeFile->name . '"');
    }
}
