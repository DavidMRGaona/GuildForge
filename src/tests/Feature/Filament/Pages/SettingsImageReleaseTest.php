<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

/**
 * The settings pages release a replaced image only once every key is saved, and only if nobody uses it.
 */
final class SettingsImageReleaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    private InMemoryMediaCatalog $catalog;

    private SettingsServiceInterface $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
        $this->settings = $this->app->make(SettingsServiceInterface::class);
        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_changing_a_logo_releases_the_old_one(): void
    {
        $this->image('branding/old');
        $this->image('branding/new');
        $this->settings->set('site_logo_light', 'branding/old.png');

        $this->save(['site_logo_light' => ['a' => 'branding/new.png']]);

        $this->assertSame('branding/new.png', $this->settings->get('site_logo_light'));
        $this->assertArrayNotHasKey('branding/old', $this->catalog->assets);
        $this->assertArrayHasKey('branding/new', $this->catalog->assets);
    }

    public function test_moving_a_logo_between_fields_keeps_it(): void
    {
        $this->image('branding/light');
        $this->settings->set('site_logo_light', 'branding/light.png');

        // The light logo becomes the dark one: it is replaced in one field and kept in the other
        $this->save(['site_logo_light' => [], 'site_logo_dark' => ['a' => 'branding/light.png']]);

        $this->assertSame('branding/light.png', $this->settings->get('site_logo_dark'));
        $this->assertArrayHasKey('branding/light', $this->catalog->assets);
    }

    public function test_a_logo_shared_with_an_event_is_kept(): void
    {
        $this->image('branding/shared');
        $this->settings->set('site_logo_light', 'branding/shared.png');
        EventModel::factory()->create(['image_public_id' => 'branding/shared.png']);

        $this->save(['site_logo_light' => []]);

        $this->assertSame('', $this->settings->get('site_logo_light'));
        $this->assertArrayHasKey('branding/shared', $this->catalog->assets);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(array $data): void
    {
        $page = Livewire::test(SiteSettings::class);

        // Set as a whole: filling would merge the new file into the one loaded from the settings
        foreach ($data as $key => $value) {
            $page->set('data.'.$key, $value);
        }

        $page->call('save')->assertHasNoFormErrors();
    }

    /**
     * An image of the library, also in the remote store.
     */
    private function image(string $id): void
    {
        $this->catalog->add($id);
        MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => 'png',
            'bytes' => 1000,
            'width' => 200,
            'height' => 100,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now(),
        ]);
    }
}
