<?php

namespace App\Mail;

use App\Dtos\HealthSnapshot;
use App\Enums\HealthStatus;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Operator alert for a change in system health.
 *
 * Deliberately NOT ShouldQueue, unlike ActivityDigestMail. A queued alert would
 * sit in the queue forever precisely when the queue worker is the thing that
 * died — which is one of the failures this mail exists to report. It sends
 * synchronously from the scheduler and accepts the few seconds that costs.
 */
class HealthAlertMail extends Mailable
{
    public function __construct(
        public HealthSnapshot $snapshot,
        public string $reason,
        public bool $isRecovery = false,
        public ?int $episodeStartedAt = null,
    ) {}

    public function envelope(): Envelope
    {
        if ($this->isRecovery) {
            return new Envelope(subject: '[MadData] Recovered - all systems go');
        }

        $failing = count($this->snapshot->failing());

        // ASCII on purpose: an em dash MIME-encodes in the subject line and
        // mangles on the SMS and pager gateways alerts often get forwarded to.
        return new Envelope(subject: sprintf(
            '[MadData] %s - %d check%s failing',
            $this->snapshot->overall === HealthStatus::CRIT ? 'OUTAGE' : 'Degraded',
            $failing,
            $failing === 1 ? '' : 's',
        ));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.health_alert',
            with: [
                'snapshot' => $this->snapshot,
                'reason' => $this->reason,
                'isRecovery' => $this->isRecovery,
                'failing' => $this->snapshot->failing(),
                'episodeStartedAt' => $this->episodeStartedAt,
            ],
        );
    }
}
