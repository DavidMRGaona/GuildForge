<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Modules\Services\ModuleChangeWindowInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleSetFingerprint;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\CacheModuleChangeWindow;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class CacheModuleChangeWindowTest extends TestCase
{
    /** @var list<Module> */
    private array $enabled = [];

    private bool $resolverFails = false;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->app->instance(EnabledModulesResolverInterface::class, $this->resolver());
        $this->app->forgetInstance(ModuleChangeWindowInterface::class);
    }

    public function test_it_is_bound_once_for_the_whole_process(): void
    {
        $window = $this->app->make(ModuleChangeWindowInterface::class);

        $this->assertSame($window, $this->app->make(ModuleChangeWindowInterface::class));
    }

    public function test_a_change_is_in_progress_while_it_runs_and_not_after(): void
    {
        $window = $this->window();
        $during = null;

        $outcome = new \stdClass;

        $result = $window->run(function () use ($window, &$during, $outcome): \stdClass {
            $during = $window->inProgress();

            return $outcome;
        });

        $this->assertSame($outcome, $result);
        $this->assertTrue($during);
        $this->assertFalse($window->inProgress());
    }

    public function test_another_process_sees_the_change_in_progress(): void
    {
        $window = $this->window();
        $seenElsewhere = null;

        $window->run(function () use (&$seenElsewhere): void {
            $seenElsewhere = $this->window()->inProgress();
        });

        $this->assertTrue($seenElsewhere);
    }

    public function test_after_a_change_it_records_the_fingerprint_of_the_enabled_modules(): void
    {
        $window = $this->window();

        $window->run(function (): void {
            $this->enabled = [$this->module('forum', '1.0.0')];
        });

        $this->assertTrue(ModuleSetFingerprint::of([$this->module('forum', '1.0.0')])->equals($window->currentFingerprint()));
        $this->assertTrue(ModuleSetFingerprint::of([$this->module('forum', '1.0.0')])->equals($this->window()->currentFingerprint()));
    }

    public function test_the_fingerprint_is_read_afresh_not_from_the_list_this_process_booted_with(): void
    {
        $resolver = Mockery::mock(EnabledModulesResolverInterface::class);
        $resolver->shouldReceive('reset')->once()->ordered();
        $resolver->shouldReceive('modules')->once()->ordered()->andReturn([]);

        (new CacheModuleChangeWindow(Cache::store(), $resolver))->run(fn (): null => null);
    }

    public function test_a_failing_change_still_ends_the_window_and_records_the_fingerprint(): void
    {
        $window = $this->window();

        try {
            $window->run($this->failingChange('migration failed', [$this->module('forum', '1.0.0')]));
            $this->fail('The change should have thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('migration failed', $e->getMessage());
        }

        $this->assertFalse($window->inProgress());
        $this->assertTrue(ModuleSetFingerprint::of([$this->module('forum', '1.0.0')])->equals($window->currentFingerprint()));
    }

    public function test_a_change_inside_another_ends_with_the_outer_one(): void
    {
        $window = $this->window();
        $afterInner = null;

        $window->run(function () use ($window, &$afterInner): void {
            $window->run(function (): void {
                $this->enabled = [$this->module('forum', '1.0.0')];
            });

            $afterInner = $window->inProgress();
            $this->enabled = [$this->module('forum', '1.0.1')];
        });

        $this->assertTrue($afterInner);
        $this->assertFalse($window->inProgress());
        $this->assertTrue(ModuleSetFingerprint::of([$this->module('forum', '1.0.1')])->equals($window->currentFingerprint()));
    }

    public function test_a_window_opened_by_hand_lasts_until_it_is_closed(): void
    {
        $window = $this->window();

        $window->open();
        $this->enabled = [$this->module('forum', '1.0.1')];
        $this->assertTrue($window->inProgress());
        $this->assertNull($window->currentFingerprint());

        $window->close();
        $this->assertFalse($window->inProgress());
        $this->assertTrue(ModuleSetFingerprint::of([$this->module('forum', '1.0.1')])->equals($window->currentFingerprint()));
    }

    public function test_closing_a_window_that_is_not_open_does_nothing(): void
    {
        $window = $this->window();

        $window->close();

        $this->assertFalse($window->inProgress());
        $this->assertNull($window->currentFingerprint());
    }

    public function test_a_window_left_open_by_a_process_that_died_expires(): void
    {
        Cache::put(CacheModuleChangeWindow::IN_PROGRESS_KEY, true, CacheModuleChangeWindow::MAX_SECONDS);

        $this->assertTrue($this->window()->inProgress());

        $this->travel(CacheModuleChangeWindow::MAX_SECONDS + 1)->seconds();

        $this->assertFalse($this->window()->inProgress());
    }

    public function test_without_a_recorded_fingerprint_it_says_it_does_not_know(): void
    {
        $this->assertNull($this->window()->currentFingerprint());
    }

    public function test_a_fingerprint_it_cannot_read_restarts_the_workers_and_forgets_the_old_one(): void
    {
        $window = $this->window();
        $window->run(fn (): null => null);
        $this->assertNotNull($window->currentFingerprint());

        $log = Log::spy();
        $this->resolverFails = true;
        $window->run(fn (): null => null);

        $this->assertNull($window->currentFingerprint());
        $this->assertFalse($window->inProgress());
        $this->assertNotNull(Cache::get('illuminate:queue:restart'));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_an_unreachable_cache_never_stops_the_change(): void
    {
        $cache = Mockery::mock(Repository::class);
        $cache->shouldReceive('put', 'forever', 'forget', 'get', 'has')->andThrow(new RuntimeException('cache down'));
        $log = Log::spy();
        $outcome = new \stdClass;

        $window = new CacheModuleChangeWindow($cache, $this->resolver());

        $this->assertSame($outcome, $window->run(fn (): \stdClass => $outcome));
        $this->assertFalse($window->inProgress());
        $this->assertNull($window->currentFingerprint());
        $log->shouldHaveReceived('warning')->atLeast()->once();
    }

    /**
     * A change that leaves the given modules enabled and then throws
     *
     * @param  list<Module>  $enabled
     * @return Closure(): void
     */
    private function failingChange(string $message, array $enabled): Closure
    {
        return function () use ($message, $enabled): void {
            $this->enabled = $enabled;

            if ($enabled !== []) {
                throw new RuntimeException($message);
            }
        };
    }

    private function window(): CacheModuleChangeWindow
    {
        return new CacheModuleChangeWindow(Cache::store(), $this->resolver());
    }

    private function resolver(): EnabledModulesResolverInterface
    {
        $test = $this;

        return new readonly class($test) implements EnabledModulesResolverInterface
        {
            public function __construct(private CacheModuleChangeWindowTest $test) {}

            public function names(): array
            {
                return array_map(static fn (Module $module): string => $module->name()->value, $this->modules());
            }

            public function modules(): array
            {
                return $this->test->enabledModules();
            }

            public function rejected(): array
            {
                return [];
            }

            public function migrationPaths(): array
            {
                return [];
            }

            public function reset(): void {}
        };
    }

    /**
     * @return list<Module>
     */
    public function enabledModules(): array
    {
        if ($this->resolverFails) {
            throw new RuntimeException('database unreachable');
        }

        return $this->enabled;
    }

    private function module(string $name, string $version): Module
    {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name),
            displayName: $name,
            description: '',
            version: ModuleVersion::fromString($version),
            author: '',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
        );
    }
}
