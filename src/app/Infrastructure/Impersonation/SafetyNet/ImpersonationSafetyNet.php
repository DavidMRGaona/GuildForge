<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation\SafetyNet;

use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use League\Flysystem\Filesystem as Flysystem;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Throwable;

/**
 * Nothing persists while an impersonation lasts (core#572), in both modes. The request runs in
 * a transaction of the default connection that is always rolled back, and what does not wait
 * for a commit is neutralised: mail goes to the array transport, every queue connection drops
 * its jobs, every cache store becomes a store of this process (Cache::forget() followed by
 * remember() would otherwise leave rolled back data for the whole site), Scout updates go to
 * that queue, and the upload disks read from the real ones and discard writes and deletes.
 *
 * Once engaged it stays so until the process ends: terminating middleware and defer()
 * callbacks run after the response, so they get the same outputs and a rolled back
 * transaction of their own. PHP-FPM runs one request per process.
 */
final class ImpersonationSafetyNet
{
    /** Flysystem options kept from the real disk, as FilesystemManager builds them */
    private const array FLYSYSTEM_OPTIONS = ['directory_visibility', 'disable_asserts', 'retain_visibility', 'temporary_url', 'url', 'visibility'];

    private bool $engaged = false;

    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
        private readonly Dispatcher $events,
        private readonly RolledBackTransaction $transaction,
    ) {}

    public function isEngaged(): bool
    {
        return $this->engaged;
    }

    /**
     * Runs the request inside the rolled back transaction, with the outputs neutralised.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $request
     * @return TResult
     */
    public function run(Closure $request): mixed
    {
        $this->engage();
        $this->transaction->begin();

        try {
            return $request();
        } finally {
            $this->transaction->rollBack();
        }
    }

    public function engage(): void
    {
        if ($this->engaged) {
            return;
        }

        $this->engaged = true;

        $this->neutraliseMail();
        $this->neutraliseQueue();
        $this->neutraliseCache();
        $this->neutraliseSearchIndex();
        $this->neutraliseDisks();
        $this->rollBackWhatRunsAfterTheResponse();
    }

    private function neutraliseMail(): void
    {
        // Every mailer, so one chosen by name (Mail::mailer('smtp')) sends nothing either
        foreach (array_keys((array) $this->config->get('mail.mailers', [])) as $name) {
            $this->config->set('mail.mailers.'.$name, ['transport' => 'array']);
        }

        $this->config->set('mail.mailers.array', ['transport' => 'array']);
        $this->config->set('mail.default', 'array');

        // Mail::fake() swaps the manager for one that already keeps everything
        $manager = Mail::getFacadeRoot();
        if ($manager instanceof MailManager) {
            $manager->forgetMailers();
        }
    }

    private function neutraliseQueue(): void
    {
        // Every connection, whatever the installation's driver, sync included
        foreach (array_keys((array) $this->config->get('queue.connections', [])) as $name) {
            $this->config->set('queue.connections.'.$name, ['driver' => 'null']);
        }

        if ($this->app->resolved('queue') && $this->app->make('queue') instanceof QueueFake) {
            return;
        }

        // A new manager, so no connection resolved before keeps the real driver
        $this->app->forgetInstance('queue');
        $this->app->forgetInstance('queue.connection');
        Queue::clearResolvedInstance('queue');
    }

    private function neutraliseCache(): void
    {
        $names = array_keys((array) $this->config->get('cache.stores', []));
        foreach ($names as $name) {
            $this->config->set('cache.stores.'.$name, ['driver' => 'array', 'serialize' => false]);
        }

        $cache = $this->app->make(CacheManager::class);

        // Repositories resolved before (the default one, the rate limiter's) keep their object,
        // so their store is replaced in place
        foreach ($names as $name) {
            $repository = $cache->store($name);
            if ($repository instanceof Repository) {
                $repository->setStore(new ArrayStore);
            }
        }
    }

    private function neutraliseSearchIndex(): void
    {
        // Scout writes to the index right away unless it queues; the queue drops it now
        if (! $this->config->get('scout.queue')) {
            $this->config->set('scout.queue', true);
        }
    }

    private function neutraliseDisks(): void
    {
        $filesystem = $this->app->make(FilesystemManager::class);

        $uploadsDisk = FileUploadConfiguration::disk();
        $uploadsDirectory = FileUploadConfiguration::directory().'/';

        foreach (array_keys((array) $this->config->get('filesystems.disks', [])) as $name) {
            try {
                $real = $filesystem->disk($name);
            } catch (Throwable) {
                // A disk that cannot be built cannot write either
                continue;
            }

            if (! $real instanceof FilesystemAdapter) {
                continue;
            }

            $adapter = $real->getAdapter();
            $options = $real->getConfig();
            $discarding = new DiscardingFlysystemAdapter($adapter, $name === $uploadsDisk ? $uploadsDirectory : null);

            // The real adapter stays as the second argument: URLs are built from it
            $filesystem->set($name, new FilesystemAdapter(
                new Flysystem($discarding, Arr::only($options, self::FLYSYSTEM_OPTIONS)),
                $adapter,
                $options,
            ));
        }
    }

    private function rollBackWhatRunsAfterTheResponse(): void
    {
        // Terminating comes before terminating middleware (defer() callbacks run there) and
        // the application's terminating callbacks run last
        $this->events->listen(Terminating::class, function (): void {
            $this->transaction->begin();
        });
        $this->app->terminating(function (): void {
            $this->transaction->rollBack();
        });
    }
}
