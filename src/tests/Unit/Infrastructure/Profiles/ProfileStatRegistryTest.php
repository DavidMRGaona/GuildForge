<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Infrastructure\Profiles\ProfileStatRegistry;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;
use stdClass;
use Tests\Support\Profiles\FakeProfileStat;
use Tests\TestCase;

final class ProfileStatRegistryTest extends TestCase
{
    private ProfileStatRegistry $registry;

    private int $bindings = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProfileStatRegistry($this->app, new Repository(new ArrayStore));
    }

    public function test_the_registry_is_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(ProfileStatRegistryInterface::class),
            $this->app->make(ProfileStatRegistryInterface::class),
        );
    }

    public function test_module_stats_are_found_by_key_and_listed_by_default_order_then_key(): void
    {
        $this->registry->registerMany([
            $this->stat('forum.topics', 20),
            $this->stat('forum.messages', 10),
            $this->stat('forum.likes', 20),
        ], 'forum');

        $this->assertSame('forum.topics', $this->registry->find('forum.topics')?->key());
        $this->assertSame(['forum.messages', 'forum.likes', 'forum.topics'], array_map(
            static fn (ProfileStatInterface $stat): string => $stat->key(),
            $this->registry->all(),
        ));

        $this->registry->unregisterModule('forum');
        $this->assertSame([], $this->registry->all());
    }

    public function test_a_stat_with_a_foreign_or_invalid_key_is_left_out_with_a_warning(): void
    {
        $log = Log::spy();
        $this->registry->register($this->stat('game-tables.tables'), 'forum');
        $this->registry->register($this->stat('core.messages'), 'forum');
        $this->registry->register($this->stat('no key'), 'forum');
        $this->registry->register(stdClass::class, 'forum');

        $this->assertSame([], $this->registry->all());
        $log->shouldHaveReceived('warning')->times(4);
    }

    public function test_two_stats_with_one_key_are_both_left_out(): void
    {
        Log::spy();
        $this->registry->register($this->stat('forum.messages'), 'forum');
        $this->registry->register($this->stat('forum.messages'), 'forum');

        $this->assertNull($this->registry->find('forum.messages'));
    }

    private function stat(string $key, int $order = 100): string
    {
        $stat = new class($key, $order) extends FakeProfileStat
        {
            public function __construct(
                private readonly string $statKey = '',
                private readonly int $position = 100,
            ) {}

            public function key(): string
            {
                return $this->statKey;
            }

            public function order(): int
            {
                return $this->position;
            }
        };

        $id = 'test-stat-'.(++$this->bindings);
        $this->app->bind($id, static fn (): ProfileStatInterface => $stat);

        return $id;
    }
}
