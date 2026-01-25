<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\Collections;

use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ModuleCollectionTest extends TestCase
{
    public function test_can_create_empty_collection(): void
    {
        $collection = new ModuleCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
    }

    public function test_can_create_collection_with_modules(): void
    {
        $module1 = $this->createModule('module-one');
        $module2 = $this->createModule('module-two');

        $collection = new ModuleCollection($module1, $module2);

        $this->assertFalse($collection->isEmpty());
        $this->assertCount(2, $collection);
    }

    public function test_can_add_module(): void
    {
        $collection = new ModuleCollection();
        $module = $this->createModule('test-module');

        $collection->add($module);

        $this->assertTrue($collection->has(new ModuleName('test-module')));
        $this->assertCount(1, $collection);
    }

    public function test_can_get_module_by_name(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $result = $collection->get(new ModuleName('test-module'));

        $this->assertNotNull($result);
        $this->assertEquals('test-module', $result->name()->value);
    }

    public function test_get_returns_null_for_nonexistent_module(): void
    {
        $collection = new ModuleCollection();

        $result = $collection->get(new ModuleName('nonexistent'));

        $this->assertNull($result);
    }

    public function test_can_find_by_name(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $result = $collection->findByName(new ModuleName('test-module'));

        $this->assertNotNull($result);
        $this->assertEquals('test-module', $result->name()->value);
    }

    public function test_has_returns_true_for_existing_module(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $this->assertTrue($collection->has(new ModuleName('test-module')));
    }

    public function test_has_returns_false_for_nonexistent_module(): void
    {
        $collection = new ModuleCollection();

        $this->assertFalse($collection->has(new ModuleName('nonexistent')));
    }

    public function test_can_remove_module(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $collection->remove(new ModuleName('test-module'));

        $this->assertFalse($collection->has(new ModuleName('test-module')));
        $this->assertTrue($collection->isEmpty());
    }

    public function test_enabled_returns_only_enabled_modules(): void
    {
        $enabled = $this->createModule('enabled-module', ModuleStatus::Enabled);
        $disabled = $this->createModule('disabled-module', ModuleStatus::Disabled);
        $collection = new ModuleCollection($enabled, $disabled);

        $result = $collection->enabled();

        $this->assertCount(1, $result);
        $this->assertTrue($result->has(new ModuleName('enabled-module')));
        $this->assertFalse($result->has(new ModuleName('disabled-module')));
    }

    public function test_disabled_returns_only_disabled_modules(): void
    {
        $enabled = $this->createModule('enabled-module', ModuleStatus::Enabled);
        $disabled = $this->createModule('disabled-module', ModuleStatus::Disabled);
        $collection = new ModuleCollection($enabled, $disabled);

        $result = $collection->disabled();

        $this->assertCount(1, $result);
        $this->assertFalse($result->has(new ModuleName('enabled-module')));
        $this->assertTrue($result->has(new ModuleName('disabled-module')));
    }

    public function test_all_returns_all_modules_as_array(): void
    {
        $module1 = $this->createModule('module-one');
        $module2 = $this->createModule('module-two');
        $collection = new ModuleCollection($module1, $module2);

        $result = $collection->all();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_items_is_alias_for_all(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $this->assertEquals($collection->all(), $collection->items());
    }

    public function test_names_returns_module_names(): void
    {
        $module1 = $this->createModule('module-one');
        $module2 = $this->createModule('module-two');
        $collection = new ModuleCollection($module1, $module2);

        $result = $collection->names();

        $this->assertContains('module-one', $result);
        $this->assertContains('module-two', $result);
    }

    public function test_each_iterates_over_modules(): void
    {
        $module1 = $this->createModule('module-one');
        $module2 = $this->createModule('module-two');
        $collection = new ModuleCollection($module1, $module2);

        $names = [];
        $collection->each(function (Module $module) use (&$names): void {
            $names[] = $module->name()->value;
        });

        $this->assertContains('module-one', $names);
        $this->assertContains('module-two', $names);
    }

    public function test_is_iterable(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $iterations = 0;
        foreach ($collection as $name => $mod) {
            $iterations++;
            $this->assertEquals('test-module', $name);
            $this->assertInstanceOf(Module::class, $mod);
        }

        $this->assertEquals(1, $iterations);
    }

    public function test_filter_returns_matching_modules(): void
    {
        $v1 = $this->createModule('module-v1', ModuleStatus::Disabled, '1.0.0');
        $v2 = $this->createModule('module-v2', ModuleStatus::Disabled, '2.0.0');
        $collection = new ModuleCollection($v1, $v2);

        $result = $collection->filter(
            fn (Module $m): bool => $m->version()->major >= 2
        );

        $this->assertCount(1, $result);
        $this->assertTrue($result->has(new ModuleName('module-v2')));
    }

    public function test_filter_returns_empty_collection_when_no_match(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $result = $collection->filter(fn (Module $m): bool => false);

        $this->assertTrue($result->isEmpty());
    }

    public function test_map_transforms_modules(): void
    {
        $module1 = $this->createModule('module-one');
        $module2 = $this->createModule('module-two');
        $collection = new ModuleCollection($module1, $module2);

        $result = $collection->map(fn (Module $m): string => $m->name()->value);

        $this->assertContains('module-one', $result);
        $this->assertContains('module-two', $result);
    }

    public function test_first_returns_first_module(): void
    {
        $module1 = $this->createModule('module-one');
        $module2 = $this->createModule('module-two');
        $collection = new ModuleCollection($module1, $module2);

        $result = $collection->first();

        $this->assertNotNull($result);
        $this->assertInstanceOf(Module::class, $result);
    }

    public function test_first_returns_null_for_empty_collection(): void
    {
        $collection = new ModuleCollection();

        $this->assertNull($collection->first());
    }

    public function test_first_with_callback_returns_matching_module(): void
    {
        $v1 = $this->createModule('module-v1', ModuleStatus::Disabled, '1.0.0');
        $v2 = $this->createModule('module-v2', ModuleStatus::Disabled, '2.0.0');
        $collection = new ModuleCollection($v1, $v2);

        $result = $collection->first(
            fn (Module $m): bool => $m->version()->major >= 2
        );

        $this->assertNotNull($result);
        $this->assertEquals('module-v2', $result->name()->value);
    }

    public function test_first_with_callback_returns_null_when_no_match(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $result = $collection->first(fn (Module $m): bool => false);

        $this->assertNull($result);
    }

    public function test_get_enabled_is_alias_for_enabled(): void
    {
        $enabled = $this->createModule('enabled-module', ModuleStatus::Enabled);
        $disabled = $this->createModule('disabled-module', ModuleStatus::Disabled);
        $collection = new ModuleCollection($enabled, $disabled);

        $this->assertEquals(
            $collection->enabled()->names(),
            $collection->getEnabled()->names()
        );
    }

    public function test_get_disabled_is_alias_for_disabled(): void
    {
        $enabled = $this->createModule('enabled-module', ModuleStatus::Enabled);
        $disabled = $this->createModule('disabled-module', ModuleStatus::Disabled);
        $collection = new ModuleCollection($enabled, $disabled);

        $this->assertEquals(
            $collection->disabled()->names(),
            $collection->getDisabled()->names()
        );
    }

    public function test_get_by_name_with_string(): void
    {
        $module = $this->createModule('test-module');
        $collection = new ModuleCollection($module);

        $result = $collection->getByName('test-module');

        $this->assertNotNull($result);
        $this->assertEquals('test-module', $result->name()->value);
    }

    public function test_get_by_name_returns_null_for_nonexistent(): void
    {
        $collection = new ModuleCollection();

        $this->assertNull($collection->getByName('nonexistent'));
    }

    private function createModule(
        string $name,
        ModuleStatus $status = ModuleStatus::Disabled,
        string $version = '1.0.0',
    ): Module {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name),
            displayName: ucfirst($name),
            description: 'Test module',
            version: ModuleVersion::fromString($version),
            author: 'Test',
            requirements: ModuleRequirements::fromArray([]),
            status: $status,
            enabledAt: $status === ModuleStatus::Enabled ? new DateTimeImmutable() : null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
            namespace: 'Modules\\'.ucfirst($name),
            provider: ucfirst($name).'ServiceProvider',
            path: '/modules/'.$name,
            dependencies: [],
        );
    }
}
