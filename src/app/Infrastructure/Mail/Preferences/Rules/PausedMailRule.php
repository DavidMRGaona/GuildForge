<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences\Rules;

use App\Application\Mail\Contracts\MailDeliveryRuleInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Domain\Mail\ValueObjects\MailPause;

/**
 * Holds back every optional mail while the person has paused them. The category choices are
 * not read or changed here, so they are as they were when the pause ends.
 */
final readonly class PausedMailRule implements MailDeliveryRuleInterface
{
    public function __construct(
        private MailPauseServiceInterface $pauses,
    ) {}

    public function allows(object $user, MailCategoryDTO $category, ?MailSubjectDTO $subject = null): bool
    {
        return ! $this->pauses->current($user) instanceof MailPause;
    }
}
