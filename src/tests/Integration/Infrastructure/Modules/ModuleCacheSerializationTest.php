<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use ArrayObject;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The enabled modules list (modules.cache.enabled) is the only object graph the
 * application caches; cache.serializable_classes must let all of it through.
 */
final class ModuleCacheSerializationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_list_of_serializable_classes_is_explicit_and_valid(): void
    {
        $classes = config('cache.serializable_classes');

        $this->assertIsArray($classes);
        $this->assertNotEmpty($classes);

        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class) || enum_exists($class), "{$class} does not exist");
        }
    }

    public function test_cached_modules_come_back_whole(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'blocked-module',
            'installed_at' => now()->subWeek(),
            'last_update_check_at' => now()->subHour(),
            'latest_blocked_version' => '2.0.0',
            'latest_blocked_reason' => [(new CompatibilityIssue(RequirementType::Core, '^3.0', '2.8.0', CompatibilityIssue::UNSATISFIED))->toArray()],
        ]);
        $modules = app(ModuleRepositoryInterface::class)->enabled()->all();
        $store = $this->serializingStore();

        $store->put('modules.discovered', $modules, 60);
        $restored = $store->get('modules.discovered');

        $this->assertEquals($modules, $restored);
        $this->assertInstanceOf(Module::class, $restored[0]);
        $this->assertInstanceOf(CompatibilityIssue::class, $restored[0]->latestBlockedIssues()[0]);
    }

    public function test_other_classes_are_not_unserialized(): void
    {
        $store = $this->serializingStore();

        $store->put('object', new ArrayObject(['a' => 1]), 60);

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $store->get('object'));
    }

    /**
     * An array store that serializes like the redis and database stores do.
     */
    private function serializingStore(): Repository
    {
        return Cache::build(['driver' => 'array', 'serialize' => true]);
    }
}
