<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivitySourceRegistrationDTO;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Domain\Activity\ActivityKind;
use App\Infrastructure\Activity\ActivitySourceRegistry;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Tests\Support\Activity\FakeActivitySource;
use Tests\TestCase;

final class ActivitySourceRegistryTest extends TestCase
{
    private ActivitySourceRegistry $registry;

    private int $bindings = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ActivitySourceRegistry($this->app, new Repository(new ArrayStore));
    }

    public function test_the_registry_is_a_singleton(): void
    {
        $registry = $this->app->make(ActivitySourceRegistryInterface::class);

        $this->assertSame($registry, $this->app->make(ActivitySourceRegistryInterface::class));
    }

    public function test_a_module_source_is_found_by_type_with_its_module_and_kind(): void
    {
        $class = $this->source('game-tables.session', ActivityKind::GameSession);
        $this->registry->register($class, 'game-tables');

        $registration = $this->registry->find('game-tables.session');
        $this->assertInstanceOf(ActivitySourceRegistrationDTO::class, $registration);
        $this->assertSame('game-tables.session', $registration->type);
        $this->assertSame(ActivityKind::GameSession, $registration->kind);
        $this->assertSame('game-tables', $registration->module);
        $this->assertSame($class, $registration->class);
        $this->assertNull($this->registry->find('game-tables.campaign'));
    }

    public function test_a_core_source_has_no_module(): void
    {
        $this->registry->register($this->source('core.account', ActivityKind::Account));

        $this->assertNull($this->registry->find('core.account')?->module);
        $this->assertNotNull($this->registry->find('core.account'));
    }

    public function test_the_registry_keeps_what_it_read_never_an_instance(): void
    {
        $built = 0;
        $this->app->bind('counted-activity-source', static function () use (&$built): ActivitySourceInterface {
            $built++;

            return new class extends FakeActivitySource
            {
                public function type(): string
                {
                    return 'forum.post';
                }
            };
        });
        $this->registry->register('counted-activity-source', 'forum');

        $this->assertSame('counted-activity-source', $this->registry->find('forum.post')?->class);
        $this->registry->all();

        // Built once, only to check it: whoever reads facts builds its own
        $this->assertSame(1, $built);
    }

    public function test_sources_are_listed_by_type(): void
    {
        $this->registry->registerMany([
            $this->source('forum.post'),
            $this->source('forum.discussion'),
        ], 'forum');

        $this->assertSame(['forum.discussion', 'forum.post'], array_map(
            static fn (ActivitySourceRegistrationDTO $registration): string => $registration->type,
            $this->registry->all(),
        ));
    }

    public function test_a_module_s_sources_are_forgotten_when_it_is_unregistered(): void
    {
        $this->registry->register($this->source('forum.post'), 'forum');
        $this->registry->register($this->source('core.account'));
        $this->registry->unregisterModule('forum');

        $this->assertNull($this->registry->find('forum.post'));
        $this->assertNotNull($this->registry->find('core.account'));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function foreignTypes(): iterable
    {
        yield 'another module' => ['game-tables.session', 'forum'];
        yield 'the core, from a module' => ['core.account', 'forum'];
        yield 'a module, from the core' => ['forum.post', null];
        yield 'a module called core' => ['core.account', 'core'];
        yield 'invalid' => ['Forum Posts', 'forum'];
    }

    #[DataProvider('foreignTypes')]
    public function test_a_source_whose_type_is_not_its_owner_s_is_left_out_with_a_warning(string $type, ?string $module): void
    {
        $log = Log::spy();
        $this->registry->register($this->source($type), $module);

        $this->assertSame([], $this->registry->all());
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_two_sources_with_the_same_type_are_both_left_out_with_a_warning(): void
    {
        $log = Log::spy();
        $this->registry->registerMany([$this->source('forum.post'), $this->source('forum.post')], 'forum');
        $this->registry->register($this->source('forum.discussion'), 'forum');

        $this->assertSame(['forum.discussion'], array_map(
            static fn (ActivitySourceRegistrationDTO $registration): string => $registration->type,
            $this->registry->all(),
        ));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_class_that_is_not_a_source_is_left_out(): void
    {
        $log = Log::spy();
        $this->registry->register(stdClass::class, 'forum');

        $this->assertSame([], $this->registry->all());
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_source_that_cannot_be_built_or_read_is_left_out_without_its_message(): void
    {
        $this->app->bind('unbuildable-activity-source', static fn () => throw new RuntimeException('secret@example.test'));
        $throwingType = new class extends FakeActivitySource
        {
            public function type(): string
            {
                throw new RuntimeException('secret@example.test');
            }
        };
        $log = Log::spy();

        $this->registry->registerMany(['unbuildable-activity-source', $throwingType::class], 'forum');

        $this->assertSame([], $this->registry->all());
        $log->shouldHaveReceived('warning')->twice()->withArgs(
            static fn (string $message, array $context): bool => $context['exception'] === RuntimeException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret@example.test'),
        );
    }

    public function test_a_class_registered_by_two_owners_keeps_the_first(): void
    {
        $class = $this->source('forum.post');
        $log = Log::spy();

        $this->registry->register($class, 'forum');
        $this->registry->register($class, 'game-tables');

        $this->assertSame('forum', $this->registry->find('forum.post')?->module);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_each_warning_is_logged_once_a_day(): void
    {
        $cache = new Repository(new ArrayStore);
        $log = Log::spy();
        $class = $this->source('Not Valid');

        foreach ([1, 2] as $request) {
            // A new registry per request, as in php-fpm, with the same cache
            $registry = new ActivitySourceRegistry($this->app, $cache);
            $registry->register($class, 'forum');
            $registry->all();
        }

        $log->shouldHaveReceived('warning')->once();
    }

    private function source(string $type, ActivityKind $kind = ActivityKind::ForumPost): string
    {
        $id = 'activity-source-'.(++$this->bindings);
        $this->app->bind($id, static fn (): ActivitySourceInterface => new class($type, $kind) extends FakeActivitySource
        {
            public function __construct(
                private readonly string $sourceType,
                private readonly ActivityKind $sourceKind,
            ) {}

            public function type(): string
            {
                return $this->sourceType;
            }

            public function kind(): ActivityKind
            {
                return $this->sourceKind;
            }
        });

        return $id;
    }
}
