<?php

declare(strict_types=1);

namespace App\Application\Calendar\DTOs;

use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;

final readonly class CalendarEntryDTO
{
    /**
     * `$details` is merged verbatim into the calendar JSON. The public detail
     * panel renders these keys generically, so any source may fill them:
     * `description`, `location`, `imagePublicId`, `tags` and `meta`, a list
     * of `['icon' => string, 'label' => string, 'value' => string]` rows
     * (label and value already translated) shown under the date and place.
     * Icons known by the core: dice, user, users, monitor, clock, map-pin.
     *
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $id,
        public string $sourceType,
        public string $sourceLabel,
        public CalendarSourceColor $color,
        public string $title,
        public DateTimeImmutable $start,
        public ?DateTimeImmutable $end,
        public string $url,
        public array $details = [],
    ) {}
}
