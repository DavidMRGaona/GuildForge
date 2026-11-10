<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Pages\MediaLibraryPage;
use App\Infrastructure\Media\Jobs\SyncMediaLibraryJob;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Media\FakeMediaSource;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

final class MediaLibraryPageTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private InMemoryMediaCatalog $catalog;

    private MediaSourceRegistryInterface $registry;

    /** @var array<string, true> ids of the users the fake source gives an edit link */
    private array $editors = [];

    /** @var array<string, true> files the fake source reports as used right now */
    private array $usedNow = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        config(['media.delete_enabled' => true]);
        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
        $this->registry = $this->app->make(MediaSourceRegistryInterface::class);
        $this->registry->clear();
        $this->registerSource();
    }

    public function test_forbidden_without_view_any(): void
    {
        $this->actingAs($this->editorWithPermissions([]));

        $this->get(MediaLibraryPage::getUrl())->assertForbidden();
    }

    public function test_ok_with_view_any(): void
    {
        $this->file('events/a');
        $this->actingAs($this->editorWithPermissions(['media.view_any']));

        $this->get(MediaLibraryPage::getUrl())
            ->assertOk()
            ->assertSee(__('filament.media_library.title'));
    }

    public function test_navigation_group_and_sort(): void
    {
        $this->assertSame('Contenido', MediaLibraryPage::getNavigationGroup());
        $this->assertSame(40, MediaLibraryPage::getNavigationSort());
        $this->assertSame('Biblioteca de medios', MediaLibraryPage::getNavigationLabel());
        $this->assertStringEndsWith('/admin/biblioteca-de-medios', MediaLibraryPage::getUrl());
    }

    public function test_grid_is_default_and_layout_toggle_switches_to_list(): void
    {
        $this->actingAs($this->viewer());

        $page = Livewire::test(MediaLibraryPage::class)->assertSet('display', 'grid');
        $this->assertNotNull($this->table($page)->getContentGrid());
        $this->assertNull($this->table($page)->getColumn('bytes')?->getVisibleFrom());

        $page->callAction('toggleLayout')->assertSet('display', 'list');
        $this->assertNull($this->table($page)->getContentGrid());
        $this->assertSame('sm', $this->table($page)->getColumn('bytes')?->getVisibleFrom());

        $page->callAction('toggleLayout')->assertSet('display', 'grid');
    }

    public function test_grid_shows_thumbnail_title_origin_and_status(): void
    {
        $file = $this->file('events/a');
        $this->usage('events/a', 'Torneo de otoño', 'record-1');
        $this->usage('events/a', 'Torneo de invierno', 'record-2');
        $this->actingAs($this->viewer());

        $page = Livewire::test(MediaLibraryPage::class);
        // The first use in alphabetical order, so it does not change between visits
        $page->assertTableColumnStateSet('title', 'Torneo de invierno +1', $file)
            ->assertTableColumnStateSet('origin', 'Eventos', $file)
            ->assertTableColumnStateSet('status', 'in_use', $file);

        $thumbnail = $this->table($page)->getColumn('thumbnail');
        $this->assertNotNull($thumbnail);
        $thumbnail->record($file);
        $url = $thumbnail->getState();
        $this->assertIsString($url);
        // A small transformed copy, never the original
        $this->assertMatchesRegularExpression('#/image/upload/[^/]*c_fill[^/]*/site/events/a$#', $url);
    }

    public function test_a_file_without_usages_shows_its_origin_and_upload_day(): void
    {
        $file = $this->file('events/a', uploadedAt: CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->actingAs($this->viewer());

        Livewire::test(MediaLibraryPage::class)
            ->set('display', 'grid')
            ->assertTableColumnStateSet('title', null, $file)
            ->assertTableColumnFormattedStateSet('origin', 'Eventos · subido el 02/11/2026', $file);

        // The list has its own «Subido» column: the origin stays short so the table fits
        $this->listPage()->assertTableColumnFormattedStateSet('origin', 'Eventos', $file);
    }

    public function test_totals_are_shown_above_the_table(): void
    {
        $this->file('events/a', bytes: 1_572_864);
        $this->file('events/b', bytes: 1024);
        $this->usage('events/b', 'Torneo', 'record-1');
        $this->actingAs($this->viewer());

        Livewire::test(MediaLibraryPage::class)
            ->assertSee(__('filament.media_library.stats.files'))
            ->assertSee('1,5 MB')
            ->assertSee(__('filament.media_library.stats.never'));
    }

    public function test_status_filter_unused_lists_only_unused(): void
    {
        $unused = $this->file('events/unused');
        $inUse = $this->file('events/in-use');
        $this->usage('events/in-use', 'Torneo', 'record-1');
        $recent = $this->file('events/recent', uploadedAt: CarbonImmutable::now()->subDay());
        $unknown = $this->file('other/unknown');
        $this->actingAs($this->viewer());

        Livewire::test(MediaLibraryPage::class)
            ->assertCanSeeTableRecords([$unused, $inUse, $recent, $unknown])
            ->filterTable('status', 'unused')
            ->assertCanSeeTableRecords([$unused])
            ->assertCanNotSeeTableRecords([$inUse, $recent, $unknown])
            ->filterTable('status', 'unknown_origin')
            ->assertCanSeeTableRecords([$unknown])
            ->assertCanNotSeeTableRecords([$unused, $inUse, $recent]);
    }

    public function test_origin_filter_offers_visible_sources_and_unknown_origin(): void
    {
        $event = $this->file('events/a');
        $unknown = $this->file('other/b');
        $this->actingAs($this->viewer());

        $page = Livewire::test(MediaLibraryPage::class);
        $filter = $this->table($page)->getFilter('origin');
        $this->assertInstanceOf(SelectFilter::class, $filter);
        $this->assertSame(['events' => 'Eventos', 'unknown' => __('filament.media_library.unknown_origin')], $filter->getOptions());

        $page->filterTable('origin', 'events')
            ->assertCanSeeTableRecords([$event])
            ->assertCanNotSeeTableRecords([$unknown])
            ->filterTable('origin', 'unknown')
            ->assertCanSeeTableRecords([$unknown])
            ->assertCanNotSeeTableRecords([$event]);
    }

    public function test_search_by_usage_title(): void
    {
        $autumn = $this->file('events/a');
        $this->usage('events/a', 'Torneo de otoño', 'record-1');
        $winter = $this->file('events/b');
        $this->usage('events/b', 'Torneo de invierno', 'record-2');
        $folder = $this->file('events/2026/11/c');
        $this->actingAs($this->viewer());

        Livewire::test(MediaLibraryPage::class)
            ->searchTable('DE OTO')
            ->assertCanSeeTableRecords([$autumn])
            ->assertCanNotSeeTableRecords([$winter, $folder])
            ->searchTable('2026/11')
            ->assertCanSeeTableRecords([$folder])
            ->assertCanNotSeeTableRecords([$autumn, $winter]);
    }

    public function test_hidden_folders_are_not_listed(): void
    {
        $visible = $this->file('events/a');
        $avatar = $this->file('avatars/a');
        $this->actingAs($this->viewer());

        Livewire::test(MediaLibraryPage::class)
            ->assertCanSeeTableRecords([$visible])
            ->assertCanNotSeeTableRecords([$avatar]);
    }

    public function test_a_source_that_cannot_tell_whether_it_is_hidden_does_not_break_the_page(): void
    {
        $this->register(new class('moody', ['moody/']) extends FakeMediaSource
        {
            public function hidden(): bool
            {
                throw new \RuntimeException('cannot tell');
            }
        });
        $visible = $this->file('events/a');
        $moody = $this->file('moody/a');
        $this->actingAs($this->deleter());

        $this->get(MediaLibraryPage::getUrl())->assertOk();
        // Taken as hidden: its files are not listed, so they can never be deleted from here
        Livewire::test(MediaLibraryPage::class)
            ->assertCanSeeTableRecords([$visible])
            ->assertCanNotSeeTableRecords([$moody]);
    }

    public function test_clicking_a_record_opens_its_details(): void
    {
        $file = $this->file('events/a');
        $this->actingAs($this->viewer());

        foreach (['grid', 'list'] as $layout) {
            $page = Livewire::test(MediaLibraryPage::class)->set('display', $layout);
            $this->assertSame('viewDetails', $this->table($page)->getRecordAction($file));
            $this->assertStringContainsString(
                "mountTableAction(&#039;viewDetails&#039;, &#039;{$file->id}&#039;)",
                $page->call('loadTable')->html(),
            );

            $page->mountTableAction('viewDetails', $file)
                ->assertActionMounted(TestAction::make('details')->arguments(['file' => $file->id]));
        }
    }

    public function test_details_never_open_for_hidden_or_unknown_files(): void
    {
        $avatar = $this->file('avatars/a');
        $this->actingAs($this->viewer());

        Livewire::test(MediaLibraryPage::class)
            ->mountAction(TestAction::make('details')->arguments(['file' => $avatar->id]))
            ->assertActionNotMounted(TestAction::make('details')->arguments(['file' => $avatar->id]))
            ->assertSet('mountedActions', []);
        Livewire::test(MediaLibraryPage::class)
            ->mountAction(TestAction::make('details')->arguments(['file' => 'missing']))
            ->assertActionNotMounted(TestAction::make('details')->arguments(['file' => 'missing']))
            ->assertSet('mountedActions', []);
    }

    public function test_details_show_usages_and_edit_link_only_when_allowed(): void
    {
        $file = $this->file('events/a', bytes: 2048);
        $this->usage('events/a', 'Torneo de otoño', 'record-1');
        $editor = $this->viewer();
        $this->editors[(string) $editor->id] = true;
        $this->actingAs($editor);

        $html = $this->detailsHtml($file);
        $this->assertStringContainsString('Torneo de otoño', $html);
        $this->assertStringContainsString('Imagen', $html);
        $this->assertStringContainsString('800 × 600', $html);
        $this->assertStringContainsString('2 KB', $html);
        $this->assertStringContainsString('events/', $html);
        $this->assertStringContainsString('https://panel.test/events/record-1/edit', $html);
        $this->assertStringContainsString(__('filament.media_library.details.edit'), $html);

        $this->actingAs($this->viewer());
        $html = $this->detailsHtml($file);
        $this->assertStringContainsString('Torneo de otoño', $html);
        $this->assertStringNotContainsString('https://panel.test/events/record-1/edit', $html);
        $this->assertStringNotContainsString(__('filament.media_library.details.edit'), $html);
    }

    public function test_details_offer_the_original_and_its_url(): void
    {
        $file = $this->file('events/a');
        $this->actingAs($this->viewer());

        $open = $this->footerAction($file, 'openOriginal');
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/site/events/a', $open->getUrl());
        $this->assertTrue($open->shouldOpenUrlInNewTab());
        $this->assertSame('copyUrl', $this->footerAction($file, 'copyUrl')->getName());
    }

    public function test_next_moves_to_the_following_file_in_current_order(): void
    {
        $newest = $this->file('events/newest', bytes: 300, uploadedAt: CarbonImmutable::now()->subDays(10));
        $middle = $this->file('events/middle', bytes: 100, uploadedAt: CarbonImmutable::now()->subDays(20));
        $oldest = $this->file('events/oldest', bytes: 200, uploadedAt: CarbonImmutable::now()->subDays(30));
        $this->actingAs($this->viewer());

        // Newest first by default
        Livewire::test(MediaLibraryPage::class)
            ->callAction([TestAction::make('details')->arguments(['file' => $newest->id]), TestAction::make('next')])
            ->assertActionMounted(TestAction::make('details')->arguments(['file' => $middle->id]));

        // Smallest first: the one after the smallest is the oldest, and back
        $this->listPage()
            ->sortTable('bytes')
            ->callAction([TestAction::make('details')->arguments(['file' => $middle->id]), TestAction::make('next')])
            ->assertActionMounted(TestAction::make('details')->arguments(['file' => $oldest->id]));
        $this->listPage()
            ->sortTable('bytes')
            ->callAction([TestAction::make('details')->arguments(['file' => $oldest->id]), TestAction::make('previous')])
            ->assertActionMounted(TestAction::make('details')->arguments(['file' => $middle->id]));

        // The last one has nothing after it; the first nothing before it
        $this->assertTrue($this->footerAction($newest, 'next', $this->listPage()->sortTable('bytes'))->isDisabled());
        $this->assertFalse($this->footerAction($newest, 'previous', $this->listPage()->sortTable('bytes'))->isDisabled());
        $this->assertTrue($this->footerAction($middle, 'previous', $this->listPage()->sortTable('bytes'))->isDisabled());
    }

    public function test_delete_requires_confirmation_checkbox(): void
    {
        $file = $this->file('events/a');
        $this->actingAs($this->deleter());

        $this->listPage()
            ->callTableAction('delete', $file, ['confirmation' => false])
            ->assertHasTableActionErrors(['confirmation']);
        $this->assertArrayHasKey('events/a', $this->catalog->assets);
        $this->assertNotNull($file->fresh());

        $this->listPage()
            ->callTableAction('delete', $file, ['confirmation' => true])
            ->assertHasNoTableActionErrors()
            ->assertNotified();
        $this->assertArrayNotHasKey('events/a', $this->catalog->assets);
        $this->assertNull($file->fresh());
        $this->assertSame(1, MediaDeletionModel::query()->count());
    }

    public function test_delete_from_the_details_closes_them(): void
    {
        $file = $this->file('events/a');
        $this->actingAs($this->deleter());

        Livewire::test(MediaLibraryPage::class)
            ->callAction([TestAction::make('details')->arguments(['file' => $file->id]), TestAction::make('delete')], ['confirmation' => true])
            ->assertHasNoActionErrors()
            ->assertActionNotMounted(TestAction::make('details')->arguments(['file' => $file->id]))
            ->assertSet('mountedActions', []);

        $this->assertArrayNotHasKey('events/a', $this->catalog->assets);
    }

    public function test_a_refused_file_is_reported_with_its_reason(): void
    {
        $file = $this->file('events/a');
        // A record started using it after the library was read
        $this->usedNow['events/a'] = true;
        $this->actingAs($this->deleter());

        $this->listPage()
            ->callTableAction('delete', $file, ['confirmation' => true])
            ->assertNotified(__('filament.media_library.notifications.nothing_deleted'));

        $this->assertArrayHasKey('events/a', $this->catalog->assets);
        $this->assertNotNull($file->fresh());
    }

    public function test_every_refusal_has_a_text(): void
    {
        foreach (MediaDeletionRefusal::cases() as $refusal) {
            $key = 'filament.media_library.refusals.'.$refusal->value;
            $this->assertNotSame($key, __($key), "Missing text for {$refusal->value}");
        }
    }

    public function test_delete_disabled_when_switch_off(): void
    {
        config(['media.delete_enabled' => false]);
        $file = $this->file('events/a');
        $this->actingAs($this->deleter());

        $page = $this->listPage()->assertSee(__('filament.media_library.deletion_disabled.body'));

        $delete = $this->rowAction($page, 'delete', $file);
        $this->assertFalse($delete->isHidden());
        $this->assertTrue($delete->isDisabled());
        $this->assertSame(__('filament.media_library.disabled_reasons.switch_off'), $delete->getTooltip());

        $footer = $this->footerAction($file, 'delete');
        $this->assertTrue($footer->isDisabled());
        $this->assertSame(__('filament.media_library.disabled_reasons.switch_off'), $footer->getTooltip());

        $bulk = $this->table($page)->getBulkAction('deleteSelected');
        $this->assertInstanceOf(BulkAction::class, $bulk);
        $this->assertTrue($bulk->isDisabled());
    }

    public function test_switch_on_shows_no_notice(): void
    {
        $this->actingAs($this->deleter());

        Livewire::test(MediaLibraryPage::class)->assertDontSee(__('filament.media_library.deletion_disabled.body'));
    }

    public function test_delete_hidden_without_permission(): void
    {
        $file = $this->file('events/a');
        $this->actingAs($this->viewer());

        $page = $this->listPage();
        $this->assertTrue($this->rowAction($page, 'delete', $file)->isHidden());
        $this->assertFalse($this->rowAction($page, 'viewDetails', $file)->isHidden());
        $bulk = $this->table($page)->getBulkAction('deleteSelected');
        $this->assertTrue(! $bulk instanceof BulkAction || $bulk->isHidden());

        // In the details it stays in sight, with the reason
        $footer = $this->footerAction($file, 'delete');
        $this->assertTrue($footer->isDisabled());
        $this->assertSame(__('filament.media_library.disabled_reasons.permission'), $footer->getTooltip());
    }

    public function test_delete_disabled_for_files_that_are_not_unused(): void
    {
        $file = $this->file('events/a');
        $this->usage('events/a', 'Torneo', 'record-1');
        $this->actingAs($this->deleter());

        $delete = $this->rowAction($this->listPage(), 'delete', $file);
        $this->assertTrue($delete->isDisabled());
        $this->assertSame(__('filament.media_library.disabled_reasons.status'), $delete->getTooltip());
        $this->assertTrue($this->footerAction($file, 'delete')->isDisabled());
    }

    public function test_bulk_only_selects_unused(): void
    {
        $unused = $this->file('events/unused', bytes: 1024);
        $other = $this->file('events/other-unused', bytes: 2048);
        $inUse = $this->file('events/in-use');
        $this->usage('events/in-use', 'Torneo', 'record-1');
        $recent = $this->file('events/recent', uploadedAt: CarbonImmutable::now()->subDay());
        $this->actingAs($this->deleter());

        foreach (['grid', 'list'] as $layout) {
            $page = Livewire::test(MediaLibraryPage::class)->set('display', $layout);
            $table = $this->table($page);
            $this->assertInstanceOf(BulkAction::class, $table->getBulkAction('deleteSelected'), "No bulk action in {$layout}");
            $this->assertTrue($table->isRecordSelectable($unused));
            $this->assertFalse($table->isRecordSelectable($inUse));
            $this->assertFalse($table->isRecordSelectable($recent));
        }

        $this->listPage()
            ->mountTableBulkAction('deleteSelected', [$unused, $other])
            ->assertMountedActionModalSee('2 archivos')
            ->assertMountedActionModalSee('3 KB');

        $this->listPage()
            ->callTableBulkAction('deleteSelected', [$unused, $other], ['confirmation' => false])
            ->assertHasTableBulkActionErrors(['confirmation']);
        $this->assertCount(4, $this->catalog->assets);

        $this->listPage()
            ->callTableBulkAction('deleteSelected', [$unused, $other, $inUse], ['confirmation' => true])
            ->assertNotified();

        $this->assertArrayNotHasKey('events/unused', $this->catalog->assets);
        $this->assertArrayNotHasKey('events/other-unused', $this->catalog->assets);
        $this->assertArrayHasKey('events/in-use', $this->catalog->assets);
    }

    public function test_refresh_queues_sync_job(): void
    {
        Queue::fake();
        $this->actingAs($this->viewer());

        Livewire::test(MediaLibraryPage::class)
            ->callAction('refresh')
            ->assertNotified(__('filament.media_library.notifications.refresh_queued'));

        Queue::assertPushed(SyncMediaLibraryJob::class);
    }

    public function test_deletions_visible_only_with_delete_permission(): void
    {
        $deleter = $this->deleter();
        MediaDeletionModel::query()->create([
            'file_id' => 'events/gone',
            'resource_type' => 'image',
            'folder' => 'events/',
            'source_type' => 'events',
            'bytes' => 2048,
            'width' => 800,
            'height' => 600,
            'outcome' => 'deleted',
            'deleted_by' => $deleter->id,
            'deleted_at' => CarbonImmutable::now()->subHour(),
        ]);

        $this->actingAs($this->viewer());
        Livewire::test(MediaLibraryPage::class)->assertActionHidden('deletions');

        $this->actingAs($deleter);
        $page = Livewire::test(MediaLibraryPage::class)
            ->assertActionVisible('deletions')
            ->mountAction('deletions');
        $content = $this->mountedAction($page)->getModalContent();
        $this->assertInstanceOf(View::class, $content);
        $html = $content->render();
        $this->assertStringContainsString('events/gone', $html);
        $this->assertStringContainsString($deleter->name, $html);
        $this->assertStringContainsString('Eventos', $html);
        $this->assertStringContainsString('2 KB', $html);
    }

    public function test_the_help_action_is_the_last_header_action(): void
    {
        $this->actingAs($this->deleter());

        $page = Livewire::test(MediaLibraryPage::class);
        $component = $page->instance();
        $this->assertInstanceOf(MediaLibraryPage::class, $component);
        $names = array_map(
            static fn (Action $action): string => (string) $action->getName(),
            array_values(array_filter($component->getCachedHeaderActions(), static fn (mixed $action): bool => $action instanceof Action)),
        );

        $this->assertSame(['toggleLayout', 'refresh', 'deletions'], array_slice($names, 0, 3));
        if (count($names) > 3) {
            $this->assertSame('contextualHelp', $names[3]);
        }
    }

    private function registerSource(): void
    {
        $test = $this;
        $events = new class('events', ['events/']) extends FakeMediaSource
        {
            /** @var \Closure(string, object): ?string */
            public \Closure $edit;

            /** @var \Closure(MediaFileId): bool */
            public \Closure $uses;

            public function label(): string
            {
                return 'Eventos';
            }

            public function usesFile(MediaFileId $file): bool
            {
                return ($this->uses)($file);
            }

            public function editUrl(string $recordId, object $user): ?string
            {
                return ($this->edit)($recordId, $user);
            }
        };
        $events->edit = static fn (string $recordId, object $user): ?string => $user instanceof UserModel && isset($test->editorIds()[(string) $user->id])
            ? 'https://panel.test/events/'.$recordId.'/edit'
            : null;
        $events->uses = static fn (MediaFileId $file): bool => isset($test->filesUsedNow()[$file->value]);
        $this->register($events);
        $this->register(new FakeMediaSource('avatars', ['avatars/'], hidden: true));
        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => 'site']);
    }

    private function register(MediaUsageSourceInterface $source): void
    {
        $alias = 'test.media.page.'.$source->type();
        $this->app->instance($alias, $source);
        /** @var class-string $alias */
        $this->registry->register($alias);
    }

    /**
     * @return array<string, true>
     */
    public function editorIds(): array
    {
        return $this->editors;
    }

    /**
     * @return array<string, true>
     */
    public function filesUsedNow(): array
    {
        return $this->usedNow;
    }

    private function viewer(): UserModel
    {
        return $this->editorWithPermissions(['media.view_any']);
    }

    private function deleter(): UserModel
    {
        return $this->editorWithPermissions(['media.view_any', 'media.delete']);
    }

    /**
     * @return Testable<MediaLibraryPage>
     */
    private function listPage(): Testable
    {
        // The layout is kept in the session, so each page sets it rather than toggling it
        return Livewire::test(MediaLibraryPage::class)->set('display', 'list');
    }

    /**
     * @param  Testable<MediaLibraryPage>  $page
     */
    private function table(Testable $page): Table
    {
        return $page->instance()->getTable();
    }

    /**
     * Grouped actions cache their visibility by the record's object id: read it with the test's own record.
     *
     * @param  Testable<MediaLibraryPage>  $page
     */
    private function rowAction(Testable $page, string $name, MediaFileModel $file): Action
    {
        $action = $this->table($page)->getAction($name);
        $this->assertInstanceOf(Action::class, $action, "Row action {$name} not found");

        return $action->record($file);
    }

    /**
     * @param  Testable<MediaLibraryPage>|null  $page
     */
    private function detailsAction(MediaFileModel $file, ?Testable $page = null): Action
    {
        $page ??= Livewire::test(MediaLibraryPage::class);
        $page->mountAction(TestAction::make('details')->arguments(['file' => $file->id]));

        return $this->mountedAction($page);
    }

    private function detailsHtml(MediaFileModel $file): string
    {
        $content = $this->detailsAction($file)->getModalContent();
        $this->assertInstanceOf(Htmlable::class, $content);

        return $content->toHtml();
    }

    /**
     * @param  Testable<MediaLibraryPage>|null  $page
     */
    private function footerAction(MediaFileModel $file, string $name, ?Testable $page = null): Action
    {
        foreach ($this->detailsAction($file, $page)->getExtraModalFooterActions() as $action) {
            if ($action instanceof Action && $action->getName() === $name) {
                return $action;
            }
        }

        $this->fail("Footer action {$name} not found");
    }

    /**
     * @param  Testable<MediaLibraryPage>  $page
     */
    private function mountedAction(Testable $page): Action
    {
        $action = $page->instance()->getMountedAction();
        $this->assertInstanceOf(Action::class, $action);

        return $action;
    }

    private function file(string $id, int $bytes = 1000, ?CarbonImmutable $uploadedAt = null): MediaFileModel
    {
        $this->catalog->add($id, $bytes);

        return MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => 'jpg',
            'bytes' => $bytes,
            'width' => 800,
            'height' => 600,
            'uploaded_at' => $uploadedAt ?? CarbonImmutable::now()->subMonth(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);
    }

    private function usage(string $fileId, string $title, string $recordId): void
    {
        MediaUsageModel::query()->create([
            'file_id' => $fileId,
            'source_type' => 'events',
            'record_id' => $recordId,
            'role' => 'Imagen',
            'title' => $title,
        ]);
    }
}
