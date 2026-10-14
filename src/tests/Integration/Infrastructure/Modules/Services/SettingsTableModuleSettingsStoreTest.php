<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Modules\Services\SettingsTableModuleSettingsStore;
use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Panel values live in the settings table; config/settings.php only holds the release
 * defaults, so replacing the module directory can no longer wipe what the admin saved.
 */
final class SettingsTableModuleSettingsStoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulePath = sys_get_temp_dir().'/gf-settings-store-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->modulePath.'/config');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulePath);
        parent::tearDown();
    }

    public function test_it_is_the_bound_implementation(): void
    {
        $this->assertInstanceOf(SettingsTableModuleSettingsStore::class, app(ModuleSettingsStoreInterface::class));
    }

    public function test_effective_returns_release_defaults_when_nothing_is_stored(): void
    {
        $this->defaults(['show_banner' => true, 'rotate_interval' => 5000]);

        $this->assertSame(['show_banner' => true, 'rotate_interval' => 5000], $this->store()->effective('fixture', $this->modulePath));
    }

    public function test_effective_is_empty_without_defaults_or_stored_values(): void
    {
        $this->assertSame([], $this->store()->effective('fixture', $this->modulePath));
    }

    public function test_store_keeps_the_values_under_the_module_settings_key_as_json(): void
    {
        // The key and shape venue-bookings already reads and writes on its own
        $this->store()->store('venue-bookings', ['approval_mode' => 'auto', 'custom_fields' => []]);

        $this->assertSame(
            '{"approval_mode":"auto","custom_fields":[]}',
            SettingModel::query()->where('key', 'module_settings:venue-bookings')->value('value'),
        );
        $this->assertSame(['approval_mode' => 'auto', 'custom_fields' => []], $this->store()->stored('venue-bookings'));
    }

    public function test_effective_overlays_stored_values_on_release_defaults(): void
    {
        $this->defaults(['notifications' => ['notify_on_registration' => true, 'notify_on_cancellation' => true], 'max_players' => 5]);
        $this->store()->store('fixture', ['notifications' => ['notify_on_registration' => false], 'max_players' => 8]);

        $this->assertSame(
            ['notifications' => ['notify_on_registration' => false, 'notify_on_cancellation' => true], 'max_players' => 8],
            $this->store()->effective('fixture', $this->modulePath),
        );
    }

    public function test_a_new_default_key_of_a_release_appears_next_to_stored_values(): void
    {
        $this->store()->store('fixture', ['a' => 2]);
        $this->defaults(['a' => 1, 'b' => true]);

        $this->assertSame(['a' => 2, 'b' => true], $this->store()->effective('fixture', $this->modulePath));
    }

    public function test_stored_lists_replace_default_lists_instead_of_merging_by_index(): void
    {
        $this->defaults(['allowed' => ['a', 'b', 'c'], 'presets' => [['label' => 'Mañana']]]);
        $this->store()->store('fixture', ['allowed' => ['x'], 'presets' => []]);

        $this->assertSame(['allowed' => ['x'], 'presets' => []], $this->store()->effective('fixture', $this->modulePath));
    }

    public function test_stored_keys_missing_from_the_defaults_are_kept(): void
    {
        $this->defaults(['a' => 1]);
        $this->store()->store('fixture', ['a' => 1, 'removed_in_this_release' => 'kept']);

        $this->assertSame(['a' => 1, 'removed_in_this_release' => 'kept'], $this->store()->effective('fixture', $this->modulePath));
    }

    public function test_stored_value_that_is_not_a_json_array_is_ignored(): void
    {
        $this->defaults(['a' => 1]);
        SettingModel::query()->create(['key' => 'module_settings:fixture', 'value' => '"just a string"']);
        SettingModel::query()->create(['key' => 'module_settings:broken', 'value' => '{not json']);

        $this->assertSame([], $this->store()->stored('fixture'));
        $this->assertSame([], $this->store()->stored('broken'));
        $this->assertSame(['a' => 1], $this->store()->effective('fixture', $this->modulePath));
    }

    public function test_effective_falls_back_to_defaults_when_the_settings_store_throws(): void
    {
        $this->defaults(['a' => 1]);
        $settings = Mockery::mock(SettingsServiceInterface::class);
        $settings->shouldReceive('get')->andThrow(new RuntimeException('database is down'));
        $this->app->instance(SettingsServiceInterface::class, $settings);

        $this->assertSame(['a' => 1], $this->store()->effective('fixture', $this->modulePath));
    }

    public function test_defaults_ignore_a_settings_file_that_does_not_return_an_array(): void
    {
        File::put($this->modulePath.'/config/settings.php', "<?php\n\nreturn 'nope';\n");

        $this->assertSame([], $this->store()->defaults($this->modulePath));
    }

    public function test_forget_removes_the_stored_values(): void
    {
        $this->defaults(['a' => 1]);
        $this->store()->store('fixture', ['a' => 2]);

        $this->store()->forget('fixture');

        $this->assertFalse(SettingModel::query()->where('key', 'module_settings:fixture')->exists());
        $this->assertSame(['a' => 1], $this->store()->effective('fixture', $this->modulePath));
    }

    private function store(): ModuleSettingsStoreInterface
    {
        return app(ModuleSettingsStoreInterface::class);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function defaults(array $values): void
    {
        File::put($this->modulePath.'/config/settings.php', "<?php\n\nreturn ".var_export($values, true).";\n");
    }
}
