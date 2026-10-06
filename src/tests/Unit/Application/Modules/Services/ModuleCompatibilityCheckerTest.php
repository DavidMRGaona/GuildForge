<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Application\Modules\Services\ModuleCompatibilityChecker;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Services\ConstraintMatcher;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\HostEnvironment;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use PHPUnit\Framework\TestCase;

final class ModuleCompatibilityCheckerTest extends TestCase
{
    private ModuleCompatibilityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ModuleCompatibilityChecker(new ConstraintMatcher());
    }

    public function test_a_module_with_satisfied_requirements_is_compatible(): void
    {
        $result = $this->checker->check(ModuleRequirements::fromManifest([
            'core' => '^2.6', 'filament' => '^3.3', 'php' => '>=8.2', 'laravel' => '>=12.0', 'extensions' => ['intl'],
        ]), $this->host());

        $this->assertTrue($result->isCompatible());
    }

    public function test_core_defaults_to_2_x(): void
    {
        $this->assertTrue($this->checker->check(ModuleRequirements::fromManifest([]), $this->host(core: '2.6.0'))->isCompatible());

        $issues = $this->checker->check(ModuleRequirements::fromManifest([]), $this->host(core: '3.0.0'))->issues;
        $this->assertEquals([new CompatibilityIssue(RequirementType::Core, '^2.0', '3.0.0', CompatibilityIssue::UNSATISFIED)], $issues);
    }

    public function test_each_unsatisfied_version_is_reported(): void
    {
        $issues = $this->checker->check(ModuleRequirements::fromManifest([
            'core' => '^3.0', 'php' => '>=8.5', 'laravel' => '^13.0', 'filament' => '^4.0',
        ]), $this->host())->issues;

        $this->assertEquals([
            new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED),
            new CompatibilityIssue(RequirementType::Php, '>=8.5', '8.4.26', CompatibilityIssue::UNSATISFIED),
            new CompatibilityIssue(RequirementType::Laravel, '^13.0', '12.69.3', CompatibilityIssue::UNSATISFIED),
            new CompatibilityIssue(RequirementType::Filament, '^4.0', '3.3.56', CompatibilityIssue::UNSATISFIED),
        ], $issues);
    }

    public function test_an_invalid_constraint_is_reported_with_the_host_version(): void
    {
        $issues = $this->checker->check(ModuleRequirements::fromManifest(['core' => '2.x', 'php' => '']), $this->host())->issues;

        $this->assertEquals([
            new CompatibilityIssue(RequirementType::Core, '2.x', '2.6.0', CompatibilityIssue::INVALID_CONSTRAINT),
            new CompatibilityIssue(RequirementType::Php, '', '8.4.26', CompatibilityIssue::INVALID_CONSTRAINT),
        ], $issues);
    }

    public function test_filament_is_only_checked_when_declared(): void
    {
        $this->assertTrue($this->checker->check(ModuleRequirements::fromManifest([]), $this->host(filament: null))->isCompatible());
    }

    public function test_a_declared_filament_with_unknown_host_version_is_reported(): void
    {
        $issues = $this->checker->check(ModuleRequirements::fromManifest(['filament' => '^3.3']), $this->host(filament: null))->issues;

        $this->assertEquals([new CompatibilityIssue(RequirementType::Filament, '^3.3', null, CompatibilityIssue::UNKNOWN_HOST_VERSION)], $issues);
    }

    public function test_each_missing_extension_is_reported(): void
    {
        $issues = $this->checker->check(ModuleRequirements::fromManifest(['extensions' => ['intl', 'imagick', 'ext-gmp']]), $this->host())->issues;

        $this->assertEquals([
            new CompatibilityIssue(RequirementType::Extension, 'imagick', null, CompatibilityIssue::MISSING_EXTENSION),
            new CompatibilityIssue(RequirementType::Extension, 'ext-gmp', null, CompatibilityIssue::MISSING_EXTENSION),
        ], $issues);
    }

    public function test_module_dependencies_are_not_checked_here(): void
    {
        $this->assertTrue($this->checker->check(ModuleRequirements::fromManifest(['modules' => ['missing-module']]), $this->host())->isCompatible());
    }

    public function test_check_manifest_uses_the_manifest_requires(): void
    {
        $manifest = new ModuleManifestDTO('m', '2.0.0', 'Modules\\M', 'MServiceProvider', requires: ['core' => '^3.0']);

        $this->assertFalse($this->checker->checkManifest($manifest, $this->host())->isCompatible());
        $this->assertTrue($this->checker->checkManifest(new ModuleManifestDTO('m', '1.0.0', 'Modules\\M', 'MServiceProvider'), $this->host())->isCompatible());
    }

    private function host(string $core = '2.6.0', ?string $filament = '3.3.56'): HostEnvironment
    {
        return new HostEnvironment(
            core: ModuleVersion::fromString($core),
            php: ModuleVersion::fromString('8.4.26'),
            laravel: ModuleVersion::fromString('12.69.3'),
            filament: $filament === null ? null : ModuleVersion::fromString($filament),
            extensions: ['intl', 'pdo_pgsql', 'json'],
        );
    }
}
