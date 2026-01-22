<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Exceptions\ModuleCircularDependencyException;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleDependencyResolver;
use PHPUnit\Framework\TestCase;

final class ModuleDependencyResolverTest extends TestCase
{
    private ModuleDependencyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ModuleDependencyResolver();
    }

    public function test_it_returns_satisfied_when_no_dependencies(): void
    {
        // Arrange
        $module = $this->createModule(
            name: 'simple-module',
            version: '1.0.0',
            requiredModules: []
        );

        $availableModules = [];

        // Act
        $result = $this->resolver->areDependenciesSatisfied($module, $availableModules);

        // Assert
        $this->assertTrue($result);
    }

    public function test_it_returns_not_satisfied_when_dependency_missing(): void
    {
        // Arrange
        $module = $this->createModule(
            name: 'dependent-module',
            version: '1.0.0',
            requiredModules: ['base-module']
        );

        $availableModules = [];

        // Act
        $result = $this->resolver->areDependenciesSatisfied($module, $availableModules);

        // Assert
        $this->assertFalse($result);
    }

    public function test_it_returns_not_satisfied_when_version_mismatch(): void
    {
        // Arrange
        $module = $this->createModule(
            name: 'dependent-module',
            version: '1.0.0',
            requiredModules: ['base-module:^2.0']
        );

        $baseModule = $this->createModule(
            name: 'base-module',
            version: '1.5.0',
            requiredModules: []
        );

        $availableModules = [$baseModule];

        // Act
        $result = $this->resolver->areDependenciesSatisfied($module, $availableModules);

        // Assert
        $this->assertFalse($result);
    }

    public function test_it_detects_circular_dependencies(): void
    {
        // Arrange
        $moduleA = $this->createModule(
            name: 'module-a',
            version: '1.0.0',
            requiredModules: ['module-b']
        );

        $moduleB = $this->createModule(
            name: 'module-b',
            version: '1.0.0',
            requiredModules: ['module-c']
        );

        $moduleC = $this->createModule(
            name: 'module-c',
            version: '1.0.0',
            requiredModules: ['module-a']
        );

        $modules = [$moduleA, $moduleB, $moduleC];

        // Assert
        $this->expectException(ModuleCircularDependencyException::class);

        // Act
        $this->resolver->detectCircularDependencies($modules);
    }

    public function test_it_sorts_modules_by_dependencies(): void
    {
        // Arrange
        $moduleC = $this->createModule(
            name: 'module-c',
            version: '1.0.0',
            requiredModules: ['module-a', 'module-b']
        );

        $moduleB = $this->createModule(
            name: 'module-b',
            version: '1.0.0',
            requiredModules: ['module-a']
        );

        $moduleA = $this->createModule(
            name: 'module-a',
            version: '1.0.0',
            requiredModules: []
        );

        $modules = [$moduleC, $moduleB, $moduleA]; // Unsorted

        // Act
        $sorted = $this->resolver->sortByDependencies($modules);

        // Assert
        $this->assertCount(3, $sorted);
        $this->assertEquals('module-a', $sorted[0]->name()->value);
        $this->assertEquals('module-b', $sorted[1]->name()->value);
        $this->assertEquals('module-c', $sorted[2]->name()->value);
    }

    public function test_it_gets_dependents_of_module(): void
    {
        // Arrange
        $baseModule = $this->createModule(
            name: 'base-module',
            version: '1.0.0',
            requiredModules: []
        );

        $moduleA = $this->createModule(
            name: 'module-a',
            version: '1.0.0',
            requiredModules: ['base-module']
        );

        $moduleB = $this->createModule(
            name: 'module-b',
            version: '1.0.0',
            requiredModules: ['base-module']
        );

        $moduleC = $this->createModule(
            name: 'module-c',
            version: '1.0.0',
            requiredModules: ['module-a']
        );

        $modules = [$baseModule, $moduleA, $moduleB, $moduleC];

        // Act
        $dependents = $this->resolver->getDependents($baseModule, $modules);

        // Assert
        $this->assertCount(2, $dependents);
        $dependentNames = array_map(fn (Module $m) => $m->name()->value, $dependents);
        $this->assertContains('module-a', $dependentNames);
        $this->assertContains('module-b', $dependentNames);
        $this->assertNotContains('module-c', $dependentNames);
    }

    public function test_it_validates_system_requirements(): void
    {
        // Arrange
        $module = $this->createModule(
            name: 'system-module',
            version: '1.0.0',
            requiredModules: [],
            phpVersion: '>=8.2',
            laravelVersion: '^11.0'
        );

        $phpVersion = '8.3.0';
        $laravelVersion = '11.5.0';
        $availableExtensions = ['json', 'mbstring', 'pdo'];

        // Act
        $result = $this->resolver->validateSystemRequirements(
            $module,
            $phpVersion,
            $laravelVersion,
            $availableExtensions
        );

        // Assert
        $this->assertTrue($result);
    }

    private function createModule(
        string $name,
        string $version,
        array $requiredModules = [],
        ?string $phpVersion = null,
        ?string $laravelVersion = null,
        array $requiredExtensions = []
    ): Module {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name),
            displayName: ucfirst($name),
            description: "Test module: {$name}",
            version: ModuleVersion::fromString($version),
            author: 'Test Author',
            requirements: new ModuleRequirements(
                phpVersion: $phpVersion,
                laravelVersion: $laravelVersion,
                requiredModules: $requiredModules,
                requiredExtensions: $requiredExtensions
            ),
            status: ModuleStatus::Disabled
        );
    }
}
