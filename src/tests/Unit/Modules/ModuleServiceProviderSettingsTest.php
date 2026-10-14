<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Modules\ModuleServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A booting module publishes its effective settings (release defaults with the panel
 * values on top) under modules.settings.<name>, where every module reads them.
 */
final class ModuleServiceProviderSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-provider-settings-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->modulesPath.'/provider-fixture/config');
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_register_sets_the_effective_settings_in_config(): void
    {
        File::put($this->modulesPath.'/provider-fixture/config/settings.php', "<?php\n\nreturn ['a' => 1, 'b' => ['c' => true, 'd' => true]];\n");
        app(ModuleSettingsStoreInterface::class)->store('provider-fixture', ['a' => 2, 'b' => ['c' => false]]);

        $this->provider()->register();

        $this->assertSame(['a' => 2, 'b' => ['c' => false, 'd' => true]], config('modules.settings.provider-fixture'));
    }

    public function test_register_sets_nothing_when_there_are_no_defaults_and_nothing_stored(): void
    {
        $this->provider()->register();

        $this->assertFalse(config()->has('modules.settings.provider-fixture'));
    }

    private function provider(): ModuleServiceProvider
    {
        return new class(app()) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'provider-fixture';
            }
        };
    }
}
