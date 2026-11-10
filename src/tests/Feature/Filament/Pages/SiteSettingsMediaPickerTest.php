<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class SiteSettingsMediaPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saving_keeps_a_logo_missing_from_the_index(): void
    {
        // Right after updating, before any media:sync: the current logo is not in the library yet
        Storage::fake('images');
        Storage::disk('images')->put('branding/logo-light-old.png', 'x');
        $settings = $this->app->make(SettingsServiceInterface::class);
        $settings->set('site_logo_light', 'branding/logo-light-old.png');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['guild_name' => 'Asociación de ejemplo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('branding/logo-light-old.png', $settings->get('site_logo_light'));
    }

    public function test_a_forged_logo_path_is_refused(): void
    {
        Storage::fake('images');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->set('data.site_logo_light', ['a' => 'branding/forged.png'])
            ->call('save')
            ->assertHasFormErrors(['site_logo_light']);
    }
}
