<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileLayoutOptionDTO;

/**
 * The sections and stats the profile editor offers (core#743): registered, valid and available now, by
 * default order. Only these keys may be saved in an owner's layout. Fails closed: one that cannot be
 * built, has another key than it was registered with, or whose availability or texts throw is not
 * offered (warned once a day).
 */
interface ProfileLayoutOptionsInterface
{
    /**
     * @return list<ProfileLayoutOptionDTO>
     */
    public function sections(): array;

    /**
     * @return list<ProfileLayoutOptionDTO>
     */
    public function stats(): array;
}
