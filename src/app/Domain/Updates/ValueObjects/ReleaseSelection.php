<?php

declare(strict_types=1);

namespace App\Domain\Updates\ValueObjects;

use App\Domain\Modules\ValueObjects\CompatibilityIssue;

/**
 * Outcome of choosing a module release: the highest compatible one, and the highest
 * incompatible one newer than it (shown to the admin, never applied).
 */
final readonly class ReleaseSelection
{
    /**
     * @param  list<CompatibilityIssue>  $blockedIssues
     */
    public function __construct(
        public ?GitHubReleaseInfo $compatible,
        public ?string $compatibleCoreConstraint,
        public ?GitHubReleaseInfo $blocked,
        public ?string $blockedCoreConstraint,
        public array $blockedIssues,
    ) {
    }

    public static function none(): self
    {
        return new self(null, null, null, null, []);
    }
}
