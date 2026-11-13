<?php

declare(strict_types=1);

namespace App\Domain\Impersonation\Enums;

/**
 * How an impersonation behaves, chosen when it starts and kept until it ends. Nothing is ever
 * saved in either mode: read_only also refuses submissions and panel actions with a notice,
 * simulate runs them and undoes them at the end of each request.
 *
 * Stored in the session, the log and shown in the banners: a new mode is a new case here.
 */
enum ImpersonationMode: string
{
    case ReadOnly = 'read_only';
    case Simulate = 'simulate';
}
