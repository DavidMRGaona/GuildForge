<?php

declare(strict_types=1);

namespace App\Application\Bots\Services;

use App\Application\Bots\Contracts\BotInterface;
use App\Application\Bots\DTOs\BotDefinitionDTO;

/**
 * The bots the enabled modules offer. Only modules register bots: the core has none of its own.
 * A class that cannot be offered (see BotInterface) is left out with a warning, never an exception.
 */
interface BotRegistryInterface
{
    /**
     * @param  class-string<BotInterface>|class-string  $botClass
     * @param  string  $module  the module that owns it
     */
    public function register(string $botClass, string $module): void;

    /**
     * @param  array<class-string<BotInterface>|class-string>  $botClasses
     */
    public function registerMany(array $botClasses, string $module): void;

    /**
     * @return list<BotDefinitionDTO>
     */
    public function all(): array;

    public function find(string $key): ?BotDefinitionDTO;
}
