<?php

declare(strict_types=1);

namespace App\Application\Calendar\DTOs;

final readonly class AddToCalendarLinksDTO
{
    public function __construct(
        public string $google,
        public string $outlook,
        public ?string $ics,
    ) {}

    /**
     * @return array{google: string, outlook: string, ics: string|null}
     */
    public function toArray(): array
    {
        return [
            'google' => $this->google,
            'outlook' => $this->outlook,
            'ics' => $this->ics,
        ];
    }
}
