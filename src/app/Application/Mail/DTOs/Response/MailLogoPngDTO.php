<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs\Response;

/**
 * The mail logo drawn as a PNG. The version names the logo it was drawn from, so a new
 * logo gets a new URL and caches can keep each one for good.
 */
final readonly class MailLogoPngDTO
{
    public function __construct(
        public string $bytes,
        public string $version,
    ) {}
}
