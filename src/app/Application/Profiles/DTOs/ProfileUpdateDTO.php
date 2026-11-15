<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileLayoutEntry;

/**
 * What the owner saves from the editor of their public profile (core#743), already validated: who may
 * see it, its address, the «Sobre mí» text, the membership badge and the order and switches of the
 * sections and stats offered now.
 */
final readonly class ProfileUpdateDTO
{
    /**
     * @param  string  $handle  the address wanted; the current one keeps it as it is
     * @param  list<ProfileLayoutEntry>  $sections  in the owner's order
     * @param  list<ProfileLayoutEntry>  $stats  in the owner's order
     * @param  list<string>  $offeredSections  the keys of the sections the editor offered
     * @param  list<string>  $offeredStats  the keys of the stats the editor offered
     */
    public function __construct(
        public string $handle,
        public ProfileVisibility $visibility,
        public ?string $bio,
        public bool $showMembership,
        public array $sections,
        public array $stats,
        public array $offeredSections,
        public array $offeredStats,
    ) {}
}
