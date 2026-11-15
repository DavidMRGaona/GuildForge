<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

/**
 * Where a section of the public profile is drawn: the wide column or the narrow one beside it
 * (on phones, one column).
 */
enum ProfileRegion: string
{
    case Main = 'main';
    case Aside = 'aside';
}
