<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\ValueObjects\ModuleRequirements;
use PHPUnit\Framework\TestCase;

final class ModuleRequirementsTest extends TestCase
{
    public function test_it_creates_module_requirements_from_constructor(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.2',
            laravelVersion: '^11.0',
            requiredModules: ['core', 'auth'],
            requiredExtensions: ['gd', 'mbstring'],
            coreVersion: '^2.6',
            filamentVersion: '^3.3',
        );

        $this->assertSame('>=8.2', $requirements->phpVersion());
        $this->assertSame('^11.0', $requirements->laravelVersion());
        $this->assertSame(['core', 'auth'], $requirements->requiredModules());
        $this->assertSame(['gd', 'mbstring'], $requirements->requiredExtensions());
        $this->assertSame('^2.6', $requirements->coreVersion());
        $this->assertSame('^3.3', $requirements->filamentVersion());
    }

    public function test_it_reads_the_database_format(): void
    {
        $requirements = ModuleRequirements::fromArray([
            'php_version' => '>=8.2',
            'laravel_version' => '>=12.0',
            'core_version' => '^2.6',
            'filament_version' => '^3.3',
            'required_modules' => ['auth'],
            'required_extensions' => ['intl'],
        ]);

        $this->assertSame('^2.6', $requirements->coreVersion());
        $this->assertSame('^3.3', $requirements->filamentVersion());
        $this->assertSame(['auth'], $requirements->requiredModules());
    }

    public function test_rows_saved_before_core_existed_have_no_core_constraint(): void
    {
        $requirements = ModuleRequirements::fromArray([
            'php_version' => null,
            'laravel_version' => '>=12.0',
            'required_modules' => [],
            'required_extensions' => [],
        ]);

        $this->assertNull($requirements->phpVersion());
        $this->assertNull($requirements->coreVersion());
    }

    public function test_it_reads_the_manifest_format(): void
    {
        $requirements = ModuleRequirements::fromManifest([
            'core' => '^2.6',
            'filament' => '^3.3',
            'php' => '>=8.2',
            'laravel' => '>=12.0',
            'modules' => ['base-module:^1.0'],
            'extensions' => ['intl'],
        ]);

        $this->assertSame('^2.6', $requirements->coreVersion());
        $this->assertSame('^3.3', $requirements->filamentVersion());
        $this->assertSame('>=8.2', $requirements->phpVersion());
        $this->assertSame('>=12.0', $requirements->laravelVersion());
        $this->assertSame(['base-module:^1.0'], $requirements->requiredModules());
        $this->assertSame(['intl'], $requirements->requiredExtensions());
    }

    public function test_a_manifest_value_that_is_not_a_string_becomes_an_invalid_constraint(): void
    {
        $requirements = ModuleRequirements::fromManifest(['core' => 3, 'filament' => ['^3.3'], 'modules' => ['ok', 7]]);

        $this->assertSame('', $requirements->coreVersion());
        $this->assertSame('', $requirements->filamentVersion());
        $this->assertSame(['ok'], $requirements->requiredModules());
    }

    public function test_core_defaults_to_any_2_x_when_not_declared(): void
    {
        $this->assertSame('^2.0', ModuleRequirements::fromManifest([])->effectiveCoreConstraint());
        $this->assertSame('^2.6', ModuleRequirements::fromManifest(['core' => '^2.6'])->effectiveCoreConstraint());
        $this->assertSame(ModuleRequirements::DEFAULT_CORE_CONSTRAINT, ModuleRequirements::fromArray([])->effectiveCoreConstraint());
    }

    public function test_to_array_round_trips_and_includes_core_and_filament(): void
    {
        $requirements = ModuleRequirements::fromManifest(['core' => '^2.6', 'filament' => '^3.3', 'php' => '>=8.2']);

        $this->assertSame([
            'php_version' => '>=8.2',
            'laravel_version' => null,
            'core_version' => '^2.6',
            'filament_version' => '^3.3',
            'required_modules' => [],
            'required_extensions' => [],
        ], $requirements->toArray());
        $this->assertTrue(ModuleRequirements::fromArray($requirements->toArray())->equals($requirements));
    }

    public function test_equals_detects_any_difference(): void
    {
        $base = ModuleRequirements::fromManifest(['php' => '>=8.2']);

        $this->assertTrue($base->equals(ModuleRequirements::fromManifest(['php' => '>=8.2'])));
        $this->assertFalse($base->equals(ModuleRequirements::fromManifest(['php' => '>=8.2', 'core' => '^2.6'])));
        $this->assertFalse($base->equals(ModuleRequirements::fromManifest(['php' => '>=8.2', 'extensions' => ['intl']])));
    }
}
