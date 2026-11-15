<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ProfileRegion;

/**
 * A section or stat the owner may turn on or off and reorder in the editor (core#743): registered and
 * available now. Region, module and component only for sections (the editor's preview draws «Sobre mí»
 * from them before it is saved).
 */
final readonly class ProfileLayoutOptionDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public bool $enabledByDefault,
        public ?ProfileRegion $region = null,
        public ?string $module = null,
        public ?string $component = null,
    ) {}
}
