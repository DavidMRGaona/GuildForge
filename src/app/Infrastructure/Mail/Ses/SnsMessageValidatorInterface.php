<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Ses;

interface SnsMessageValidatorInterface
{
    /**
     * @param  array<string, mixed>  $message
     */
    public function isValid(array $message): bool;
}
