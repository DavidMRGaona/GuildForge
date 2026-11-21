<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Domain\Activity\ActivityIdentifierRule;
use InvalidArgumentException;

/**
 * An attribute a source's facts carry (core#774), e.g. `game_system`, with its label, already
 * translated. Listed by OffersActivityAttributes so a rule builder can offer it.
 */
final readonly class ActivityAttributeDTO
{
    public function __construct(
        public string $key,
        public string $label,
    ) {
        if (! ActivityIdentifierRule::isAttributeKey($key)) {
            throw new InvalidArgumentException('An activity attribute key must be snake_case.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException("Activity attribute '{$key}' needs a label.");
        }
    }
}
