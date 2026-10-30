<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use InvalidArgumentException;

/**
 * Who took part in a survey subject: accounts by user id, plus guests without an account.
 */
final readonly class SurveyAudienceDTO
{
    /** @var list<string> */
    public array $userIds;

    /** @var list<SurveyGuestDTO> */
    public array $guests;

    /**
     * A user listed more than once (e.g. as player and organizer) counts once.
     *
     * @param  list<string>  $userIds
     * @param  list<SurveyGuestDTO>  $guests
     */
    public function __construct(array $userIds = [], array $guests = [])
    {
        $references = [];

        foreach ($guests as $guest) {
            if (isset($references[$guest->reference])) {
                throw new InvalidArgumentException("Survey guest reference '{$guest->reference}' is used twice.");
            }

            $references[$guest->reference] = true;
        }

        $this->userIds = array_values(array_unique($userIds));
        $this->guests = $guests;
    }

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Number of people the survey goes to, the base of the response rate.
     */
    public function count(): int
    {
        return count($this->userIds) + count($this->guests);
    }
}
