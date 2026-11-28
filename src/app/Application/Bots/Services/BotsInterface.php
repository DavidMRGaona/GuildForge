<?php

declare(strict_types=1);

namespace App\Application\Bots\Services;

use App\Application\Bots\DTOs\BotDTO;
use App\Domain\Bots\Exceptions\UnknownBotException;

/**
 * Bot accounts (core#863): accounts that are not people. Modules ask for the account of a bot they
 * registered, and recognise and describe bot accounts they meet in their data.
 */
interface BotsInterface
{
    /**
     * The id (lower case UUID) of the bot's account, created the first time it is asked for.
     *
     * @throws UnknownBotException when no enabled module registers the key
     */
    public function id(string $key): string;

    /** Whether the account is a bot, even one whose module is now disabled */
    public function isBot(?string $accountId): bool;

    /**
     * Only the ids that are bots, keyed by id in lower case. Ids of people, unknown or malformed are left out.
     *
     * @param  list<string>  $ids
     * @return array<string, BotDTO>
     */
    public function describe(array $ids): array;
}
