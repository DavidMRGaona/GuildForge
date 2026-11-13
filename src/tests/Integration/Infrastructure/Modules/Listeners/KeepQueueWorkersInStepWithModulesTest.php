<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Listeners;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Modules\Services\ModuleChangeWindowInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleSetFingerprint;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Listeners\KeepQueueWorkersInStepWithModules;
use App\Modules\ModuleLoader;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

final class KeepQueueWorkersInStepWithModulesTest extends TestCase
{
    private ModuleChangeWindowInterface $window;

    private bool $inProgress = false;

    private ?ModuleSetFingerprint $current = null;

    protected function setUp(): void
    {
        parent::setUp();

        $test = $this;
        $this->window = new readonly class($test) implements ModuleChangeWindowInterface
        {
            public function __construct(private KeepQueueWorkersInStepWithModulesTest $test) {}

            public function run(\Closure $change): mixed
            {
                return $change();
            }

            public function open(): void {}

            public function close(): void {}

            public function inProgress(): bool
            {
                return $this->test->inProgress();
            }

            public function currentFingerprint(): ?ModuleSetFingerprint
            {
                return $this->test->current();
            }
        };
        $this->app->instance(ModuleChangeWindowInterface::class, $this->window);
        $this->bootWorkerWith([$this->module('forum', '1.0.0')]);
    }

    public function inProgress(): bool
    {
        return $this->inProgress;
    }

    public function current(): ?ModuleSetFingerprint
    {
        return $this->current;
    }

    public function test_a_worker_whose_modules_are_current_carries_on(): void
    {
        $this->current = ModuleSetFingerprint::of([$this->module('forum', '1.0.0')]);

        $this->assertNull($this->guard()->handle($this->looping()));
        $this->assertFalse($this->worker()->shouldQuit);
    }

    public function test_a_worker_takes_no_job_while_the_modules_are_changing(): void
    {
        $this->inProgress = true;
        $this->current = ModuleSetFingerprint::of([]);

        $this->assertFalse($this->guard()->handle($this->looping()));
        // It waits for the change to end: a worker started now would boot halfway through it
        $this->assertFalse($this->worker()->shouldQuit);
    }

    public function test_a_worker_booted_with_other_modules_stops_before_taking_a_job(): void
    {
        $this->current = ModuleSetFingerprint::of([$this->module('forum', '1.0.0'), $this->module('surveys', '1.6.0')]);

        $this->assertFalse($this->guard()->handle($this->looping()));
        $this->assertTrue($this->worker()->shouldQuit);
    }

    public function test_a_worker_booted_with_another_version_stops_before_taking_a_job(): void
    {
        $this->current = ModuleSetFingerprint::of([$this->module('forum', '1.0.1')]);

        $this->assertFalse($this->guard()->handle($this->looping()));
        $this->assertTrue($this->worker()->shouldQuit);
    }

    public function test_without_a_recorded_fingerprint_the_worker_carries_on(): void
    {
        $this->current = null;

        $this->assertNull($this->guard()->handle($this->looping()));
        $this->assertFalse($this->worker()->shouldQuit);
    }

    public function test_a_process_that_never_booted_the_modules_is_left_alone(): void
    {
        $resolver = Mockery::mock(EnabledModulesResolverInterface::class);
        $this->app->instance(ModuleLoader::class, new ModuleLoader($this->app, $resolver));
        $this->current = ModuleSetFingerprint::of([]);

        $this->assertNull($this->guard()->handle($this->looping()));
        $this->assertFalse($this->worker()->shouldQuit);
    }

    public function test_it_listens_to_every_turn_of_the_worker_loop(): void
    {
        $this->inProgress = true;

        $this->assertFalse(Event::until(new Looping('redis', 'default')));
    }

    private function guard(): KeepQueueWorkersInStepWithModules
    {
        return $this->app->make(KeepQueueWorkersInStepWithModules::class);
    }

    private function worker(): Worker
    {
        return $this->app->make('queue.worker');
    }

    private function looping(): Looping
    {
        return new Looping('redis', 'default');
    }

    /**
     * @param  list<Module>  $modules
     */
    private function bootWorkerWith(array $modules): void
    {
        $resolver = Mockery::mock(EnabledModulesResolverInterface::class);
        // Modules whose files are missing fail to boot; the loader still says what it booted with
        $resolver->shouldReceive('modules')->andReturn($modules);
        $loader = new ModuleLoader($this->app, $resolver);
        $loader->boot();

        $this->app->instance(ModuleLoader::class, $loader);
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
            namespace: 'Modules\\Missing'.ucfirst($name),
            provider: 'Missing'.ucfirst($name).'ServiceProvider',
            path: sys_get_temp_dir().'/guildforge-missing-module-'.$name,
        );
    }
}
