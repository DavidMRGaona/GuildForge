<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Services\MediaLibraryQueryInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\MediaFileStatusRule;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaIndexer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Media\FakeMediaSource;
use Tests\TestCase;

final class EloquentMediaLibraryQueryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MediaSourceRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->registry = $this->app->make(MediaSourceRegistryInterface::class);
        $this->registry->clear();
        $this->source('events', ['events/']);
        $this->source('hero_slides', ['hero_slides/']);
        // Claims articles/ except articles/content/, which a nested source claims in part
        $this->source('articles', ['articles/'], excluded: ['content/']);
        $this->source('covers', ['galleries/covers/']);
        $this->source('galleries', ['galleries/']);
        $this->source('avatars', ['avatars/'], hidden: true);
    }

    public function test_sql_status_matches_domain_rule(): void
    {
        $now = CarbonImmutable::now();
        $expected = [
            'events/in-use' => MediaFileStatus::InUse,
            'events/old' => MediaFileStatus::Unused,
            // Exactly seven days old: the grace period is over
            'events/boundary' => MediaFileStatus::Unused,
            'events/one-second-short' => MediaFileStatus::Recent,
            'events/new' => MediaFileStatus::Recent,
            // An underscore is not a wildcard
            'hero_slides/old' => MediaFileStatus::Unused,
            'heroXslides/old' => MediaFileStatus::UnknownOrigin,
            // Folders are case sensitive, also on SQLite
            'EVENTS/old' => MediaFileStatus::UnknownOrigin,
            'other/old' => MediaFileStatus::UnknownOrigin,
            'other/in-use' => MediaFileStatus::InUse,
            'articles/old' => MediaFileStatus::Unused,
            'articles/content/old' => MediaFileStatus::UnknownOrigin,
            'galleries/covers/new' => MediaFileStatus::Recent,
            'galleries/old' => MediaFileStatus::Unused,
        ];
        $uploaded = [
            'events/boundary' => $now->modify('-7 days'),
            'events/one-second-short' => $now->modify('-7 days')->modify('+1 second'),
            'events/new' => $now->modify('-1 day'),
            'galleries/covers/new' => $now->modify('-2 days'),
        ];

        foreach (array_keys($expected) as $id) {
            $this->file($id, uploadedAt: $uploaded[$id] ?? $now->modify('-30 days'));
        }
        $this->usage('events/in-use');
        $this->usage('other/in-use');

        $query = $this->library();
        $files = $query->query()->get()->keyBy('file_id');

        foreach ($expected as $id => $status) {
            $file = $files[$id];
            $this->assertInstanceOf(MediaFileModel::class, $file);
            $rule = MediaFileStatusRule::for(
                hasUsages: MediaUsageModel::query()->where('file_id', $id)->exists(),
                claimedByActiveSource: $this->registry->claimingSource(new MediaFileId($id)) instanceof MediaUsageSourceInterface,
                uploadedAt: $file->uploaded_at->toImmutable(),
                now: $now,
            );

            $this->assertSame($status, $rule, "Fixture for {$id} does not match the domain rule");
            $this->assertSame($rule, $query->statusOf($file), "statusOf() differs for {$id}");
        }

        foreach (MediaFileStatus::cases() as $status) {
            $listed = $query->applyStatus($query->query(), $status)->pluck('file_id')->sort()->values()->all();
            $wanted = collect($expected)->filter(fn (MediaFileStatus $s): bool => $s === $status)->keys()->sort()->values()->all();

            $this->assertSame($wanted, $listed, "applyStatus() differs for {$status->value}");
        }
    }

    public function test_status_of_reads_the_usage_count_when_it_is_not_loaded(): void
    {
        $this->file('events/in-use');
        $this->usage('events/in-use');

        $file = MediaFileModel::query()->where('file_id', 'events/in-use')->firstOrFail();

        $this->assertSame(MediaFileStatus::InUse, $this->library()->statusOf($file));
    }

    public function test_origin_filter_follows_the_claiming_source(): void
    {
        foreach (['events/a', 'galleries/b', 'galleries/covers/c', 'articles/d', 'articles/content/e', 'other/f'] as $id) {
            $this->file($id);
        }

        $query = $this->library();
        $origin = fn (?string $type): array => $query->applyOrigin($query->query(), $type)->orderBy('file_id')->pluck('file_id')->all();

        $this->assertSame(['events/a'], $origin('events'));
        // The longest claimed folder wins
        $this->assertSame(['galleries/b'], $origin('galleries'));
        $this->assertSame(['galleries/covers/c'], $origin('covers'));
        $this->assertSame(['articles/d'], $origin('articles'));
        $this->assertSame(['articles/content/e', 'other/f'], $origin(null));
        $this->assertSame([], $origin('avatars'));
        $this->assertSame([], $origin('unknown-type'));
    }

    public function test_hidden_folders_are_excluded_from_query_and_stats(): void
    {
        $this->file('events/a', bytes: 100);
        $this->file('avatars/b', bytes: 5000);

        $query = $this->library();

        $this->assertSame(['events/a'], $query->query()->pluck('file_id')->all());
        $stats = $query->stats();
        $this->assertSame(1, $stats->files);
        $this->assertSame(100, $stats->bytes);
        $this->assertSame(1, $stats->unusedFiles);
        $this->assertSame(100, $stats->unusedBytes);
    }

    public function test_stats_sum_unused_bytes(): void
    {
        $now = CarbonImmutable::now();
        $this->file('events/unused-1', bytes: 1000);
        $this->file('events/unused-2', bytes: 2500);
        $this->file('events/in-use', bytes: 4000);
        $this->usage('events/in-use');
        $this->file('events/recent', bytes: 8000, uploadedAt: $now->modify('-1 day'));
        $this->file('other/unknown', bytes: 16000);

        $stats = $this->library()->stats();

        $this->assertSame(5, $stats->files);
        $this->assertSame(31500, $stats->bytes);
        $this->assertSame(2, $stats->unusedFiles);
        $this->assertSame(3500, $stats->unusedBytes);
        $this->assertNull($stats->lastSyncedAt);
    }

    public function test_stats_carry_the_last_sync(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set(MediaIndexer::LAST_SYNCED_KEY, '2026-11-09T03:00:00+00:00');

        $stats = $this->library()->stats();

        $this->assertSame('2026-11-09T03:00:00+00:00', $stats->lastSyncedAt?->format(DATE_ATOM));
        $this->assertSame(0, $stats->files);
        $this->assertSame(0, $stats->bytes);
    }

    public function test_a_source_whose_folders_cannot_be_read_claims_nothing(): void
    {
        $broken = new class('broken', ['broken/']) extends FakeMediaSource
        {
            public function excludedSubfolders(): array
            {
                throw new \RuntimeException('unreadable');
            }
        };
        $this->register('broken', $broken);
        $this->file('broken/a');

        $query = $this->library();
        $file = MediaFileModel::query()->where('file_id', 'broken/a')->firstOrFail();

        $this->assertSame(MediaFileStatus::UnknownOrigin, $query->statusOf($file));
        $this->assertSame(['broken/a'], $query->applyStatus($query->query(), MediaFileStatus::UnknownOrigin)->pluck('file_id')->all());
    }

    private function library(): MediaLibraryQueryInterface
    {
        return $this->app->make(MediaLibraryQueryInterface::class);
    }

    /**
     * @param  list<string>  $folders
     * @param  list<string>  $excluded
     */
    private function source(string $type, array $folders, bool $hidden = false, array $excluded = []): void
    {
        $this->register($type, new FakeMediaSource($type, $folders, $hidden, $excluded));
    }

    private function register(string $type, MediaUsageSourceInterface $source): void
    {
        $alias = 'test.media.library.'.$type;
        $this->app->instance($alias, $source);
        /** @var class-string $alias */
        $this->registry->register($alias);
    }

    private function file(string $id, int $bytes = 1000, ?CarbonImmutable $uploadedAt = null): void
    {
        MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => 'jpg',
            'bytes' => $bytes,
            'width' => 800,
            'height' => 600,
            'uploaded_at' => $uploadedAt ?? CarbonImmutable::now()->modify('-30 days'),
            'last_seen_at' => CarbonImmutable::now(),
        ]);
    }

    private function usage(string $fileId): void
    {
        MediaUsageModel::query()->create([
            'file_id' => $fileId,
            'source_type' => 'events',
            'record_id' => 'record-1',
            'role' => 'Imagen',
            'title' => 'Registro',
        ]);
    }
}
