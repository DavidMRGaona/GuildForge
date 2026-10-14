<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

/**
 * Whether the current request (or queue job) already logged that the configured association
 * member role is a forbidden one.
 *
 * Bound as a scoped instance: Laravel drops it when a new request or job starts, so the
 * singleton AssociationMembershipService warns at most once per lifecycle.
 */
final class AssociationMemberRoleWarning
{
    public bool $logged = false;
}
