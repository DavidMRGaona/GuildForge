<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Knowledge;

use App\Application\Knowledge\DTOs\KnowledgeIndexResultDTO;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Search\NullKnowledgeIndexer;
use App\Infrastructure\Knowledge\Search\ScoutKnowledgeIndexer;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Knowledge\KnowledgeFixtures;
use Tests\TestCase;

final class KnowledgeSyncCommandTest extends TestCase
{
    use KnowledgeFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKnowledgeSandbox();
    }

    protected function tearDown(): void
    {
        $this->tearDownKnowledgeSandbox();
        parent::tearDown();
    }

    public function test_command_prints_the_report_and_succeeds_with_errors(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->writeFile($this->corePath().'/es/broken.md', "nothing\n");

        $this->artisanCommand('knowledge:sync')
            ->expectsOutputToContain('Inserted: 1')
            ->expectsOutputToContain('broken.md')
            ->assertSuccessful();
    }

    private function resolvedClass(string $abstract): string
    {
        $instance = $this->app->make($abstract);
        $this->assertIsObject($instance);

        return $instance::class;
    }

    private function indexerReturning(KnowledgeIndexResultDTO $result): void
    {
        $this->app->instance(KnowledgeIndexerInterface::class, new readonly class($result) implements KnowledgeIndexerInterface
        {
            public function __construct(private KnowledgeIndexResultDTO $result) {}

            public function reindex(): KnowledgeIndexResultDTO
            {
                return $this->result;
            }

            public function refresh(GuideId $id): void {}

            public function forget(GuideId $id): void {}
        });
    }

    public function test_sync_reindexes_the_guides_after_synchronizing(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->indexerReturning(new KnowledgeIndexResultDTO(3, 1, null));

        $this->artisanCommand('knowledge:sync')
            ->expectsOutputToContain('Indexed: 3')
            ->expectsOutputToContain('Removed from the index: 1')
            ->assertSuccessful();
    }

    public function test_sync_succeeds_and_warns_when_the_index_is_unreachable(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->indexerReturning(new KnowledgeIndexResultDTO(0, 0, 'Connection refused'));

        $this->artisanCommand('knowledge:sync')
            ->expectsOutputToContain('Inserted: 1')
            ->expectsOutputToContain('Search index not updated: Connection refused')
            ->assertSuccessful();

        $this->assertTrue(KnowledgeGuideModel::query()->where('guide_id', 'account.one')->where('active', true)->exists());
    }

    public function test_the_indexer_follows_the_scout_driver(): void
    {
        config(['scout.driver' => 'null']);
        $this->assertSame(NullKnowledgeIndexer::class, $this->resolvedClass(KnowledgeIndexerInterface::class));

        config(['scout.driver' => 'meilisearch']);
        $this->assertSame(ScoutKnowledgeIndexer::class, $this->resolvedClass(KnowledgeIndexerInterface::class));
    }

    public function test_an_unreachable_meilisearch_does_not_fail_the_sync(): void
    {
        config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://127.0.0.1:1', 'scout.meilisearch.key' => 'none']);
        $this->writeCoreGuide('a.md', 'account.one');

        $this->artisanCommand('knowledge:sync')
            ->expectsOutputToContain('Search index not updated')
            ->assertSuccessful();

        $this->assertTrue(KnowledgeGuideModel::query()->where('guide_id', 'account.one')->exists());
    }

    public function test_sync_is_scheduled_daily_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(
            fn (Event $event): bool => str_contains($event->command ?? '', 'knowledge:sync')
                && $event->expression === '0 0 * * *'
                && $event->withoutOverlapping
        ));
    }
}
