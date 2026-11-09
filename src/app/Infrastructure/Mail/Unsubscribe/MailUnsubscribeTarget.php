<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Unsubscribe;

use App\Application\Mail\DTOs\MailPreferenceDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * The account and the category an unsubscribe link points at, with the person's current choice.
 */
final readonly class MailUnsubscribeTarget
{
    public function __construct(
        public UserModel $user,
        public MailPreferenceDTO $preference,
    ) {}
}
