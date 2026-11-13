<?php

declare(strict_types=1);

namespace App\Domain\Impersonation\Enums;

/**
 * Why an account may not impersonate another one, as ImpersonationEligibilityRule decides it.
 */
enum ImpersonationRefusal: string
{
    /** The actor lacks users.impersonate (only the administrator role can hold it) */
    case NotPermitted = 'not_permitted';

    /** An impersonation is already under way: they are never chained */
    case AlreadyImpersonating = 'already_impersonating';

    case TargetIsSelf = 'target_is_self';

    case TargetIsAdmin = 'target_is_admin';

    /** Soft-deleted */
    case TargetDeactivated = 'target_deactivated';

    case TargetAnonymized = 'target_anonymized';

    /** A check could not be made (it threw): refused, never allowed */
    case CheckFailed = 'check_failed';
}
