<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\Response\ConnectionTestResultDTO;

interface MailTestServiceInterface
{
    /**
     * Test SMTP connection by opening a socket and performing EHLO.
     */
    public function testSmtpConnection(): ConnectionTestResultDTO;

    /**
     * Send a test email to verify the full mail pipeline.
     */
    public function sendTestEmail(string $to): ConnectionTestResultDTO;
}
