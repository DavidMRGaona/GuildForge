<?php

declare(strict_types=1);

namespace App\Domain\Impersonation\Enums;

/**
 * Why an impersonation ended, as the log records it. An entry without one (and without
 * ended_at) is an impersonation whose session expired before it was ended.
 */
enum ImpersonationEndReason: string
{
    /** «Volver a mi cuenta» */
    case Stopped = 'stopped';

    /** The administrator signed out while impersonating */
    case Logout = 'logout';

    /** A request found it no longer allowed (or could not tell) and went back to the administrator */
    case Revoked = 'revoked';
}
