<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationBlast extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $notificationTitle,
        public readonly string $notificationMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notificationTitle);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.notification-blast');
    }

    public function attachments(): array
    {
        return [];
    }
}
