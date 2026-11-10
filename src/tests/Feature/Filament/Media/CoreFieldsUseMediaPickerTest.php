<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Media;

use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\GalleryResource\Pages\EditGallery;
use App\Filament\Resources\HeroSlideResource\Pages\CreateHeroSlide;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

final class CoreFieldsUseMediaPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_core_image_fields_offer_the_library(): void
    {
        $gallery = GalleryModel::factory()->create();
        PhotoModel::factory()->create(['gallery_id' => $gallery->id, 'image_public_id' => 'galleries/2026/11/a.jpg']);

        $this->assertOffersLibrary(Livewire::test(CreateEvent::class), 'image_public_id');
        $this->assertOffersLibrary(Livewire::test(CreateArticle::class), 'featured_image_public_id');
        $this->assertOffersLibrary(Livewire::test(CreateHeroSlide::class), 'image_public_id');
        $this->assertOffersLibrary(Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()]), 'image_public_id');
        foreach (['site_logo_light', 'site_logo_dark', 'site_logo_email', 'site_favicon_light', 'site_favicon_dark'] as $key) {
            $this->assertOffersLibrary(Livewire::test(SiteSettings::class), $key);
        }
        $this->assertOffersLibrary(Livewire::test(AboutPageSettings::class), 'about_hero_image');
    }

    public function test_avatar_field_does_not_offer_the_library(): void
    {
        $page = Livewire::test(CreateUser::class);
        $field = $this->field($page, 'avatar_public_id');

        $page->assertActionDoesNotExist(TestAction::make(MediaLibraryPicker::ACTION)->schemaComponent(Str::after((string) $field->getKey(), 'form.'), schema: 'form'));
        $this->assertFalse($field->shouldPreventFilePathTampering());
    }

    public function test_saving_an_event_with_a_library_image_keeps_the_path(): void
    {
        MediaFileModel::query()->create([
            'file_id' => 'articles/2026/11/cover',
            'resource_type' => 'image',
            'folder' => 'articles/2026/11/',
            'format' => 'png',
            'bytes' => 1000,
            'width' => 800,
            'height' => 600,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now(),
        ]);
        $event = EventModel::factory()->create([
            'image_public_id' => null,
            'start_date' => now()->addDays(10)->setTime(10, 0),
            'end_date' => now()->addDays(10)->setTime(20, 0),
        ]);
        // The form asks for a category
        $event->tags()->attach(TagModel::factory()->forEvents()->create());

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->set('data.image_public_id', ['a' => 'articles/2026/11/cover.png'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('articles/2026/11/cover.png', $event->refresh()->image_public_id);
    }

    /**
     * @param  Testable<Component>  $page
     */
    private function assertOffersLibrary(Testable $page, string $name): void
    {
        $field = $this->field($page, $name);

        // Hint actions live in the schema after the label, so they are found through the page
        $page->assertActionVisible(TestAction::make(MediaLibraryPicker::ACTION)->schemaComponent(Str::after((string) $field->getKey(), 'form.'), schema: 'form'));
        $this->assertTrue($field->shouldPreventFilePathTampering(), $name);
    }

    /**
     * @param  Testable<Component>  $page
     */
    private function field(Testable $page, string $name): FileUpload
    {
        $component = $page->instance();
        $this->assertInstanceOf(HasSchemas::class, $component);
        $form = $component->getSchema('form');
        $this->assertInstanceOf(Schema::class, $form);

        foreach ($form->getFlatFields(withHidden: true) as $field) {
            if ($field instanceof FileUpload && $field->getName() === $name) {
                return $field;
            }
        }

        $this->fail("No file upload named {$name}");
    }
}
