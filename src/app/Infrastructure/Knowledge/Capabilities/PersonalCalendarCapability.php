<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Capabilities;

use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;

/**
 * Passes while the personal calendar exists: some enabled module tells the core
 * what people attend. Without one, the filter and the subscription are hidden.
 */
final readonly class PersonalCalendarCapability implements KnowledgeCapabilityInterface
{
    public function __construct(
        private PersonalCalendarServiceInterface $personalCalendar,
    ) {}

    public function key(): string
    {
        return 'core.personal_calendar';
    }

    public function passes(object $user): bool
    {
        return $this->personalCalendar->isAvailable();
    }
}
