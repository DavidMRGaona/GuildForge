<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

/**
 * Whether someone may propose a type of content right now, and why not.
 */
final readonly class ProposalEligibilityDTO
{
    public const string DISABLED = 'disabled';

    public const string AUTHENTICATION_REQUIRED = 'authentication_required';

    public const string ACCOUNT_INACTIVE = 'account_inactive';

    public const string EMAIL_UNVERIFIED = 'email_unverified';

    public const string NO_ACCESS_LEVEL = 'no_access_level';

    public const string NO_ROLES_CONFIGURED = 'no_roles_configured';

    public const string ROLE_NOT_ALLOWED = 'role_not_allowed';

    public const string PERMISSION_DENIED = 'permission_denied';

    private function __construct(
        public bool $allowed,
        public ?string $reason,
    ) {}

    public static function allowed(): self
    {
        return new self(true, null);
    }

    public static function denied(string $reason): self
    {
        return new self(false, $reason);
    }
}
