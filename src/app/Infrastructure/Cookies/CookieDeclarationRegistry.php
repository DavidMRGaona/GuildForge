<?php

declare(strict_types=1);

namespace App\Infrastructure\Cookies;

use App\Application\Cookies\DTOs\DeclaredCookieDTO;
use App\Application\Cookies\Services\CookieDeclarationRegistryInterface;

final class CookieDeclarationRegistry implements CookieDeclarationRegistryInterface
{
    /**
     * @var array<string, DeclaredCookieDTO>
     */
    private array $cookies = [];

    public function declare(DeclaredCookieDTO $cookie): void
    {
        $this->cookies[$cookie->name] = $cookie;
    }

    public function all(): array
    {
        return array_values($this->cookies);
    }
}
