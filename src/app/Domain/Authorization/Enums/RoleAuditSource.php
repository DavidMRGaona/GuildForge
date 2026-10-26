<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

/**
 * Where a change recorded in the role history was made: the admin panel, the public site, an
 * artisan command, a queued job, or none of them.
 */
enum RoleAuditSource: string
{
    case Panel = 'panel';
    case Web = 'web';
    case Command = 'command';
    case Queue = 'queue';
    case System = 'system';
}
