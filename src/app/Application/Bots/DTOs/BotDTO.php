<?php

declare(strict_types=1);

namespace App\Application\Bots\DTOs;

/**
 * A bot account as modules show it: the name follows the site's name unless the account has its own.
 */
final readonly class BotDTO
{
    public function __construct(
        public string $id,
        public string $key,
        public string $module,
        public string $name,
        public ?string $avatarPublicId,
    ) {}
}
