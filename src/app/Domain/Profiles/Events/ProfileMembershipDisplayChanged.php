<?php

declare(strict_types=1);

namespace App\Domain\Profiles\Events;

/**
 * What ProfileMembershipDisplayInterface answers for an account may have changed (core#824): the badge,
 * the visibility or the public name changed, the account was anonymized, deactivated or restored, it gained
 * or lost the association member role, or the association member role setting changed.
 *
 * Dispatched after the commit, once per account and transaction, and never while impersonating. It says
 * nothing about the new answer, and it may arrive when the answer did not change: whoever depends on it asks
 * the reader again. Carries the account id only.
 */
final readonly class ProfileMembershipDisplayChanged
{
    public function __construct(
        public string $userId,
    ) {}
}
