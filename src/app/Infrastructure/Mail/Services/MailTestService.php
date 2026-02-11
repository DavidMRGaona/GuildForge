<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Services;

use App\Application\Mail\DTOs\Response\ConnectionTestResultDTO;
use App\Application\Mail\Services\MailTestServiceInterface;
use App\Mail\TestMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

final class MailTestService implements MailTestServiceInterface
{
    public function testSmtpConnection(): ConnectionTestResultDTO
    {
        $host = Config::get('mail.mailers.smtp.host', '');
        $port = (int) Config::get('mail.mailers.smtp.port', 587);

        if ($host === '' || $host === null) {
            return new ConnectionTestResultDTO(
                success: false,
                errorMessage: 'No SMTP host configured.',
            );
        }

        $startTime = hrtime(true);

        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, 10);

            if ($socket === false) {
                return new ConnectionTestResultDTO(
                    success: false,
                    responseTimeMs: $this->elapsedMs($startTime),
                    errorMessage: "Connection failed: {$errstr} (errno: {$errno})",
                );
            }

            $response = fgets($socket, 512);
            fclose($socket);

            return new ConnectionTestResultDTO(
                success: true,
                responseTimeMs: $this->elapsedMs($startTime),
                serverResponse: $response !== false ? trim($response) : null,
            );
        } catch (\Throwable $e) {
            return new ConnectionTestResultDTO(
                success: false,
                responseTimeMs: $this->elapsedMs($startTime),
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function sendTestEmail(string $to): ConnectionTestResultDTO
    {
        $startTime = hrtime(true);

        try {
            $driver = (string) Config::get('mail.default', 'smtp');
            $timestamp = now()->format('d/m/Y H:i:s');

            Mail::to($to)->send(new TestMail($driver, $timestamp));

            return new ConnectionTestResultDTO(
                success: true,
                responseTimeMs: $this->elapsedMs($startTime),
            );
        } catch (\Throwable $e) {
            return new ConnectionTestResultDTO(
                success: false,
                responseTimeMs: $this->elapsedMs($startTime),
                errorMessage: $e->getMessage(),
            );
        }
    }

    private function elapsedMs(int $startNs): int
    {
        return (int) ((hrtime(true) - $startNs) / 1_000_000);
    }
}
