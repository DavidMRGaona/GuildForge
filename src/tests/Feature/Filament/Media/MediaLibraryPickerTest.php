<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Media;

use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Filament\Forms\Components\MediaLibraryBrowser;
use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Filament\MediaPickerFixturePage;
use Tests\Support\Media\FakeMarkedMediaSource;
use Tests\Support\Media\FakeMediaSource;
use Tests\TestCase;

final class MediaLibraryPickerTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        MediaPickerFixturePage::$constrain = null;
        MediaPickerFixturePage::$storedPaths = null;
        MediaPickerFixturePage::$directory = 'events/2026/11';
        $registry = $this->app->make(MediaSourceRegistryInterface::class);
        $registry->clear();
        $this->register('events', new FakeMarkedMediaSource('events', ['events/']));
        $this->register('articles', new FakeMarkedMediaSource('articles', ['articles/']));
        $this->register('link', new FakeMediaSource('link', ['link-page/']));
        $this->register('avatars', new FakeMarkedMediaSource('avatars', ['avatars/'], hidden: true));
        $this->actingAs($this->editorWithPermissions(['media.view_any']));
    }

    protected function tearDown(): void
    {
        MediaPickerFixturePage::$constrain = null;
        MediaPickerFixturePage::$storedPaths = null;

        parent::tearDown();
    }

    public function test_action_is_hidden_without_media_view_any(): void
    {
        $this->actingAs($this->editorWithPermissions([]));

        $this->assertCannotPick(Livewire::test(MediaPickerFixturePage::class));
    }

    public function test_action_is_visible_with_media_view_any(): void
    {
        Livewire::test(MediaPickerFixturePage::class)->assertActionVisible($this->pick());
    }

    public function test_action_is_hidden_when_no_marked_source_claims_the_field_directory(): void
    {
        MediaPickerFixturePage::$directory = 'link-page/2026/11';

        $this->assertCannotPick(Livewire::test(MediaPickerFixturePage::class));
    }

    public function test_browser_lists_only_what_the_field_accepts(): void
    {
        $this->image('events/a');
        $this->image('articles/b', format: 'png');
        $this->image('events/webp', format: 'webp');
        $this->image('events/big', bytes: 3 * 1024 * 1024);
        $this->image('link-page/c');
        $this->image('unknown/d');
        $this->image('avatars/e');

        $html = $this->browserHtml(Livewire::test(MediaPickerFixturePage::class)->mountAction($this->pick()));

        $this->assertStringContainsString('data-media-file="events/a"', $html);
        $this->assertStringContainsString('data-media-file="articles/b"', $html);
        foreach (['events/webp', 'events/big', 'link-page/c', 'unknown/d', 'avatars/e'] as $hidden) {
            $this->assertStringNotContainsString('data-media-file="'.$hidden.'"', $html, $hidden);
        }
    }

    public function test_browser_searches_by_usage_title(): void
    {
        $this->image('events/a');
        $this->image('events/b');
        MediaUsageModel::query()->create([
            'file_id' => 'events/b',
            'source_type' => 'events',
            'record_id' => 'r1',
            'role' => 'Imagen',
            'title' => 'Torneo de otoño',
        ]);

        $page = Livewire::test(MediaPickerFixturePage::class)
            ->mountAction($this->pick())
            ->setActionData(['search' => 'otoño']);
        $html = $this->browserHtml($page);

        $this->assertStringContainsString('data-media-file="events/b"', $html);
        $this->assertStringNotContainsString('data-media-file="events/a"', $html);
    }

    public function test_choosing_a_file_fills_the_state_with_a_path_resolving_to_the_same_id(): void
    {
        $this->image('articles/2026/11/shared', format: 'png');

        $page = Livewire::test(MediaPickerFixturePage::class)
            ->callAction($this->pick(), data: ['file' => 'articles/2026/11/shared'])
            ->assertHasNoActionErrors();

        /** @var array<string, string> $state */
        $state = $page->get('data.image_public_id');
        $this->assertSame(['articles/2026/11/shared.png'], array_values($state));
        $id = $this->app->make(MediaFileIdResolverInterface::class)->fromStoredPath((string) array_first($state));
        $this->assertSame('articles/2026/11/shared', $id?->value);

        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame('articles/2026/11/shared.png', $page->get('saved')['image_public_id']);
    }

    public function test_submitting_without_a_choice_is_refused(): void
    {
        Livewire::test(MediaPickerFixturePage::class)
            ->callAction($this->pick(), data: ['file' => null])
            ->assertHasActionErrors(['file' => 'required']);
    }

    public function test_choosing_a_file_the_field_does_not_accept_keeps_the_state(): void
    {
        $this->image('link-page/c');

        $page = Livewire::test(MediaPickerFixturePage::class)
            ->callAction($this->pick(), data: ['file' => 'link-page/c'])
            ->assertNotified(__('filament.media_picker.errors.unavailable'));

        $this->assertSame([], array_values((array) $page->get('data.image_public_id')));
    }

    public function test_save_rejects_a_forged_path_outside_the_library(): void
    {
        $this->assertSaveRejected('events/2026/11/forged.jpg', missing: true);
    }

    public function test_save_rejects_a_hidden_or_unmarked_or_oversized_path(): void
    {
        $this->image('avatars/e');
        $this->image('link-page/c');
        $this->image('events/big', bytes: 3 * 1024 * 1024);
        $this->image('events/webp', format: 'webp');

        foreach (['avatars/e.jpg', 'link-page/c.jpg', 'events/big.jpg', 'events/webp.webp'] as $path) {
            $this->assertSaveRejected($path, missing: false);
        }
    }

    public function test_save_rejects_a_library_path_without_media_view_any(): void
    {
        $this->image('events/a');
        $this->actingAs($this->editorWithPermissions([]));

        $this->assertSaveRejected('events/a.jpg', missing: false);
    }

    public function test_save_reports_a_file_gone_from_the_library(): void
    {
        $this->image('events/gone');
        $page = Livewire::test(MediaPickerFixturePage::class)
            ->callAction($this->pick(), data: ['file' => 'events/gone']);

        // Deleted from the library while the form was open
        MediaFileModel::query()->where('file_id', 'events/gone')->delete();

        $page->call('save')->assertHasFormErrors(['image_public_id']);

        $this->assertSame([__('filament.media_picker.errors.missing')], $page->errors()->get('data.image_public_id'));
    }

    public function test_original_record_path_is_always_accepted(): void
    {
        // An old path no source claims, never indexed
        $event = EventModel::factory()->create(['image_public_id' => 'legacy/old.jpg']);
        Storage::disk('images')->put('legacy/old.jpg', 'x');

        Livewire::test(MediaPickerFixturePage::class, ['event' => $event->id])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved.image_public_id', 'legacy/old.jpg');
    }

    public function test_stored_paths_are_always_accepted(): void
    {
        MediaPickerFixturePage::$storedPaths = static fn (): array => ['branding/current.png'];

        Livewire::test(MediaPickerFixturePage::class)
            ->set('data.image_public_id', ['a' => 'branding/current.png'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_constrain_applies_to_listing_and_validation(): void
    {
        MediaPickerFixturePage::$constrain = static fn (Builder $query): Builder => $query->where('media_files.width', '>=', 1000);
        $this->image('events/small', width: 400);
        $this->image('events/wide', width: 1600);

        $html = $this->browserHtml(Livewire::test(MediaPickerFixturePage::class)->mountAction($this->pick()));
        $this->assertStringContainsString('data-media-file="events/wide"', $html);
        $this->assertStringNotContainsString('data-media-file="events/small"', $html);

        $this->assertSaveRejected('events/small.jpg', missing: false);
    }

    private function assertSaveRejected(string $path, bool $missing): void
    {
        $page = Livewire::test(MediaPickerFixturePage::class)
            ->set('data.image_public_id', ['a' => $path])
            ->call('save')
            ->assertHasFormErrors(['image_public_id']);

        $this->assertNull($page->get('saved'), $path);

        $messages = $page->errors()->get('data.image_public_id');
        $missing
            ? $this->assertSame([__('filament.media_picker.errors.missing')], $messages, $path)
            : $this->assertNotContains(__('filament.media_picker.errors.missing'), $messages, $path);
    }

    private function pick(): TestAction
    {
        return TestAction::make(MediaLibraryPicker::ACTION)->schemaComponent('image_public_id', schema: 'form');
    }

    /**
     * A hidden hint action is not registered on its field, so it cannot be mounted at all.
     *
     * @param  Testable<Component>  $page
     */
    private function assertCannotPick(Testable $page): void
    {
        $page->assertActionDoesNotExist($this->pick());
        $this->assertStringNotContainsString(__('filament.media_picker.action'), $page->html());
    }

    /**
     * Action modals render outside the component HTML: the browser of the mounted action is rendered alone.
     *
     * @param  Testable<Component>  $page
     */
    private function browserHtml(Testable $page): string
    {
        $component = $page->instance();
        $this->assertInstanceOf(MediaPickerFixturePage::class, $component);
        $schema = (fn (): ?Schema => $this->getMountedActionSchema())->call($component);
        $this->assertInstanceOf(Schema::class, $schema);
        $browser = $schema->getFlatFields()['file'] ?? null;
        $this->assertInstanceOf(MediaLibraryBrowser::class, $browser);

        // Rendered outside a request, the field wrapper still reads the error bag
        view()->share('errors', new ViewErrorBag);

        return $browser->toHtml();
    }

    private function register(string $type, FakeMediaSource $source): void
    {
        $alias = 'test.media.picker.'.$type;
        $this->app->instance($alias, $source);
        /** @var class-string $alias */
        $this->app->make(MediaSourceRegistryInterface::class)->register($alias);
    }

    private function image(string $id, string $format = 'jpg', int $bytes = 1000, int $width = 800): void
    {
        MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => $format,
            'bytes' => $bytes,
            'width' => $width,
            'height' => 600,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now(),
        ]);
    }
}
