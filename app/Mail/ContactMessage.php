<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
    }

    public function envelope(): Envelope
    {
        $kind = $this->payload['kind'] ?? 'outro';
        $kindLabel = config("site.contact_kinds.$kind.pt", $kind);

        return new Envelope(
            subject: "[{$kindLabel}] " . ($this->payload['name'] ?? 'sem nome'),
            replyTo: [$this->payload['email']],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
            with: ['payload' => $this->payload],
        );
    }
}
