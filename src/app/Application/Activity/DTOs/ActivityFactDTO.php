<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Domain\Activity\ActivityIdentifierRule;
use App\Domain\Activity\ActivityRole;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One person's part in one subject (core#774): who (an account UUID, kept in lower case), in what
 * role, when, with which attributes (`game_system` => its value) and, for what has a ranking,
 * their placement (1 = the winner). All the facts of one subject happened together: the players of
 * a table are its players.
 *
 * `$occurredAt` is an instant (when the subject took place or ended, as the source decides): compare
 * it as such, whatever its timezone.
 */
final readonly class ActivityFactDTO
{
    public const int MAX_ATTRIBUTES = 20;

    public string $userId;

    /**
     * @param  array<string, ActivityAttributeValueDTO>  $attributes  attribute key => value
     *
     * @throws InvalidArgumentException when the user id is not an account id, the placement is below 1
     *                                  or an attribute is malformed
     */
    public function __construct(
        string $userId,
        public ActivityRole $role,
        public DateTimeImmutable $occurredAt,
        public array $attributes = [],
        public ?int $placement = null,
    ) {
        $normalized = ActivityIdentifierRule::userId($userId);

        if ($normalized === null) {
            throw new InvalidArgumentException('An activity fact needs an account id (UUID).');
        }

        $this->userId = $normalized;

        if ($placement !== null && $placement < 1) {
            throw new InvalidArgumentException('An activity placement starts at 1 (the winner).');
        }

        if (count($attributes) > self::MAX_ATTRIBUTES) {
            throw new InvalidArgumentException(sprintf('An activity fact carries at most %d attributes.', self::MAX_ATTRIBUTES));
        }

        // A numeric key turns into an int, and no attribute key is numeric
        foreach (array_keys($attributes) as $key) {
            if (! ActivityIdentifierRule::isAttributeKey((string) $key)) {
                throw new InvalidArgumentException('Activity attribute keys must be snake_case.');
            }
        }

        if (! ActivityContractChecks::areAll($attributes, ActivityAttributeValueDTO::class)) {
            throw new InvalidArgumentException('Activity attribute values must be ActivityAttributeValueDTO.');
        }
    }
}
