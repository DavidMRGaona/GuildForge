<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * What giving an email and a password led to (core#770).
 */
enum LoginAttemptOutcome
{
    /** Wrong email or password: nothing happened */
    case Failed;

    /** The account is signed in */
    case SignedIn;

    /** The password is right but the account has two-factor authentication on: nobody is signed in yet */
    case TwoFactorRequired;
}
