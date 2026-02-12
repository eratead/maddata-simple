<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActivityDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $logs;

    /**
     * Create a new message instance.
     */
    public function __construct($logs)
    {
        $this->logs = $logs;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Activity on MadData',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Group logs by client name, then by campaign name
        $groupedLogs = $this->logs->groupBy(function ($log) {
            return $log->campaign && $log->campaign->client ? $log->campaign->client->name : 'Unknown Client';
        })->map(function ($clientLogs) {
            return $clientLogs->groupBy(function ($log) {
                return $log->campaign ? $log->campaign->name : 'Unknown Campaign';
            });
        });

        return new Content(
            view: 'emails.activity_digest',
            with: [
                'groupedLogs' => $groupedLogs,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
