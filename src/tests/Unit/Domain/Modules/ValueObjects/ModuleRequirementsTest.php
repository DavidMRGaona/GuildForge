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
            requiredExtensions: ['gd', 'mbstring']
        );

        $this->assertEquals('>=8.2', $requirements->phpVersion());
        $this->assertEquals('^11.0', $requirements->laravelVersion());
        $this->assertEquals(['core', 'auth'], $requirements->requiredModules());
        $this->assertEquals(['gd', 'mbstring'], $requirements->requiredExtensions());
    }

    public function test_it_creates_module_requirements_from_array(): void
    {
        $data = [
            'php_version' => '>=8.2',
            'laravel_version' => '^11.0',
            'required_modules' => ['core', 'auth'],
            'required_extensions' => ['gd', 'mbstring'],
        ];

        $requirements = ModuleRequirements::fromArray($data);

        $this->assertEquals('>=8.2', $requirements->phpVersion());
        $this->assertEquals('^11.0', $requirements->laravelVersion());
        $this->assertEquals(['core', 'auth'], $requirements->requiredModules());
        $this->assertEquals(['gd', 'mbstring'], $requirements->requiredExtensions());
    }

    public function test_are_satisfied_returns_true_when_all_requirements_met(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.2',
            laravelVersion: '^11.0',
            requiredModules: [],
            requiredExtensions: ['json']
        );

        $availableModules = [];
        $availableExtensions = ['json', 'mbstring'];

        $this->assertTrue(
            $requirements->areSatisfied(
                phpVersion: '8.3.0',
                laravelVersion: '11.5.0',
                availableModules: $availableModules,
                availableExtensions: $availableExtensions
            )
        );
    }

    public function test_are_satisfied_returns_false_when_php_version_not_met(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.3',
            laravelVersion: '^11.0',
            requiredModules: [],
            requiredExtensions: []
        );

        $this->assertFalse(
            $requirements->areSatisfied(
                phpVersion: '8.2.0',
                laravelVersion: '11.5.0',
                availableModules: [],
                availableExtensions: []
            )
        );
    }

    public function test_get_unsatisfied_returns_empty_array_when_all_satisfied(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.2',
            laravelVersion: '^11.0',
            requiredModules: [],
            requiredExtensions: ['json']
        );

        $unsatisfied = $requirements->getUnsatisfied(
            phpVersion: '8.3.0',
            laravelVersion: '11.5.0',
            availableModules: [],
            availableExtensions: ['json']
        );

        $this->assertEmpty($unsatisfied);
    }

    public function test_get_unsatisfied_returns_list_of_unsatisfied_requirements(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.3',
            laravelVersion: '^11.0',
            requiredModules: ['auth', 'forum'],
            requiredExtensions: ['gd', 'imagick']
        );

        $unsatisfied = $requirements->getUnsatisfied(
            phpVersion: '8.2.0',
            laravelVersion: '11.5.0',
            availableModules: ['auth'],
            availableExtensions: ['gd']
        );

        $this->assertContains('PHP version >=8.3 required, but 8.2.0 found', $unsatisfied);
        $this->assertContains('Required module: forum', $unsatisfied);
        $this->assertContains('Required extension: imagick', $unsatisfied);
    }

    public function test_it_handles_null_optional_requirements(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: null,
            laravelVersion: null,
            requiredModules: [],
            requiredExtensions: []
        );

        $this->assertNull($requirements->phpVersion());
        $this->assertNull($requirements->laravelVersion());
        $this->assertEquals([], $requirements->requiredModules());
        $this->assertEquals([], $requirements->requiredExtensions());
    }

    public function test_are_satisfied_returns_true_when_no_requirements(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: null,
            laravelVersion: null,
            requiredModules: [],
            requiredExtensions: []
        );

        $this->assertTrue(
            $requirements->areSatisfied(
                phpVersion: '8.2.0',
                laravelVersion: '11.0.0',
                availableModules: [],
                availableExtensions: []
            )
        );
    }
}
