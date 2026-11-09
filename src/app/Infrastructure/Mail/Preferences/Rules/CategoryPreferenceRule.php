<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences\Rules;

use App\Application\Mail\Contracts\MailDeliveryRuleInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Holds back an optional mail whose category the person turned off.
 */
final readonly class CategoryPreferenceRule implements MailDeliveryRuleInterface
{
    public function __construct(
        private MailPreferenceRepositoryInterface $preferences,
    ) {}

    public function allows(object $user, MailCategoryDTO $category): bool
    {
        if (! $user instanceof UserModel) {
            return true;
        }

        return ! in_array($category->key, $this->preferences->disabledFor(UserId::fromString($user->id)), true);
    }
}
