<?php

declare(strict_types=1);

namespace App\Application\Cookies\Services;

use App\Application\Cookies\DTOs\DeclaredCookieDTO;

/**
 * Where modules declare the cookies they set. Declaring needs nothing else installed; a cookie
 * consent module, when there is one, reads the list and shows the cookies to visitors.
 */
interface CookieDeclarationRegistryInterface
{
    /**
     * Declare a cookie; declaring the same name again replaces the earlier entry.
     */
    public function declare(DeclaredCookieDTO $cookie): void;

    /**
     * @return list<DeclaredCookieDTO> In declaration order.
     */
    public function all(): array;
}
