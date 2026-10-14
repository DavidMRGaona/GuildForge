<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ModuleSettingsPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\ModuleServiceProvider;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Support\Filament\FixtureTheme;
use Tests\Support\Modules\RegistersBootedModuleProviders;
use Tests\TestCase;

/**
 * Module settings are stored in the settings table and read back with config(): a select
 * whose options come from an enum must be stored as its value.
 */
final class ModuleSettingsEnumTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RegistersBootedModuleProviders;

    private string $modulePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulePath = sys_get_temp_dir().'/gf-settings-'.bin2hex(random_bytes(8));
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
        $this->registerBootedProvider(new class(app()) extends ModuleServiceProvider
        {
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

        $this->assertSame('{"theme":"dark"}', SettingModel::query()->where('key', 'module_settings:settings-fixture')->value('value'));
        $this->assertSame('dark', config('modules.settings.settings-fixture.theme'));
        $this->assertFileDoesNotExist($this->modulePath.'/config/settings.php');
    }
}
