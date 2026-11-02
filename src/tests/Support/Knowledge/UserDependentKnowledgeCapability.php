<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;

/**
 * Passes only for users whose email is on the allowed test domain.
 */
final class UserDependentKnowledgeCapability implements KnowledgeCapabilityInterface
{
    public const string ALLOWED_DOMAIN = '@allowed.test';

    public function key(): string
    {
        return 'fake.per_user';
    }

    public function passes(object $user): bool
    {
        $email = $user->email ?? null;

        return is_string($email) && str_ends_with($email, self::ALLOWED_DOMAIN);
    }
}
