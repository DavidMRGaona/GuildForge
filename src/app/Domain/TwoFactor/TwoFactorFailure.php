<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor;

/**
 * Why two-factor authentication refused or could not do something. The value travels with
 * TwoFactorException, so screens can tell people what to do without parsing messages.
 */
enum TwoFactorFailure: string
{
    /** No account with that id */
    case UnknownAccount = 'unknown_account';

    /** Two-factor authentication is already on: a confirmed secret is never replaced by a new setup */
    case AlreadyEnabled = 'already_enabled';

    /** Nothing is being set up: confirming needs beginSetup() first */
    case NoPendingSetup = 'no_pending_setup';

    /** Two-factor authentication is off, so there is nothing to regenerate or turn off */
    case NotEnabled = 'not_enabled';

    /** The code is wrong, outside the window or already used */
    case InvalidCode = 'invalid_code';

    /** An administrator is impersonating the account: no secret or mail is made for it */
    case Impersonating = 'impersonating';

    /** Something it relies on failed (computing a code, drawing the QR code, reading the seal); try again later */
    case Unavailable = 'unavailable';
}
