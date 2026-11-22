<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Listeners;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsVersionInterface;
use App\Domain\Modules\Exceptions\ModuleSettingsUnavailableException;
use App\Infrastructure\Modules\Listeners\RefreshModuleSettingsForEachJob;
use App\Modules\ModuleServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

/**
 * A queue worker loads the module settings once, when it boots, and runs for up to an hour:
 * each job must still read the values saved meanwhile, never the ones the process started with
 * (core#825).
 */
final class RefreshModuleSettingsForEachJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string MODULE = 'worker-fixture';

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-worker-settings-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->modulesPath.'/'.self::MODULE.'/config');
        File::put(
            $this->modulesPath.'/'.self::MODULE.'/config/settings.php',
            "<?php\n\nreturn ['access' => 'open', 'limits' => ['posts' => 10, 'threads' => 5]];\n",
        );
        config(['modules.path' => $this->modulesPath, 'queue.default' => 'database']);
        ReadModuleSettingJob::$seen = null;
        Sleep::fake();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        ReadModuleSettingJob::$seen = null;
        parent::tearDown();
    }

    public function test_a_job_reads_a_setting_saved_after_the_worker_booted(): void
    {
        $this->bootModule();

        // Saved by another process (the panel): this one keeps what it booted with in config
        $this->saveFromAnotherProcess(['access' => 'members']);
        $this->assertSame('open', config('modules.settings.'.self::MODULE.'.access'));

        dispatch(new ReadModuleSettingJob);
        $this->work();

        $this->assertSame('members', ReadModuleSettingJob::$seen);
        $this->assertSame(
            ['access' => 'members', 'limits' => ['posts' => 10, 'threads' => 5]],
            config('modules.settings.'.self::MODULE),
        );
    }

    public function test_every_job_reads_the_values_saved_before_it(): void
    {
        $this->bootModule();

        $this->saveFromAnotherProcess(['access' => 'members']);
        dispatch(new ReadModuleSettingJob);
        $this->work();
        $this->assertSame('members', ReadModuleSettingJob::$seen);

        $this->saveFromAnotherProcess(['access' => 'association']);
        dispatch(new ReadModuleSettingJob);
        $this->work();
        $this->assertSame('association', ReadModuleSettingJob::$seen);
    }

    public function test_settings_forgotten_meanwhile_fall_back_to_the_release_defaults(): void
    {
        app(ModuleSettingsStoreInterface::class)->store(self::MODULE, ['access' => 'members']);
        $this->bootModule();
        $this->assertSame('members', config('modules.settings.'.self::MODULE.'.access'));

        app(ModuleSettingsStoreInterface::class)->forget(self::MODULE);
        $this->startAnotherJob();
        dispatch(new ReadModuleSettingJob);
        $this->work();

        $this->assertSame('open', ReadModuleSettingJob::$seen);
    }

    public function test_the_stored_values_are_not_read_again_while_no_setting_changed(): void
    {
        $this->bootModule();
        $reads = $this->countStoredReads();

        $this->handle($this->processing());
        $afterFirstJob = $reads->count;
        $this->startAnotherJob();
        $this->handle($this->processing());
        $this->startAnotherJob();
        $this->handle($this->processing());

        // The first job reads them once (the boot read carries no version); later ones only the version
        $this->assertSame(1, $afterFirstJob);
        $this->assertSame(1, $reads->count);
    }

    public function test_a_job_is_not_run_when_the_settings_version_cannot_be_read(): void
    {
        $this->bootModule();
        $this->saveFromAnotherProcess(['access' => 'members']);
        $this->app->instance(SettingsVersionInterface::class, new class implements SettingsVersionInterface
        {
            public function current(): string
            {
                throw new RuntimeException('cache is down');
            }
        });

        try {
            $this->handle($this->processing());
            $this->fail('A job must not run when the settings cannot be read');
        } catch (ModuleSettingsUnavailableException) {
            // Only after the one retry
        }

        Sleep::assertSleptTimes(1);
        $this->assertSame('open', config('modules.settings.'.self::MODULE.'.access'));
    }

    public function test_a_brief_cut_costs_the_job_no_attempt(): void
    {
        $this->bootModule();
        $this->saveFromAnotherProcess(['access' => 'members']);
        $flaky = new FlakySettingsVersion(app(SettingsVersionInterface::class), failures: 1);
        $this->app->instance(SettingsVersionInterface::class, $flaky);

        // Throws nothing: the read is tried again after a pause and the job runs with the new value
        $this->handle($this->processing());

        Sleep::assertSleptTimes(1);
        $this->assertSame(2, $flaky->calls);
        $this->assertSame('members', config('modules.settings.'.self::MODULE.'.access'));
    }

    public function test_a_brief_cut_lets_the_worker_run_the_job_on_its_first_attempt(): void
    {
        $this->bootModule();
        $this->saveFromAnotherProcess(['access' => 'members']);
        $this->app->instance(SettingsVersionInterface::class, new FlakySettingsVersion(app(SettingsVersionInterface::class), failures: 1));

        dispatch(new ReadModuleSettingJob);
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0, '--tries' => 1]);

        $this->assertSame('members', ReadModuleSettingJob::$seen);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_a_module_booted_after_a_refresh_is_read_again_on_the_next_job(): void
    {
        $this->bootModule();
        $this->handle($this->processing());
        $reads = $this->countStoredReads();

        // Its boot read carries no version (and may have fallen back to the defaults)
        $this->bootModule('late-fixture');
        $this->handle($this->processing());

        $this->assertSame(2, $reads->count);
    }

    public function test_a_sync_job_keeps_the_config_of_the_request_that_dispatched_it(): void
    {
        $this->bootModule();
        // A test (or the request itself) set the value in this process: a sync job runs in it
        config(['modules.settings.'.self::MODULE.'.access' => 'set-in-this-request']);

        dispatch_sync(new ReadModuleSettingJob);

        $this->assertSame('set-in-this-request', ReadModuleSettingJob::$seen);
    }

    public function test_nothing_is_read_in_a_process_without_modules(): void
    {
        $this->app->instance(SettingsVersionInterface::class, new class implements SettingsVersionInterface
        {
            public function current(): string
            {
                throw new RuntimeException('must not be asked');
            }
        });

        $this->handle($this->processing());

        $this->assertFalse(config()->has('modules.settings.'.self::MODULE));
    }

    public function test_the_listener_runs_before_each_job_of_a_worker(): void
    {
        $listeners = Event::getRawListeners()[JobProcessing::class] ?? [];

        $this->assertIsArray($listeners);
        $this->assertContains(RefreshModuleSettingsForEachJob::class, $listeners);
    }

    public function test_the_listener_ignores_sync_jobs(): void
    {
        $this->bootModule();
        $this->saveFromAnotherProcess(['access' => 'members']);

        $this->handle(new JobProcessing('sync', new SyncJob($this->app, '{}', 'sync', 'default')));

        $this->assertSame('open', config('modules.settings.'.self::MODULE.'.access'));
    }

    private function bootModule(string $name = self::MODULE): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public string $name = '';

            public function moduleName(): string
            {
                return $this->name;
            }
        };
        $provider->name = $name;
        $provider->register();
    }

    /**
     * Writes the stored values as the panel's request would, leaving this process's config as
     * it was, and starts the next job's scope.
     *
     * @param  array<string, mixed>  $settings
     */
    private function saveFromAnotherProcess(array $settings): void
    {
        $before = config('modules.settings.'.self::MODULE);
        app(ModuleSettingsStoreInterface::class)->store(self::MODULE, $settings);
        config(['modules.settings.'.self::MODULE => $before]);
        $this->startAnotherJob();
    }

    /**
     * Laravel drops scoped instances when a worker starts the next job.
     */
    private function startAnotherJob(): void
    {
        $this->app->forgetScopedInstances();
    }

    private function work(): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0, '--tries' => 3]);
    }

    private function processing(): JobProcessing
    {
        return new JobProcessing('database', new FakeJob);
    }

    private function handle(JobProcessing $event): void
    {
        app(RefreshModuleSettingsForEachJob::class)->handle($event);
    }

    /**
     * Counts the strict reads of the stored values, keeping the real store behind them.
     */
    private function countStoredReads(): CountingModuleSettingsStore
    {
        $counting = new CountingModuleSettingsStore(app(ModuleSettingsStoreInterface::class));
        $this->app->instance(ModuleSettingsStoreInterface::class, $counting);

        return $counting;
    }
}

final class ReadModuleSettingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public static ?string $seen = null;

    public function handle(): void
    {
        $value = config('modules.settings.worker-fixture.access');
        self::$seen = is_string($value) ? $value : null;
    }
}

final class CountingModuleSettingsStore implements ModuleSettingsStoreInterface
{
    public int $count = 0;

    public function __construct(private readonly ModuleSettingsStoreInterface $real) {}

    public function effective(string $moduleName, string $modulePath): array
    {
        return $this->real->effective($moduleName, $modulePath);
    }

    public function current(string $moduleName, string $modulePath): array
    {
        $this->count++;

        return $this->real->current($moduleName, $modulePath);
    }

    public function defaults(string $modulePath): array
    {
        return $this->real->defaults($modulePath);
    }

    public function stored(string $moduleName): array
    {
        return $this->real->stored($moduleName);
    }

    public function store(string $moduleName, array $settings): void
    {
        $this->real->store($moduleName, $settings);
    }

    public function forget(string $moduleName): void
    {
        $this->real->forget($moduleName);
    }
}

/**
 * The real version behind a few failures, as a cache store coming back after a brief cut.
 */
final class FlakySettingsVersion implements SettingsVersionInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly SettingsVersionInterface $real,
        private int $failures,
    ) {}

    public function current(): string
    {
        $this->calls++;

        if ($this->failures > 0) {
            $this->failures--;

            throw new RuntimeException('cache is briefly down');
        }

        return $this->real->current();
    }
}
