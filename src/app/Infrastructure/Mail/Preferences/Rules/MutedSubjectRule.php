<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences\Rules;

use App\Application\Mail\Contracts\MailDeliveryRuleInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;

/**
 * Holds back an optional mail about a thing the person muted, whatever its category. A mail
 * about nothing in particular is never held here.
 */
final readonly class MutedSubjectRule implements MailDeliveryRuleInterface
{
    public function __construct(
        private MailSubjectMuteServiceInterface $mutes,
    ) {}

    public function allows(object $user, MailCategoryDTO $category, ?MailSubjectDTO $subject = null): bool
    {
        return ! $subject instanceof MailSubjectDTO || ! $this->mutes->isMuted($user, $subject);
    }
}
