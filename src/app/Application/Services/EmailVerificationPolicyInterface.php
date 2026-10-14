<?php

declare(strict_types=1);

namespace App\Application\Services;

/**
 * The single rule deciding whether an account must verify its email before using the
 * authenticated part of the site (EnsureEmailIsVerifiedWhenRequired applies it).
 *
 * The "email verification required" setting is only enforced for accounts created from
 * `email_verification_enforced_since` on (recorded by a migration when 3.8.0 was deployed):
 * accounts that never verified before then keep working as they did.
 */
interface EmailVerificationPolicyInterface
{
    /**
     * True when all of these hold:
     * - the "email verification required" setting is on;
     * - the account has not verified its email;
     * - the account is not anonymized;
     * - the account was created at or after the cutoff.
     *
     * Fails closed: without a readable cutoff, without a creation date, or for an object
     * that is not a known user, every unverified account must verify while the setting is on.
     */
    public function mustVerify(object $user): bool;
}
