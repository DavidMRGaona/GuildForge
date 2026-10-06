<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Services\ConstraintMatcher;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\CompatibilityResult;
use App\Domain\Modules\ValueObjects\HostEnvironment;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;

/**
 * The only place that decides whether a module's requirements fit this host.
 * Pure: no I/O, no container, no clock. Module-to-module dependencies stay in enable().
 */
final readonly class ModuleCompatibilityChecker
{
    public function __construct(
        private ConstraintMatcher $matcher,
    ) {
    }

    public function check(ModuleRequirements $requirements, HostEnvironment $host): CompatibilityResult
    {
        $issues = [];

        $this->checkConstraint($issues, RequirementType::Core, $requirements->effectiveCoreConstraint(), $host->core);

        $php = $requirements->phpVersion();
        if ($php !== null) {
            $this->checkConstraint($issues, RequirementType::Php, $php, $host->php);
        }

        $laravel = $requirements->laravelVersion();
        if ($laravel !== null) {
            $this->checkConstraint($issues, RequirementType::Laravel, $laravel, $host->laravel);
        }

        $filament = $requirements->filamentVersion();
        if ($filament !== null) {
            if ($host->filament === null) {
                $issues[] = new CompatibilityIssue(RequirementType::Filament, $filament, null, CompatibilityIssue::UNKNOWN_HOST_VERSION);
            } else {
                $this->checkConstraint($issues, RequirementType::Filament, $filament, $host->filament);
            }
        }

        foreach ($requirements->requiredExtensions() as $extension) {
            if (! $host->hasExtension($extension)) {
                $issues[] = new CompatibilityIssue(RequirementType::Extension, $extension, null, CompatibilityIssue::MISSING_EXTENSION);
            }
        }

        return new CompatibilityResult($issues);
    }

    public function checkManifest(ModuleManifestDTO $manifest, HostEnvironment $host): CompatibilityResult
    {
        return $this->check(ModuleRequirements::fromManifest($manifest->requires ?? []), $host);
    }

    /**
     * @param  list<CompatibilityIssue>  $issues
     */
    private function checkConstraint(array &$issues, RequirementType $type, string $constraint, ModuleVersion $found): void
    {
        if (! $this->matcher->isValid($constraint)) {
            $issues[] = new CompatibilityIssue($type, $constraint, $found->value(), CompatibilityIssue::INVALID_CONSTRAINT);

            return;
        }

        if (! $this->matcher->matches($constraint, $found)) {
            $issues[] = new CompatibilityIssue($type, $constraint, $found->value(), CompatibilityIssue::UNSATISFIED);
        }
    }
}
