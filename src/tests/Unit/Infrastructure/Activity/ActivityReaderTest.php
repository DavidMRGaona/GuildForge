<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySourceSummaryDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Application\Activity\Enums\ActivityReadOutcome;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Infrastructure\Activity\ActivityReader;
use App\Infrastructure\Activity\ActivitySourceRegistry;
use Closure;
use DateTimeImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Support\Activity\FakeActivitySource;
use Tests\TestCase;

final class ActivityReaderTest extends TestCase
{
    private const string ANA = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e5f';

    private const string LUIS = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e60';

    private ActivitySourceRegistry $registry;

    private Repository $cache;

    private int $bindings = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Repository(new ArrayStore);
        $this->registry = new ActivitySourceRegistry($this->app, $this->cache);
    }

    public function test_the_reader_is_bound_once_per_request_or_job(): void
    {
        $reader = $this->app->make(ActivityReaderInterface::class);

        $this->assertSame($reader, $this->app->make(ActivityReaderInterface::class));
        $this->app->forgetScopedInstances();
        $this->assertNotSame($reader, $this->app->make(ActivityReaderInterface::class));
    }

    public function test_the_facts_of_a_subject_are_read(): void
    {
        $facts = [$this->fact(self::ANA, ActivityRole::Director), $this->fact(self::LUIS, ActivityRole::Player)];
        $this->register('game-tables.session', 'game-tables', facts: static fn (string $id): array => $id === '42' ? $facts : []);

        $result = $this->reader()->facts('game-tables.session', '42');

        $this->assertSame(ActivityReadOutcome::Read, $result->outcome);
        $this->assertTrue($result->isRead());
        $this->assertSame($facts, $result->facts);
    }

    public function test_a_subject_that_no_longer_counts_is_read_with_no_facts(): void
    {
        $this->register('game-tables.session', 'game-tables');

        $result = $this->reader()->facts('game-tables.session', '42');

        $this->assertTrue($result->isRead());
        $this->assertSame([], $result->facts);
    }

    public function test_a_type_nobody_offers_is_missing_not_empty(): void
    {
        $log = Log::spy();

        $result = $this->reader()->facts('game-tables.session', '42');

        $this->assertSame(ActivityReadOutcome::Missing, $result->outcome);
        $this->assertFalse($result->isRead());
        $this->assertSame([], $result->facts);
        $log->shouldNotHaveReceived('warning');
    }

    public function test_a_source_that_throws_fails_without_its_message(): void
    {
        $this->register('game-tables.session', 'game-tables', facts: static fn (): array => throw new RuntimeException('secret@example.test'));
        $log = Log::spy();

        $result = $this->reader()->facts('game-tables.session', '42');

        $this->assertSame(ActivityReadOutcome::Failed, $result->outcome);
        $this->assertSame([], $result->facts);
        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['type'] === 'game-tables.session'
                && $context['reason'] === 'facts'
                && $context['exception'] === RuntimeException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret@example.test'),
        );
    }

    public function test_facts_with_the_same_person_twice_in_one_role_fail(): void
    {
        $this->register('game-tables.session', 'game-tables', facts: fn (): array => [
            $this->fact(self::ANA, ActivityRole::Player),
            $this->fact(self::ANA, ActivityRole::Player),
        ]);
        $log = Log::spy();

        $result = $this->reader()->facts('game-tables.session', '42');

        $this->assertSame(ActivityReadOutcome::Failed, $result->outcome);
        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['reason'] === 'invalid_facts',
        );
    }

    public function test_facts_that_are_not_fact_dtos_fail(): void
    {
        $this->register('game-tables.session', 'game-tables', facts: static fn (): array => ['not a fact']);

        $this->assertSame(ActivityReadOutcome::Failed, $this->reader()->facts('game-tables.session', '42')->outcome);
    }

    public function test_an_id_that_cannot_be_a_subject_has_no_facts_and_the_source_is_not_asked(): void
    {
        $asked = false;
        $this->register('game-tables.session', 'game-tables', facts: static function () use (&$asked): array {
            $asked = true;

            return [];
        });

        $result = $this->reader()->facts('game-tables.session', 'not an id');

        $this->assertTrue($result->isRead());
        $this->assertSame([], $result->facts);
        $this->assertFalse($asked);
    }

    public function test_a_source_that_can_no_longer_be_built_fails(): void
    {
        $builds = 0;
        $this->app->bind('flaky-activity-source', static function () use (&$builds): ActivitySourceInterface {
            if (++$builds > 1) {
                throw new RuntimeException('down');
            }

            return new class extends FakeActivitySource
            {
                public function type(): string
                {
                    return 'forum.post';
                }
            };
        });
        $this->registry->register('flaky-activity-source', 'forum');
        $this->registry->all();

        $this->assertSame(ActivityReadOutcome::Failed, $this->reader()->facts('forum.post', '1')->outcome);
    }

    public function test_a_source_whose_type_changed_since_it_was_registered_fails(): void
    {
        $builds = 0;
        $this->app->bind('shifting-activity-source', static function () use (&$builds): ActivitySourceInterface {
            $type = ++$builds > 1 ? 'forum.discussion' : 'forum.post';

            return new class($type) extends FakeActivitySource
            {
                public function __construct(private readonly string $sourceType) {}

                public function type(): string
                {
                    return $this->sourceType;
                }
            };
        });
        $this->registry->register('shifting-activity-source', 'forum');
        $this->registry->all();

        $this->assertSame(ActivityReadOutcome::Failed, $this->reader()->facts('forum.post', '1')->outcome);
    }

    public function test_one_source_failing_leaves_the_others_alone(): void
    {
        $this->register('forum.post', 'forum', facts: static fn (): array => throw new RuntimeException('down'));
        $this->register('game-tables.session', 'game-tables', facts: fn (): array => [$this->fact(self::ANA, ActivityRole::Player)]);
        $reader = $this->reader();

        $this->assertSame(ActivityReadOutcome::Failed, $reader->facts('forum.post', '1')->outcome);
        $this->assertCount(1, $reader->facts('game-tables.session', '1')->facts);
    }

    public function test_a_history_page_is_read_with_the_limit_kept_within_bounds(): void
    {
        $limits = [];
        $page = new ActivityHistoryPageDTO([new ActivitySubjectFactsDTO('1', [$this->fact(self::ANA, ActivityRole::Player)])], 'next');
        $this->register('game-tables.session', 'game-tables', history: static function (?string $cursor, int $limit) use (&$limits, $page): ActivityHistoryPageDTO {
            $limits[] = $limit;

            return $page;
        });
        $reader = $this->reader();

        $result = $reader->history('game-tables.session', null, 100);
        $reader->history('game-tables.session', null, 0);
        $reader->history('game-tables.session', null, 1_000_000);

        $this->assertSame(ActivityReadOutcome::Read, $result->outcome);
        $this->assertTrue($result->isRead());
        $this->assertSame($page, $result->page);
        $this->assertSame([100, 1, ActivityHistoryPageDTO::MAX_LIMIT], $limits);
    }

    public function test_a_history_page_longer_than_asked_fails(): void
    {
        $this->register('game-tables.session', 'game-tables', history: static fn (): ActivityHistoryPageDTO => new ActivityHistoryPageDTO([
            new ActivitySubjectFactsDTO('1', []),
            new ActivitySubjectFactsDTO('2', []),
        ], null));

        $result = $this->reader()->history('game-tables.session', null, 1);

        $this->assertSame(ActivityReadOutcome::Failed, $result->outcome);
        $this->assertNull($result->page);
    }

    public function test_a_history_page_that_points_back_to_itself_fails(): void
    {
        $this->register('game-tables.session', 'game-tables', history: static fn (?string $cursor): ActivityHistoryPageDTO => new ActivityHistoryPageDTO([], $cursor ?? 'first'));
        $reader = $this->reader();

        $this->assertTrue($reader->history('game-tables.session', null, 10)->isRead());
        $this->assertSame(ActivityReadOutcome::Failed, $reader->history('game-tables.session', 'first', 10)->outcome);
    }

    public function test_a_history_that_throws_fails_and_a_missing_type_is_missing(): void
    {
        $this->register('game-tables.session', 'game-tables', history: static fn (): ActivityHistoryPageDTO => throw new RuntimeException('down'));
        $reader = $this->reader();

        $this->assertSame(ActivityReadOutcome::Failed, $reader->history('game-tables.session', null, 10)->outcome);
        $this->assertSame(ActivityReadOutcome::Missing, $reader->history('forum.post', null, 10)->outcome);
    }

    public function test_subjects_are_described_only_for_the_ids_asked(): void
    {
        $asked = [];
        $this->register('game-tables.session', 'game-tables', describe: static function (array $ids, ?Authenticatable $viewer) use (&$asked): array {
            $asked = $ids;

            return [
                '1' => new ActivitySubjectDTO('1', 'Mesa uno', '/mesas/1'),
                '2' => new ActivitySubjectDTO('3', 'Otra cosa'),
                '9' => new ActivitySubjectDTO('9', 'No pedida'),
                '4' => 'not a subject',
                '5' => new ActivitySubjectDTO('5', 'Enlace raro', 'javascript:alert(1)'),
            ];
        });

        $result = $this->reader()->describe('game-tables.session', ['1', '2', '1', 'not an id', '4', '5'], null);

        $this->assertSame(['1', '2', '4', '5'], $asked);
        $this->assertSame(ActivityReadOutcome::Read, $result->outcome);
        $this->assertSame(['1', '5'], array_map(static fn (ActivitySubjectDTO $subject): string => $subject->subjectId, $result->subjects));
        $this->assertSame('/mesas/1', $result->find('1')?->url);
        // The title stays; a link that is not a site path or https is dropped
        $this->assertSame('Enlace raro', $result->find('5')?->title);
        $this->assertNull($result->find('5')->url);
        $this->assertNull($result->find('2'));
    }

    public function test_describing_nothing_asks_nobody(): void
    {
        $asked = false;
        $this->register('game-tables.session', 'game-tables', describe: static function () use (&$asked): array {
            $asked = true;

            return [];
        });

        $result = $this->reader()->describe('game-tables.session', [], null);

        $this->assertTrue($result->isRead());
        $this->assertSame([], $result->subjects);
        $this->assertFalse($asked);
    }

    public function test_a_describe_that_throws_fails_and_a_missing_type_is_missing(): void
    {
        $this->register('game-tables.session', 'game-tables', describe: static fn (): array => throw new RuntimeException('down'));
        $reader = $this->reader();

        $this->assertSame(ActivityReadOutcome::Failed, $reader->describe('game-tables.session', ['1'], null)->outcome);
        $this->assertSame([], $reader->describe('game-tables.session', ['1'], null)->subjects);
        $this->assertSame(ActivityReadOutcome::Missing, $reader->describe('forum.post', ['1'], null)->outcome);
    }

    public function test_attributes_and_their_values_come_from_sources_that_offer_them(): void
    {
        $this->app->bind('attributes-activity-source', static fn (): ActivitySourceInterface => new class extends FakeActivitySource implements OffersActivityAttributes
        {
            public function attributes(): array
            {
                return [new ActivityAttributeDTO('game_system', 'Sistema de juego')];
            }

            public function attributeValues(string $key): array
            {
                return $key === 'game_system' ? [new ActivityAttributeValueDTO('pf2', 'Pathfinder 2')] : [];
            }
        });
        $this->registry->register('attributes-activity-source', 'example-module');
        $this->register('forum.post', 'forum');
        $reader = $this->reader();

        $this->assertSame(['game_system'], array_map(static fn (ActivityAttributeDTO $attribute): string => $attribute->key, $reader->attributes('example-module.thing')));
        $this->assertSame(['pf2'], array_map(static fn (ActivityAttributeValueDTO $value): string => $value->id, $reader->attributeValues('example-module.thing', 'game_system')));
        $this->assertSame([], $reader->attributeValues('example-module.thing', 'other'));
        $this->assertSame([], $reader->attributes('forum.post'));
        $this->assertSame([], $reader->attributeValues('forum.post', 'game_system'));
        $this->assertSame([], $reader->attributes('missing.type'));
    }

    public function test_attributes_that_throw_or_are_malformed_are_none(): void
    {
        $this->app->bind('broken-attributes-activity-source', static fn (): ActivitySourceInterface => new class extends FakeActivitySource implements OffersActivityAttributes
        {
            public function attributes(): array
            {
                /** @phpstan-ignore return.type (what a module may still return) */
                return ['game_system'];
            }

            public function attributeValues(string $key): array
            {
                throw new RuntimeException('down');
            }
        });
        $this->registry->register('broken-attributes-activity-source', 'example-module');
        $log = Log::spy();
        $reader = $this->reader();

        $this->assertSame([], $reader->attributes('example-module.thing'));
        $this->assertSame([], $reader->attributeValues('example-module.thing', 'game_system'));
        $log->shouldHaveReceived('warning')->twice();
    }

    public function test_sources_are_listed_with_their_labels_leaving_out_those_that_fail(): void
    {
        $this->register('game-tables.session', 'game-tables', label: 'Partidas');
        $this->register('forum.post', 'forum', label: static fn (): string => throw new RuntimeException('down'));

        $sources = $this->reader()->sources();

        $this->assertEquals([new ActivitySourceSummaryDTO('game-tables.session', ActivityKind::GameSession, 'Partidas', 'game-tables')], $sources);
    }

    public function test_each_failure_is_warned_once_a_day_per_type_and_reason(): void
    {
        $this->register('game-tables.session', 'game-tables', facts: static fn (): array => throw new RuntimeException('down'));
        $log = Log::spy();

        foreach ([1, 2] as $job) {
            // A new reader per job, with the same cache
            $reader = $this->reader();
            $reader->facts('game-tables.session', '1');
            $reader->facts('game-tables.session', '2');
        }
        $this->reader()->history('game-tables.session', null, 10);

        $log->shouldHaveReceived('warning')->once()->withArgs(static fn (string $message, array $context): bool => $context['reason'] === 'facts');
    }

    private function reader(): ActivityReader
    {
        return new ActivityReader($this->registry, $this->app, $this->cache);
    }

    /**
     * @param  (Closure(string): array<mixed>)|null  $facts
     * @param  (Closure(?string, int): ActivityHistoryPageDTO)|null  $history
     * @param  (Closure(list<string>, ?Authenticatable): array<mixed>)|null  $describe
     * @param  string|(Closure(): string)  $label
     */
    private function register(string $type, string $module, ?Closure $facts = null, ?Closure $history = null, ?Closure $describe = null, string|Closure $label = 'Cosas'): void
    {
        $id = 'reader-activity-source-'.(++$this->bindings);
        $this->app->bind($id, static fn (): ActivitySourceInterface => new class($type, $facts, $history, $describe, $label) extends FakeActivitySource
        {
            /**
             * @param  (Closure(string): array<mixed>)|null  $factsOf
             * @param  (Closure(?string, int): ActivityHistoryPageDTO)|null  $historyOf
             * @param  (Closure(list<string>, ?Authenticatable): array<mixed>)|null  $describeOf
             * @param  string|(Closure(): string)  $labelOf
             */
            public function __construct(
                private readonly string $sourceType,
                private readonly ?Closure $factsOf,
                private readonly ?Closure $historyOf,
                private readonly ?Closure $describeOf,
                private readonly string|Closure $labelOf,
            ) {}

            public function type(): string
            {
                return $this->sourceType;
            }

            public function label(): string
            {
                return is_string($this->labelOf) ? $this->labelOf : ($this->labelOf)();
            }

            public function facts(string $subjectId): array
            {
                // @phpstan-ignore return.type (what a broken module may return)
                return $this->factsOf instanceof Closure ? ($this->factsOf)($subjectId) : [];
            }

            public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
            {
                return $this->historyOf instanceof Closure ? ($this->historyOf)($cursor, $limit) : new ActivityHistoryPageDTO([], null);
            }

            public function describe(array $subjectIds, ?Authenticatable $viewer): array
            {
                return $this->describeOf instanceof Closure ? ($this->describeOf)($subjectIds, $viewer) : [];
            }
        });
        $this->registry->register($id, $module);
    }

    private function fact(string $userId, ActivityRole $role): ActivityFactDTO
    {
        return new ActivityFactDTO($userId, $role, new DateTimeImmutable('2026-11-20 18:00:00'));
    }
}
