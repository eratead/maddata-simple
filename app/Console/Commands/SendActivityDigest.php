<?php

namespace App\Console\Commands;

use App\Mail\ActivityDigestMail;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendActivityDigest extends Command
{
    protected $signature = 'digest:send-activity';

    protected $description = 'Email a digest of activity logs created since the previous digest run.';

    public function handle(): int
    {
        // Reuses the cache key set by the legacy in-request flow so the first
        // scheduled run after deploy continues from where the old code left off.
        $lastSent = Cache::get('last_activity_digest_sent_at');
        $since = $lastSent ? Carbon::parse($lastSent) : now()->subHours(2);

        $logs = ActivityLog::with(['user', 'campaign.client', 'subject'])
            ->where('created_at', '>', $since)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No activity since '.$since->toDateTimeString().' — nothing to send.');
            Cache::put('last_activity_digest_sent_at', now());

            return self::SUCCESS;
        }

        $recipients = User::where('receive_activity_notifications', true)
            ->where('is_active', true)
            ->pluck('email');

        if ($recipients->isEmpty()) {
            $this->warn("Found {$logs->count()} log(s) but no opted-in active users — nothing to send.");
            Cache::put('last_activity_digest_sent_at', now());

            return self::SUCCESS;
        }

        Mail::to($recipients)->send(new ActivityDigestMail($logs));

        $this->info("Sent digest covering {$logs->count()} log(s) to {$recipients->count()} recipient(s).");

        Cache::put('last_activity_digest_sent_at', now());

        return self::SUCCESS;
    }
}
