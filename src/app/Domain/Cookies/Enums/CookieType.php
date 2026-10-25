<?php

declare(strict_types=1);

namespace App\Domain\Cookies\Enums;

/**
 * Who sets a declared cookie: the site itself or a third party.
 */
enum CookieType: string
{
    case FirstParty = 'first_party';
    case ThirdParty = 'third_party';
}
