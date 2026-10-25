<?php

declare(strict_types=1);

namespace App\Application\Cookies\DTOs;

use App\Domain\Cookies\Enums\CookieType;

/**
 * A cookie a module sets, declared so a cookie consent module can list it.
 */
final readonly class DeclaredCookieDTO
{
    /**
     * @param  string  $categorySlug  Slug of the consent category the cookie belongs to, e.g. 'preferences'.
     */
    public function __construct(
        public string $name,
        public string $categorySlug,
        public string $provider,
        public string $purpose,
        public ?string $duration = null,
        public CookieType $type = CookieType::FirstParty,
    ) {}
}
