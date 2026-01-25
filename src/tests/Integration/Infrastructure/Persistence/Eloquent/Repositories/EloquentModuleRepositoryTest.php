<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentModuleRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentModuleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentModuleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentModuleRepository();
    }

    public function test_it_saves_a_module(): void
    {
        $module = $this->createModule(
            name: 'test-module',
            status: ModuleStatus::Enabled,
        );

        $this->repository->save($module);

        $this->assertDatabaseHas('modules', [
            'id' => $module->id()->value,
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'author' => 'Test Author',
            'status' => ModuleStatus::Enabled->value,
        ]);
    }

    public function test_it_finds_module_by_id(): void
    {
        $module = $this->createModule(name: 'findable-module');
        $this->repository->save($module);

        $found = $this->repository->findById($module->id());

        $this->assertNotNull($found);
        $this->assertEquals($module->id()->value, $found->id()->value);
        $this->assertEquals('findable-module', $found->name()->value);
    }

    public function test_it_returns_null_when_module_not_found_by_id(): void
    {
        $result = $this->repository->findById(ModuleId::generate());

        $this->assertNull($result);
    }

    public function test_it_finds_module_by_name(): void
    {
        $module = $this->createModule(name: 'named-module');
        $this->repository->save($module);

        $found = $this->repository->findByName(new ModuleName('named-module'));

        $this->assertNotNull($found);
        $this->assertEquals('named-module', $found->name()->value);
    }

    public function test_it_returns_null_when_module_not_found_by_name(): void
    {
        $result = $this->repository->findByName(new ModuleName('non-existent'));

        $this->assertNull($result);
    }

    public function test_it_returns_all_modules(): void
    {
        $module1 = $this->createModule(name: 'module-one', status: ModuleStatus::Enabled);
        $module2 = $this->createModule(name: 'module-two', status: ModuleStatus::Disabled);
        $module3 = $this->createModule(name: 'module-three', status: ModuleStatus::Enabled);

        $this->repository->save($module1);
        $this->repository->save($module2);
        $this->repository->save($module3);

        $all = $this->repository->all();

        $this->assertInstanceOf(ModuleCollection::class, $all);
        $this->assertCount(3, $all);
    }

    public function test_it_returns_only_enabled_modules(): void
    {
        $enabledModule1 = $this->createModule(name: 'enabled-one', status: ModuleStatus::Enabled);
        $enabledModule2 = $this->createModule(name: 'enabled-two', status: ModuleStatus::Enabled);
        $disabledModule = $this->createModule(name: 'disabled-one', status: ModuleStatus::Disabled);

        $this->repository->save($enabledModule1);
        $this->repository->save($enabledModule2);
        $this->repository->save($disabledModule);

        $enabled = $this->repository->enabled();

        $this->assertInstanceOf(ModuleCollection::class, $enabled);
        $this->assertCount(2, $enabled);
        $enabled->each(function (Module $module) {
            $this->assertTrue($module->isEnabled());
        });
    }

    public function test_it_returns_only_disabled_modules(): void
    {
        $enabledModule = $this->createModule(name: 'enabled-one', status: ModuleStatus::Enabled);
        $disabledModule1 = $this->createModule(name: 'disabled-one', status: ModuleStatus::Disabled);
        $disabledModule2 = $this->createModule(name: 'disabled-two', status: ModuleStatus::Disabled);

        $this->repository->save($enabledModule);
        $this->repository->save($disabledModule1);
        $this->repository->save($disabledModule2);

        $disabled = $this->repository->disabled();

        $this->assertInstanceOf(ModuleCollection::class, $disabled);
        $this->assertCount(2, $disabled);
        $disabled->each(function (Module $module) {
            $this->assertTrue($module->isDisabled());
        });
    }

    public function test_it_checks_if_module_exists_by_name(): void
    {
        $module = $this->createModule(name: 'existing-module');
        $this->repository->save($module);

        $exists = $this->repository->exists(new ModuleName('existing-module'));
        $notExists = $this->repository->exists(new ModuleName('non-existent'));

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    public function test_it_deletes_a_module(): void
    {
        $module = $this->createModule(name: 'deletable-module');
        $this->repository->save($module);

        $this->assertDatabaseHas('modules', [
            'id' => $module->id()->value,
        ]);

        $this->repository->delete($module);

        $this->assertDatabaseMissing('modules', [
            'id' => $module->id()->value,
        ]);
    }

    public function test_it_updates_existing_module(): void
    {
        $module = $this->createModule(
            name: 'updatable-module',
            status: ModuleStatus::Disabled,
        );
        $this->repository->save($module);

        $this->assertDatabaseHas('modules', [
            'id' => $module->id()->value,
            'status' => ModuleStatus::Disabled->value,
        ]);

        $module->enable();
        $this->repository->save($module);

        $this->assertDatabaseHas('modules', [
            'id' => $module->id()->value,
            'status' => ModuleStatus::Enabled->value,
        ]);

        $this->assertDatabaseCount('modules', 1);
    }

    private function createModule(
        ?string $name = null,
        ModuleStatus $status = ModuleStatus::Disabled,
        ?DateTimeImmutable $enabledAt = null,
    ): Module {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name ?? 'test-module'),
            displayName: 'Test Module',
            description: 'Test module description',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: $status,
            enabledAt: $enabledAt,
        );
    }
}
