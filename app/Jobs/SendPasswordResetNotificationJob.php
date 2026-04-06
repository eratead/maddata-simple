<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatches the built-in ResetPassword notification inside a queue worker
 * so the SMTP call does not block the HTTP response.
 *
 * Using notifyNow() ensures the notification class remains
 * Illuminate\Auth\Notifications\ResetPassword, which is what the
 * PasswordBroker and test assertions expect.
 */
class SendPasswordResetNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected User $user,
        protected string $token,
    ) {}

    public function handle(): void
    {
        $this->user->notifyNow(new ResetPassword($this->token));
    }
}
