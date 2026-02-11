<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $driver,
        public readonly string $timestamp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'GuildForge - Email de prueba',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.test',
            with: [
                'driver' => $this->driver,
                'timestamp' => $this->timestamp,
            ],
        );
    }
}
