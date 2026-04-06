<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued wrapper around Laravel's built-in ResetPassword notification.
 *
 * The framework's ResetPassword class does not use the Queueable trait, so it
 * cannot be dispatched to the queue on its own. This subclass adds ShouldQueue
 * and Queueable so the SMTP send happens in a worker process rather than
 * blocking the HTTP response.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
