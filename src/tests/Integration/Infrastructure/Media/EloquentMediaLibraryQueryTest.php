<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\MediaFileStatusRule;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaIndexer;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Media\FakeMarkedMediaSource;
use Tests\Support\Media\FakeMediaSource;
use Tests\Support\Media\FakePreviewedMediaSource;
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
        $this->assertNull($stats->lastSyncFailedAt);
        $this->assertSame(0, $stats->files);
        $this->assertSame(0, $stats->bytes);
    }

    public function test_stats_carry_the_last_failed_sync(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set(MediaIndexer::LAST_SYNC_FAILED_KEY, '2026-11-10T03:00:00+00:00');

        $this->assertSame('2026-11-10T03:00:00+00:00', $this->library()->stats()->lastSyncFailedAt?->format(DATE_ATOM));
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

    public function test_apply_reusable_keeps_images_of_marked_sources_only(): void
    {
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->register('link', new FakeMediaSource('link', ['link-page/']));
        $this->register('avatars', new FakeMarkedMediaSource('avatars', ['avatars/'], hidden: true));
        $this->file('events/a');
        $this->file('link-page/b');
        $this->file('unknown/c');
        $this->file('avatars/d');
        $this->file('events/raw', resourceType: 'raw');
        $this->file('events/no-format', format: null);

        $query = $this->library();

        $this->assertSame(['events/a'], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_apply_reusable_respects_excluded_subfolders(): void
    {
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/'], excludedSubfolders: ['content/']));
        $this->file('events/a');
        $this->file('events/content/b');

        $query = $this->library();

        $this->assertSame(['events/a'], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_apply_reusable_never_offers_a_file_with_a_hidden_usage(): void
    {
        // The picker shows thumbnails too: a file whose image a source keeps out of sight is not offered
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->file('events/shown');
        $this->usage('events/shown');
        $this->file('events/secret');
        $this->usage('events/secret', recordId: 'record-1');
        $this->usage('events/secret', hidden: true, recordId: 'record-2');

        $query = $this->library();

        $this->assertSame(['events/shown'], $query->applyReusable($query->query())->pluck('file_id')->all());
        // Still listed, counted and in use in the library itself
        $this->assertSame(['events/secret', 'events/shown'], $query->query()->orderBy('file_id')->pluck('file_id')->all());
        $secret = MediaFileModel::query()->where('file_id', 'events/secret')->firstOrFail();
        $this->assertSame(MediaFileStatus::InUse, $query->statusOf($secret));
    }

    public function test_a_file_of_a_source_that_does_not_show_previews_is_concealed(): void
    {
        $this->registry->clear();
        $this->register('forum', new FakeMediaSource('forum', ['forum/']));
        $this->file('forum/a');
        $this->usage('forum/a');

        $this->assertTrue($this->library()->conceals($this->model('forum/a')));
    }

    public function test_a_file_of_a_source_that_shows_previews_is_not_concealed(): void
    {
        $this->registry->clear();
        $this->register('events', new FakePreviewedMediaSource('events', ['events/']));
        $this->file('events/a');
        $this->usage('events/a');

        $this->assertFalse($this->library()->conceals($this->model('events/a')));
    }

    public function test_a_hidden_usage_conceals_a_file_of_a_source_that_shows_previews(): void
    {
        $this->registry->clear();
        $this->register('events', new FakePreviewedMediaSource('events', ['events/']));
        $this->file('events/secret');
        $this->usage('events/secret', recordId: 'record-1');
        $this->usage('events/secret', hidden: true, recordId: 'record-2');

        $this->assertTrue($this->library()->conceals($this->model('events/secret')));
    }

    public function test_a_file_of_unknown_origin_is_concealed(): void
    {
        $this->registry->clear();
        $this->register('events', new FakePreviewedMediaSource('events', ['events/']));
        $this->file('unknown/a');
        $this->usage('unknown/a');
        $this->file('unknown/b');

        $this->assertTrue($this->library()->conceals($this->model('unknown/a')));
        $this->assertTrue($this->library()->conceals($this->model('unknown/b')));
    }

    public function test_an_unused_file_of_a_folder_that_shows_previews_is_not_concealed(): void
    {
        // The decision is per claiming source: unused files are what gets reviewed before deleting
        $this->registry->clear();
        $this->register('events', new FakePreviewedMediaSource('events', ['events/']));
        $this->file('events/unused');

        $this->assertFalse($this->library()->conceals($this->model('events/unused')));
    }

    public function test_the_source_that_wins_the_folder_decides(): void
    {
        // A nested folder claimed by a source without previews hides its files, and the other way round
        $this->registry->clear();
        $this->register('galleries', new FakePreviewedMediaSource('galleries', ['galleries/']));
        $this->register('covers', new FakeMediaSource('covers', ['galleries/covers/']));
        $this->register('forum', new FakeMediaSource('forum', ['forum/'], excludedSubfolders: ['public/']));
        $this->register('public', new FakePreviewedMediaSource('public', ['forum/public/']));
        $this->file('galleries/a');
        $this->file('galleries/covers/b');
        $this->file('forum/c');
        $this->file('forum/public/d');

        $query = $this->library();

        $this->assertFalse($query->conceals($this->model('galleries/a')));
        $this->assertTrue($query->conceals($this->model('galleries/covers/b')));
        $this->assertTrue($query->conceals($this->model('forum/c')));
        $this->assertFalse($query->conceals($this->model('forum/public/d')));
    }

    public function test_a_file_of_a_hidden_source_is_concealed_even_if_it_shows_previews(): void
    {
        $this->registry->clear();
        $this->register('avatars', new FakePreviewedMediaSource('avatars', ['avatars/'], hidden: true));
        $this->file('avatars/a');

        $this->assertTrue($this->library()->conceals($this->model('avatars/a')));
    }

    public function test_a_source_that_cannot_tell_whether_it_is_hidden_conceals_its_files(): void
    {
        $this->registry->clear();
        $moody = new class('moody', ['moody/']) extends FakeMediaSource implements ShowsMediaPreviews
        {
            public function hidden(): bool
            {
                throw new \RuntimeException('unreadable');
            }
        };
        $this->register('moody', $moody);
        $this->file('moody/a');

        $this->assertTrue($this->library()->conceals($this->model('moody/a')));
    }

    public function test_apply_reusable_never_offers_a_file_of_a_source_that_does_not_show_previews(): void
    {
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $releasing = new class('forum', ['forum/']) extends FakeMediaSource implements ReleasesMediaThroughCoreInterface {};
        $this->register('forum', $releasing);
        $this->file('events/a');
        $this->file('forum/b');

        $query = $this->library();

        $this->assertSame(['events/a'], $query->applyReusable($query->query())->pluck('file_id')->all());
        // Still listed in the library itself
        $this->assertSame(['events/a', 'forum/b'], $query->query()->orderBy('file_id')->pluck('file_id')->all());
    }

    public function test_is_reusable_folder(): void
    {
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->register('link', new FakeMediaSource('link', ['link-page/']));
        $this->register('avatars', new FakeMarkedMediaSource('avatars', ['avatars/'], hidden: true));

        $query = $this->library();

        $this->assertTrue($query->isReusableFolder('events/2026/11/'));
        // A field directory has no trailing slash
        $this->assertTrue($query->isReusableFolder('events'));
        $this->assertFalse($query->isReusableFolder('link-page/2026/11/'));
        $this->assertFalse($query->isReusableFolder('unknown/'));
        $this->assertFalse($query->isReusableFolder('avatars/'));
    }

    public function test_apply_search_matches_usage_title_or_folder(): void
    {
        $this->file('events/2026/a');
        $this->file('hero_slides/b');
        $this->file('galleries/c');
        MediaUsageModel::query()->create([
            'file_id' => 'hero_slides/b',
            'source_type' => 'hero_slides',
            'record_id' => 'record-2',
            'role' => 'Imagen',
            'title' => 'Torneo de otoño',
        ]);

        $query = $this->library();

        $this->assertSame(['hero_slides/b'], $query->applySearch($query->query(), 'otoño')->pluck('file_id')->all());
        $this->assertSame(['events/2026/a'], $query->applySearch($query->query(), 'events/2026')->pluck('file_id')->all());
    }

    private function model(string $id): MediaFileModel
    {
        return MediaFileModel::query()->where('file_id', $id)->firstOrFail();
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

    private function file(string $id, int $bytes = 1000, ?CarbonImmutable $uploadedAt = null, string $resourceType = 'image', ?string $format = 'jpg'): void
    {
        MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => $resourceType,
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => $format,
            'bytes' => $bytes,
            'width' => 800,
            'height' => 600,
            'uploaded_at' => $uploadedAt ?? CarbonImmutable::now()->modify('-30 days'),
            'last_seen_at' => CarbonImmutable::now(),
        ]);
    }

    private function usage(string $fileId, bool $hidden = false, string $recordId = 'record-1'): void
    {
        MediaUsageModel::query()->create([
            'file_id' => $fileId,
            'source_type' => 'events',
            'record_id' => $recordId,
            'role' => 'Imagen',
            'title' => 'Registro',
            'is_hidden' => $hidden,
        ]);
    }
}
