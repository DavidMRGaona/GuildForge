<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\Contracts\ConcealsUnusedMediaFiles;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\MediaFileStatusRule;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaIndexer;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use App\Infrastructure\Media\Services\MediaSourceSyncState;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Support\Media\FakeConcealingUnusedMediaSource;
use Tests\Support\Media\FakeMarkedMediaSource;
use Tests\Support\Media\FakeMediaSource;
use Tests\Support\Media\FakePreviewedMediaSource;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\Support\Media\MarksMediaSourcesSynced;
use Tests\TestCase;

final class EloquentMediaLibraryQueryTest extends TestCase
{
    use LazilyRefreshDatabase;
    use MarksMediaSourcesSynced;

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

    public function test_an_unused_file_of_a_source_that_conceals_unused_files_is_concealed(): void
    {
        // The images of a deleted thread in a members-only category stay behind without any usage (core#843)
        $this->registry->clear();
        $this->register('forum', new FakeConcealingUnusedMediaSource('forum', ['forum/']));
        $this->file('forum/orphan');

        $query = $this->library();
        $orphan = $this->model('forum/orphan');

        $this->assertTrue($query->conceals($orphan));
        // Still unused, listed and counted: only what the library shows changes
        $this->assertSame(MediaFileStatus::Unused, $query->statusOf($orphan));
        $this->assertSame(['forum/orphan'], $query->applyStatus($query->query(), MediaFileStatus::Unused)->pluck('file_id')->all());
        $this->assertSame(1, $query->stats()->unusedFiles);
    }

    public function test_a_used_file_of_a_source_that_conceals_unused_files_follows_its_usages(): void
    {
        $this->registry->clear();
        $this->register('forum', new FakeConcealingUnusedMediaSource('forum', ['forum/']));
        $this->file('forum/shown');
        $this->usage('forum/shown', sourceType: 'forum');
        $this->file('forum/secret');
        $this->usage('forum/secret', hidden: true, sourceType: 'forum');

        $query = $this->library();

        $this->assertFalse($query->conceals($this->model('forum/shown')));
        $this->assertTrue($query->conceals($this->model('forum/secret')));
    }

    public function test_an_unused_file_of_a_source_without_the_marker_stays_visible(): void
    {
        // Unused files of public sources are what gets reviewed before deleting
        $this->registry->clear();
        $this->register('forum', new FakeConcealingUnusedMediaSource('forum', ['forum/']));
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->file('events/unused');

        $query = $this->library();

        $this->assertFalse($query->conceals($this->model('events/unused')));
        $this->assertSame(['events/unused'], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_any_visible_source_claiming_an_unused_file_may_conceal_it(): void
    {
        // Not only the source that wins the folder: a parent that conceals its unused files keeps a nested
        // folder's unused files out of sight too, unless it excludes that folder
        $this->registry->clear();
        $this->register('forum', new FakeConcealingUnusedMediaSource('forum', ['forum/'], excludedSubfolders: ['public/']));
        $this->register('public', new FakeMarkedMediaSource('public', ['forum/public/']));
        $this->register('characters', new FakeMarkedMediaSource('characters', ['forum/characters/']));
        $this->file('forum/public/a');
        $this->file('forum/characters/b');

        $query = $this->library();

        $this->assertFalse($query->conceals($this->model('forum/public/a')));
        $this->assertTrue($query->conceals($this->model('forum/characters/b')));
        $this->assertSame(['forum/public/a'], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_a_nested_source_that_conceals_unused_files_leaves_its_parent_alone(): void
    {
        $this->registry->clear();
        $this->register('forum', new FakeMarkedMediaSource('forum', ['forum/']));
        $this->register('characters', new FakeConcealingUnusedMediaSource('characters', ['forum/characters/']));
        $this->file('forum/a');
        $this->file('forum/characters/b');

        $query = $this->library();

        $this->assertFalse($query->conceals($this->model('forum/a')));
        $this->assertTrue($query->conceals($this->model('forum/characters/b')));
        $this->assertSame(['forum/a'], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_a_source_that_conceals_unused_files_on_a_shared_folder_conceals_them_winning_or_losing_the_tie(): void
    {
        foreach (['wins' => ['concealing', 'shown'], 'loses' => ['shown', 'concealing']] as $case => $order) {
            $this->registry->clear();
            MediaUsageModel::query()->delete();
            MediaFileModel::query()->delete();

            foreach ($order as $type) {
                $this->register($type, $type === 'concealing'
                    ? new FakeConcealingUnusedMediaSource($type, ['shared/'])
                    : new FakeMarkedMediaSource($type, ['shared/']));
            }

            $this->file('shared/unused');
            $this->file('shared/used');
            $this->usage('shared/used', sourceType: 'shown');

            $query = $this->library();

            $this->assertTrue($query->conceals($this->model('shared/unused')), $case);
            $this->assertFalse($query->conceals($this->model('shared/used')), $case);
            $this->assertSame(['shared/used'], $query->applyReusable($query->query())->pluck('file_id')->all(), $case);
        }
    }

    public function test_conceals_and_apply_reusable_agree_on_unused_files(): void
    {
        $this->registry->clear();
        $this->register('forum', new FakeConcealingUnusedMediaSource('forum', ['forum/'], excludedSubfolders: ['public/']));
        $this->register('public', new FakeMarkedMediaSource('public', ['forum/public/']));
        $this->register('characters', new FakeMarkedMediaSource('characters', ['forum/characters/']));
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->register('deep', new FakeConcealingUnusedMediaSource('deep', ['events/deep/']));

        foreach (['forum/a', 'forum/b', 'forum/public/a', 'forum/public/b', 'forum/characters/a', 'forum/characters/b', 'events/a', 'events/b', 'events/deep/a', 'events/deep/b', 'events/deep/c'] as $id) {
            $this->file($id);
        }

        $this->usage('forum/b', sourceType: 'forum');
        $this->usage('forum/public/b', sourceType: 'public');
        $this->usage('forum/characters/b', sourceType: 'events');
        $this->usage('events/b', sourceType: 'events');
        $this->usage('events/deep/b', sourceType: 'deep');
        $this->usage('events/deep/c', hidden: true, sourceType: 'deep');

        $query = $this->library();
        $reusable = $query->applyReusable($query->query())->orderBy('file_id')->pluck('file_id')->all();

        $this->assertSame(['events/a', 'events/b', 'events/deep/b', 'forum/b', 'forum/characters/b', 'forum/public/a', 'forum/public/b'], $reusable);

        foreach ($query->query()->with('usages')->get() as $file) {
            $this->assertSame(! in_array($file->file_id, $reusable, true), $query->conceals($file), $file->file_id);
        }
    }

    public function test_a_source_that_stops_telling_whether_it_is_hidden_keeps_concealing_its_unused_files(): void
    {
        // Whether a source is hidden is read once, by the registry: a later call that throws in the same
        // request must not drop the claim, nor what it conceals
        $this->registry->clear();
        $forum = new class('forum', ['forum/']) extends FakeMediaSource implements ConcealsUnusedMediaFiles, ReleasesMediaThroughCoreInterface, ShowsMediaPreviews
        {
            public bool $fails = false;

            public function hidden(): bool
            {
                return $this->fails ? throw new \RuntimeException('unreadable') : false;
            }
        };
        $this->register('forum', $forum);
        $this->register('public', new FakeMarkedMediaSource('public', ['forum/public/']));
        $this->file('forum/public/a');
        $this->registry->sources();
        $forum->fails = true;

        $query = $this->library();

        $this->assertTrue($query->conceals($this->model('forum/public/a')));
        $this->assertSame([], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_every_visible_source_claiming_a_file_must_show_it(): void
    {
        // A nested folder claimed by a source without previews hides its files; one excluded from a parent
        // without previews is not the parent's, so a nested source that shows them decides alone
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

    public function test_a_parent_folder_without_previews_conceals_a_nested_folder_that_has_them(): void
    {
        // The nested source wins the folder, but the parent claims the file too and would not show it
        $this->registry->clear();
        $this->register('forum', new FakeMediaSource('forum', ['forum/']));
        $this->register('public', new FakeMarkedMediaSource('public', ['forum/public/']));
        $this->file('forum/public/a');

        $query = $this->library();

        $this->assertTrue($query->conceals($this->model('forum/public/a')));
        $this->assertSame([], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_two_sources_claiming_the_same_folder_must_both_show_it(): void
    {
        // The first registered wins the folder; the second still claims the file
        $this->registry->clear();
        $this->register('first', new FakeMarkedMediaSource('first', ['shared/']));
        $this->register('second', new FakeMediaSource('second', ['shared/']));
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->register('copy', new FakeMarkedMediaSource('copy', ['events/']));
        $this->file('shared/a');
        $this->file('events/b');

        $query = $this->library();

        $this->assertTrue($query->conceals($this->model('shared/a')));
        $this->assertFalse($query->conceals($this->model('events/b')));
        $this->assertSame(['events/b'], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_a_source_never_synced_since_it_is_as_it_is_now_conceals_its_files(): void
    {
        // Rows written before the source declared ShowsMediaPreviews may carry a stale `is_hidden`
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->file('events/a');
        $state = $this->app->make(MediaSourceSyncState::class);

        // Last synced as another source (before it declared the marker, say)
        $state->record(['events' => $state->fingerprints([new FakeMediaSource('events', ['events/'])])['events']], []);
        $this->assertTrue($this->library()->conceals($this->model('events/a')));
        $this->assertSame([], $this->library()->applyReusable($this->library()->query())->pluck('file_id')->all());

        $this->app->make(SettingsServiceInterface::class)->set(MediaSourceSyncState::KEY, '');
        $this->assertTrue($this->library()->conceals($this->model('events/a')));

        $this->app->make(SettingsServiceInterface::class)->set(MediaSourceSyncState::KEY, 'not json');
        $this->assertTrue($this->library()->conceals($this->model('events/a')));

        $this->markMediaSourcesSynced();
        $this->assertFalse($this->library()->conceals($this->model('events/a')));
        $this->assertSame(['events/a'], $this->library()->applyReusable($this->library()->query())->pluck('file_id')->all());
    }

    public function test_a_usage_of_a_source_not_up_to_date_conceals_the_file(): void
    {
        // A source without folders (the rich text one) only gives usages: their `is_hidden` may be stale too
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->register('texts', new FakeMarkedMediaSource('texts', []));
        $this->file('events/a');
        $this->usage('events/a', sourceType: 'texts');
        $query = $this->library();
        $this->assertFalse($query->conceals($this->model('events/a')));

        $this->app->make(MediaSourceSyncState::class)->record([], ['texts']);

        $query = $this->library();
        $this->assertTrue($query->conceals($this->model('events/a')));
        $this->assertTrue($query->conceals(MediaFileModel::query()->with('usages')->where('file_id', 'events/a')->firstOrFail()));
        $this->assertSame([], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_a_usage_of_a_type_without_an_active_source_conceals_the_file(): void
    {
        // A disabled module: its rows can no longer be refreshed
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->file('events/a');
        $this->usage('events/a', sourceType: 'gone.module');

        $query = $this->library();

        $this->assertTrue($query->conceals($this->model('events/a')));
        $this->assertSame([], $query->applyReusable($query->query())->pluck('file_id')->all());
    }

    public function test_a_new_version_of_the_module_conceals_its_files_until_it_is_synced(): void
    {
        // The same class may hide more in a new release: rows of the old one are not trusted
        $this->registry->clear();
        ModuleModel::factory()->enabled()->create(['name' => 'shop', 'version' => '1.0.0']);
        $this->register('shop.items', new FakeMarkedMediaSource('shop.items', ['shop/']), module: 'shop');
        $this->file('shop/a');
        $this->assertFalse($this->library()->conceals($this->model('shop/a')));

        ModuleModel::query()->where('name', 'shop')->update(['version' => '1.1.0']);

        $this->assertTrue($this->library()->conceals($this->model('shop/a')));
        $this->markMediaSourcesSynced();
        $this->assertFalse($this->library()->conceals($this->model('shop/a')));
    }

    public function test_a_new_core_version_conceals_the_files_of_core_sources_until_they_are_synced(): void
    {
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->file('events/a');
        $this->assertFalse($this->library()->conceals($this->model('events/a')));

        $core = Mockery::mock(CoreVersionServiceInterface::class);
        $core->shouldReceive('getCurrentVersion')->andReturn(ModuleVersion::fromString('99.0.0'));
        $this->app->instance(CoreVersionServiceInterface::class, $core);

        $this->assertTrue($this->library()->conceals($this->model('events/a')));
    }

    public function test_a_source_whose_last_sync_failed_conceals_its_files(): void
    {
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->file('events/a');

        $this->app->make(MediaSourceSyncState::class)->record([], ['events']);

        $this->assertTrue($this->library()->conceals($this->model('events/a')));
    }

    public function test_a_failed_refresh_conceals_the_files_of_its_type_until_the_next_good_sync(): void
    {
        // core#844: a refresh that failed may have left a usage shown that the source now hides
        $this->registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->register('articles', new FakeMarkedMediaSource('articles', ['articles/']));
        $this->file('events/a');
        $this->file('articles/a');
        $catalog = new InMemoryMediaCatalog;
        $catalog->add('events/a');
        $catalog->add('articles/a');
        $this->app->instance(MediaCatalogInterface::class, $catalog);

        $this->app->make(MediaSourceSyncState::class)->markRefreshFailed('events');

        $query = $this->library();
        $this->assertTrue($query->conceals($this->model('events/a')));
        $this->assertFalse($query->conceals($this->model('articles/a')));
        $this->assertSame(['articles/a'], $query->applyReusable($query->query())->pluck('file_id')->all());

        $this->app->make(MediaIndexerInterface::class)->sync();

        $query = $this->library();
        $this->assertFalse($query->conceals($this->model('events/a')));
        $this->assertSame(['articles/a', 'events/a'], $query->applyReusable($query->query())->orderBy('file_id')->pluck('file_id')->all());
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

    public function test_apply_reusable_never_offers_an_unused_file_of_a_source_that_conceals_unused_files(): void
    {
        $this->registry->clear();
        $this->register('forum', new FakeConcealingUnusedMediaSource('forum', ['forum/']));
        $this->file('forum/orphan');
        $this->file('forum/used');
        $this->usage('forum/used', sourceType: 'forum');

        $query = $this->library();

        $this->assertSame(['forum/used'], $query->applyReusable($query->query())->pluck('file_id')->all());
        // Still listed in the library itself
        $this->assertSame(['forum/orphan', 'forum/used'], $query->query()->orderBy('file_id')->pluck('file_id')->all());
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

    private function register(string $type, MediaUsageSourceInterface $source, ?string $module = null): void
    {
        $alias = 'test.media.library.'.$type;
        $this->app->instance($alias, $source);
        /** @var class-string $alias */
        $this->registry->register($alias, $module);
        $this->markMediaSourcesSynced();
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

    private function usage(string $fileId, bool $hidden = false, string $recordId = 'record-1', string $sourceType = 'events'): void
    {
        MediaUsageModel::query()->create([
            'file_id' => $fileId,
            'source_type' => $sourceType,
            'record_id' => $recordId,
            'role' => 'Imagen',
            'title' => 'Registro',
            'is_hidden' => $hidden,
        ]);
    }
}
