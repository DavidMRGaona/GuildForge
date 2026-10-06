<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Exceptions\ModuleIncompatibleException;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\CompatibilityResult;
use App\Domain\Modules\ValueObjects\HostEnvironment;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use PHPUnit\Framework\TestCase;

final class CompatibilityIssueTest extends TestCase
{
    public function test_describe_explains_each_reason_in_english(): void
    {
        $this->assertSame('requires core ^3.0, found 2.6.0', (new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED))->describe());
        $this->assertSame('requires PHP extension intl', (new CompatibilityIssue(RequirementType::Extension, 'intl', null, CompatibilityIssue::MISSING_EXTENSION))->describe());
        $this->assertSame("invalid core constraint '2.x'", (new CompatibilityIssue(RequirementType::Core, '2.x', '2.6.0', CompatibilityIssue::INVALID_CONSTRAINT))->describe());
        $this->assertSame('requires Filament ^3.3 but its installed version is unknown', (new CompatibilityIssue(RequirementType::Filament, '^3.3', null, CompatibilityIssue::UNKNOWN_HOST_VERSION))->describe());
        $this->assertSame('module.json not found', (new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_MISSING))->describe());
        $this->assertSame('module.json is not valid', (new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_INVALID))->describe());
    }

    public function test_it_round_trips_through_arrays(): void
    {
        $issue = new CompatibilityIssue(RequirementType::Php, '>=8.5', '8.4.26', CompatibilityIssue::UNSATISFIED);

        $this->assertSame(
            ['requirement' => 'php', 'required' => '>=8.5', 'found' => '8.4.26', 'reason_key' => 'modules.compatibility.reasons.unsatisfied'],
            $issue->toArray(),
        );
        $this->assertEquals($issue, CompatibilityIssue::fromArray($issue->toArray()));
    }

    public function test_from_array_tolerates_unknown_or_missing_values(): void
    {
        $issue = CompatibilityIssue::fromArray(['requirement' => 'nope']);

        $this->assertSame(RequirementType::Manifest, $issue->requirement);
        $this->assertSame('', $issue->required);
        $this->assertNull($issue->found);
        $this->assertSame(CompatibilityIssue::MANIFEST_INVALID, $issue->reasonKey);
    }

    public function test_result_is_compatible_only_without_issues_and_summarises_them(): void
    {
        $result = new CompatibilityResult([
            new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED),
            new CompatibilityIssue(RequirementType::Extension, 'intl', null, CompatibilityIssue::MISSING_EXTENSION),
        ]);

        $this->assertTrue(CompatibilityResult::compatible()->isCompatible());
        $this->assertFalse($result->isCompatible());
        $this->assertSame('requires core ^3.0, found 2.6.0; requires PHP extension intl', $result->summary());
        $this->assertCount(2, $result->toArray());
    }

    public function test_host_environment_resolves_versions_and_extensions(): void
    {
        $host = new HostEnvironment(
            core: new ModuleVersion(2, 6, 0),
            php: new ModuleVersion(8, 4, 26),
            laravel: new ModuleVersion(12, 69, 3),
            filament: null,
            extensions: ['intl', 'pdo_pgsql'],
        );

        $this->assertSame('2.6.0', $host->versionOf(RequirementType::Core)?->value());
        $this->assertNull($host->versionOf(RequirementType::Filament));
        $this->assertNull($host->versionOf(RequirementType::Extension));
        $this->assertTrue($host->hasExtension('INTL'));
        $this->assertTrue($host->hasExtension('ext-pdo_pgsql'));
        $this->assertFalse($host->hasExtension('imagick'));
    }

    public function test_incompatible_exception_names_module_version_and_reasons(): void
    {
        $exception = ModuleIncompatibleException::forModule('announcements', '2.0.0', new CompatibilityResult([
            new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED),
        ]));

        $this->assertSame('Module "announcements" 2.0.0 is not compatible with this site: requires core ^3.0, found 2.6.0', $exception->getMessage());
        $this->assertSame('announcements', $exception->moduleName);
        $this->assertSame('2.0.0', $exception->version);
        $this->assertCount(1, $exception->issues);
        $this->assertSame('Module "x" is not compatible with this site: module.json not found', ModuleIncompatibleException::forModule('x', null, new CompatibilityResult([
            new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_MISSING),
        ]))->getMessage());
    }
}
