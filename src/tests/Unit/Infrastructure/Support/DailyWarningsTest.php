<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use App\Infrastructure\SiteBlocks\SiteBlockRegistry;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class DailyWarningsTest extends TestCase
{
    public function test_a_warning_is_logged_once_per_process_and_once_a_day_across_processes(): void
    {
        $cache = new Repository(new ArrayStore);
        $log = Log::spy();

        $warnings = new DailyWarnings('example:warned:', static fn (): Cache => $cache);
        $warnings->warn('renderer|key|failed', 'Something failed.', ['key' => 'key']);
        $warnings->warn('renderer|key|failed', 'Something failed.', ['key' => 'key']);

        // A later request builds its own instance and shares the cache
        (new DailyWarnings('example:warned:', static fn (): Cache => $cache))->warn('renderer|key|failed', 'Something failed.');

        $log->shouldHaveReceived('warning')->once()->with('Something failed.', ['key' => 'key']);
    }

    public function test_the_mark_is_kept_for_a_day_under_the_prefix(): void
    {
        $cache = new Repository(new ArrayStore);
        Log::spy();

        (new DailyWarnings('example:warned:', static fn (): Cache => $cache))->warn('one', 'Message.');

        $this->assertTrue($cache->has('example:warned:'.sha1('one')));
    }

    public function test_different_prefixes_keep_their_warnings_apart(): void
    {
        $cache = new Repository(new ArrayStore);
        $log = Log::spy();

        (new DailyWarnings('first:warned:', static fn (): Cache => $cache))->warn('same', 'First.');
        (new DailyWarnings('second:warned:', static fn (): Cache => $cache))->warn('same', 'Second.');

        $log->shouldHaveReceived('warning')->twice();
    }

    public function test_without_a_cache_it_still_warns_once_per_process(): void
    {
        $log = Log::spy();
        $warnings = new DailyWarnings('example:warned:', static fn (): Cache => throw new RuntimeException('No cache'));

        $warnings->warn('one', 'Message.');
        $warnings->warn('one', 'Message.');

        $log->shouldHaveReceived('warning')->once();
    }

    public function test_building_it_never_asks_for_the_cache(): void
    {
        new DailyWarnings('example:warned:', static fn (): Cache => throw new LogicException('The cache was asked for'));

        $this->addToAssertionCount(1);
    }

    public function test_site_blocks_keep_remembering_their_warnings_where_they_did(): void
    {
        $cache = new Repository(new ArrayStore);
        Log::spy();

        (new SiteBlockRegistry($this->app, cache: $cache))->register(stdClass::class, 'newsletter');
        (new SiteBlockRegistry($this->app, cache: $cache))->register(stdClass::class, 'newsletter');
        $registry = new SiteBlockRegistry($this->app, cache: $cache);
        $registry->register(stdClass::class, 'newsletter');
        $registry->all();

        $this->assertTrue($cache->has('site_blocks:warned:'.sha1('registry|'.stdClass::class.'|type')));
    }
}
