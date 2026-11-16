<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\DTOs;

/**
 * What a person needs to add the account to an authenticator app: the key to type by hand, the
 * otpauth:// URI and the same URI as a QR code. Shown during setup only, never logged or stored
 * anywhere else.
 */
final readonly class TwoFactorSetupDTO
{
    public function __construct(
        /** The secret in Base32, as authenticator apps take it when typed */
        #[\SensitiveParameter]
        public string $secret,
        /** otpauth://totp/<site>:<email>?secret=…&issuer=<site> */
        #[\SensitiveParameter]
        public string $otpauthUri,
        /** The URI as an SVG QR code (an <svg> element, no XML declaration) */
        #[\SensitiveParameter]
        public string $qrCodeSvg,
    ) {}

    /**
     * The key in groups of four, easier to type («ABCD EFGH …»).
     */
    public function groupedSecret(): string
    {
        return implode(' ', str_split($this->secret, 4));
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['secret' => '[hidden]', 'otpauthUri' => '[hidden]', 'qrCodeSvg' => '[hidden]'];
    }
}
