<?php

declare(strict_types=1);

namespace App\Application\Bots\DTOs;

use App\Application\Bots\Contracts\BotInterface;

final readonly class BotDefinitionDTO
{
    /**
     * @param  class-string<BotInterface>  $class
     */
    public function __construct(
        public string $key,
        public string $module,
        public string $label,
        public string $description,
        public ?string $name,
        public string $class,
    ) {}
}
