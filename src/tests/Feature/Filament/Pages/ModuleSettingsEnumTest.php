<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ModuleSettingsPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Support\Filament\FixtureTheme;
use Tests\TestCase;

/**
 * Module settings are written to the module's config/settings.php and read back with
 * config(): a select whose options come from an enum must be stored as its value.
 */
final class ModuleSettingsEnumTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulePath = sys_get_temp_dir().'/gf-settings-'.uniqid();
        File::ensureDirectoryExists($this->modulePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulePath);
        parent::tearDown();
    }

    public function test_an_enum_backed_select_is_stored_as_its_value(): void
    {
        ModuleModel::factory()->enabled()->create(['name' => 'settings-fixture', 'path' => $this->modulePath]);
        $this->loadProvider(new class (app()) extends ModuleServiceProvider {
            public function moduleName(): string
            {
                return 'settings-fixture';
            }

            public function getSettingsSchema(): array
            {
                return [
                    Select::make('theme')->options(FixtureTheme::class)->required(),
                ];
            }
        });
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModuleSettingsPage::class, ['module' => 'settings-fixture'])
            ->fillForm(['theme' => 'dark'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['theme' => 'dark'], require $this->modulePath.'/config/settings.php');
        $this->assertSame('dark', config('modules.settings.settings-fixture.theme'));
    }

    private function loadProvider(ModuleServiceProvider $provider): void
    {
        // As if the module had booted: ModuleSettingsPage asks the loader for its provider
        (function (ModuleServiceProvider $provider): void {
            $this->loadedProviders[$provider->moduleName()] = $provider;
        })->call(app(ModuleLoader::class), $provider);
    }
}
