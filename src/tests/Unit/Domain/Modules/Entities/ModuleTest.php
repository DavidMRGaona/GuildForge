<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\Entities;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ModuleTest extends TestCase
{
    public function test_it_creates_module_with_all_properties(): void
    {
        $id = ModuleId::generate();
        $name = new ModuleName('forum');
        $displayName = 'Forum';
        $description = 'Discussion forum module';
        $version = new ModuleVersion(1, 0, 0);
        $author = 'GuildForge Team';
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.2',
            laravelVersion: '^11.0',
            requiredModules: [],
            requiredExtensions: []
        );
        $status = ModuleStatus::Enabled;
        $enabledAt = new DateTimeImmutable('2024-01-15 10:00:00');
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2024-01-10 15:30:00');

        $module = new Module(
            id: $id,
            name: $name,
            displayName: $displayName,
            description: $description,
            version: $version,
            author: $author,
            requirements: $requirements,
            status: $status,
            enabledAt: $enabledAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->assertEquals($id, $module->id());
        $this->assertEquals($name, $module->name());
        $this->assertEquals($displayName, $module->displayName());
        $this->assertEquals($description, $module->description());
        $this->assertEquals($version, $module->version());
        $this->assertEquals($author, $module->author());
        $this->assertEquals($requirements, $module->requirements());
        $this->assertEquals($status, $module->status());
        $this->assertEquals($enabledAt, $module->enabledAt());
        $this->assertEquals($createdAt, $module->createdAt());
        $this->assertEquals($updatedAt, $module->updatedAt());
    }

    public function test_enable_changes_status_to_enabled_and_sets_enabled_at(): void
    {
        $module = $this->createModule(status: ModuleStatus::Disabled);

        $module->enable();

        $this->assertEquals(ModuleStatus::Enabled, $module->status());
        $this->assertInstanceOf(DateTimeImmutable::class, $module->enabledAt());
    }

    public function test_disable_changes_status_to_disabled_and_clears_enabled_at(): void
    {
        $module = $this->createModule(
            status: ModuleStatus::Enabled,
            enabledAt: new DateTimeImmutable()
        );

        $module->disable();

        $this->assertEquals(ModuleStatus::Disabled, $module->status());
        $this->assertNull($module->enabledAt());
    }

    public function test_is_enabled_returns_true_when_status_is_enabled(): void
    {
        $module = $this->createModule(status: ModuleStatus::Enabled);

        $this->assertTrue($module->isEnabled());
    }

    public function test_is_enabled_returns_false_when_status_is_disabled(): void
    {
        $module = $this->createModule(status: ModuleStatus::Disabled);

        $this->assertFalse($module->isEnabled());
    }

    public function test_is_disabled_returns_true_when_status_is_disabled(): void
    {
        $module = $this->createModule(status: ModuleStatus::Disabled);

        $this->assertTrue($module->isDisabled());
    }

    public function test_is_disabled_returns_false_when_status_is_enabled(): void
    {
        $module = $this->createModule(status: ModuleStatus::Enabled);

        $this->assertFalse($module->isDisabled());
    }

    public function test_is_installed_returns_true_when_installed_at_is_set(): void
    {
        $module = $this->createModule(installedAt: new DateTimeImmutable());

        $this->assertTrue($module->isInstalled());
    }

    public function test_is_installed_returns_false_when_installed_at_is_null(): void
    {
        $module = $this->createModule(installedAt: null);

        $this->assertFalse($module->isInstalled());
    }

    public function test_mark_installed_sets_installed_at(): void
    {
        $module = $this->createModule(installedAt: null);

        $module->markInstalled();

        $this->assertTrue($module->isInstalled());
        $this->assertInstanceOf(DateTimeImmutable::class, $module->installedAt());
    }

    public function test_mark_uninstalled_clears_installed_at(): void
    {
        $module = $this->createModule(installedAt: new DateTimeImmutable());

        $module->markUninstalled();

        $this->assertFalse($module->isInstalled());
        $this->assertNull($module->installedAt());
    }

    public function test_requirements_satisfied_returns_true_when_all_met(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.2',
            laravelVersion: '^11.0',
            requiredModules: [],
            requiredExtensions: ['json']
        );

        $module = $this->createModule(requirements: $requirements);

        $satisfied = $module->requirementsSatisfied(
            phpVersion: '8.3.0',
            laravelVersion: '11.5.0',
            availableModules: [],
            availableExtensions: ['json', 'mbstring']
        );

        $this->assertTrue($satisfied);
    }

    public function test_requirements_satisfied_returns_false_when_not_met(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.3',
            laravelVersion: '^11.0',
            requiredModules: [],
            requiredExtensions: []
        );

        $module = $this->createModule(requirements: $requirements);

        $satisfied = $module->requirementsSatisfied(
            phpVersion: '8.2.0',
            laravelVersion: '11.5.0',
            availableModules: [],
            availableExtensions: []
        );

        $this->assertFalse($satisfied);
    }

    public function test_get_unsatisfied_requirements_returns_array_of_unmet_requirements(): void
    {
        $requirements = new ModuleRequirements(
            phpVersion: '>=8.3',
            laravelVersion: '^11.0',
            requiredModules: ['auth'],
            requiredExtensions: ['gd']
        );

        $module = $this->createModule(requirements: $requirements);

        $unsatisfied = $module->getUnsatisfiedRequirements(
            phpVersion: '8.2.0',
            laravelVersion: '11.5.0',
            availableModules: [],
            availableExtensions: []
        );

        $this->assertNotEmpty($unsatisfied);
        $this->assertContains('PHP version >=8.3 required, but 8.2.0 found', $unsatisfied);
    }

    public function test_getters_return_correct_values(): void
    {
        $id = ModuleId::generate();
        $name = new ModuleName('shop');
        $displayName = 'Shop';
        $description = 'E-commerce shop module';
        $version = new ModuleVersion(2, 1, 0);
        $author = 'Developer';

        $module = $this->createModule(
            id: $id,
            name: $name,
            displayName: $displayName,
            description: $description,
            version: $version,
            author: $author
        );

        $this->assertEquals($id, $module->id());
        $this->assertEquals($name, $module->name());
        $this->assertEquals($displayName, $module->displayName());
        $this->assertEquals($description, $module->description());
        $this->assertEquals($version, $module->version());
        $this->assertEquals($author, $module->author());
    }

    private function createModule(
        ?ModuleId $id = null,
        ?ModuleName $name = null,
        ?string $displayName = null,
        ?string $description = null,
        ?ModuleVersion $version = null,
        ?string $author = null,
        ?ModuleRequirements $requirements = null,
        ModuleStatus $status = ModuleStatus::Disabled,
        ?DateTimeImmutable $enabledAt = null,
        ?DateTimeImmutable $installedAt = null,
    ): Module {
        return new Module(
            id: $id ?? ModuleId::generate(),
            name: $name ?? new ModuleName('test-module'),
            displayName: $displayName ?? 'Test Module',
            description: $description ?? 'Test module description',
            version: $version ?? new ModuleVersion(1, 0, 0),
            author: $author ?? 'Test Author',
            requirements: $requirements ?? new ModuleRequirements(
                phpVersion: null,
                laravelVersion: null,
                requiredModules: [],
                requiredExtensions: []
            ),
            status: $status,
            enabledAt: $enabledAt,
            installedAt: $installedAt,
        );
    }
}
