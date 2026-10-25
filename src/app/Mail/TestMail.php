<?php

declare(strict_types=1);

namespace App\Mail;

use App\Application\Mail\Services\MailBrandingServiceInterface;
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
            // The mail theme's name: it falls back to APP_NAME when the settings fail,
            // and this mail exists to diagnose the mail setup
            subject: __('mail.test.subject', ['app' => app(MailBrandingServiceInterface::class)->branding()->siteName]),
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
