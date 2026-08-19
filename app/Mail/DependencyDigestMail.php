<?php

namespace App\Mail;

use App\Dtos\HealthCheckResult;
use App\Dtos\HealthSnapshot;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The weekly report for everything that is real but is not a 2am page:
 * composer advisories, runtime EOL, OS patch backlog, patch-run freshness and
 * the two security-posture counters.
 *
 * Not ShouldQueue, for the same reason HealthAlertMail is not: this is
 * operations mail, and it should not depend on the queue worker it reports on.
 * One email a week sent inline from the scheduler is not a cost worth managing.
 */
class DependencyDigestMail extends Mailable
{
    /** @param  array<int, HealthCheckResult>  $checks  every check on the digest-owned nodes */
    public function __construct(
        public HealthSnapshot $snapshot,
        public array $checks,
    ) {}

    public function envelope(): Envelope
    {
        $failing = count(array_filter($this->checks, fn (HealthCheckResult $c) => $c->status->isFailing()));

        // ASCII, like the alert subject: these get forwarded to places that
        // mangle anything else.
        return new Envelope(subject: $failing === 0
            ? '[MadData] Dependencies - all clear'
            : sprintf('[MadData] Dependencies - %d item%s need attention', $failing, $failing === 1 ? '' : 's'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dependency_digest',
            with: [
                'snapshot' => $this->snapshot,
                'failing' => array_values(array_filter($this->checks, fn (HealthCheckResult $c) => $c->status->isFailing())),
                'passing' => array_values(array_filter($this->checks, fn (HealthCheckResult $c) => ! $c->status->isFailing())),
            ],
        );
    }
}
