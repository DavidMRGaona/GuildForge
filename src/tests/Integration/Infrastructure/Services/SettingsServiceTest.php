<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use App\Infrastructure\Services\SettingsService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class SettingsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Booting the app already read the settings: start clean, as a new request would
        $this->startAnotherRequest();
        $this->settingsService = new SettingsService;
    }

    public function test_get_returns_value_for_existing_key(): void
    {
        SettingModel::create([
            'key' => 'test_key',
            'value' => 'test_value',
        ]);

        $result = $this->settingsService->get('test_key');

        $this->assertEquals('test_value', $result);
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $result = $this->settingsService->get('non_existent_key', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function test_set_creates_new_setting(): void
    {
        $this->settingsService->set('new_key', 'new_value');

        $this->assertDatabaseHas('settings', [
            'key' => 'new_key',
            'value' => 'new_value',
        ]);
    }

    public function test_set_updates_existing_setting(): void
    {
        SettingModel::create([
            'key' => 'existing_key',
            'value' => 'old_value',
        ]);

        $this->settingsService->set('existing_key', 'updated_value');

        $this->assertDatabaseHas('settings', [
            'key' => 'existing_key',
            'value' => 'updated_value',
        ]);
        $this->assertSame(1, SettingModel::query()->where('key', 'existing_key')->count());
    }

    public function test_get_location_settings_returns_location_dto(): void
    {
        SettingModel::create([
            'key' => 'location_name',
            'value' => 'Test HQ',
        ]);
        SettingModel::create([
            'key' => 'location_address',
            'value' => 'Test Address, City',
        ]);
        SettingModel::create([
            'key' => 'location_lat',
            'value' => '42.5956',
        ]);
        SettingModel::create([
            'key' => 'location_lng',
            'value' => '-8.7644',
        ]);
        SettingModel::create([
            'key' => 'location_zoom',
            'value' => '15',
        ]);
        SettingModel::create([
            'key' => 'other_setting',
            'value' => 'should_not_be_included',
        ]);

        $result = $this->settingsService->getLocationSettings();

        $this->assertEquals('Test HQ', $result->name);
        $this->assertEquals('Test Address, City', $result->address);
        $this->assertEquals(42.5956, $result->lat);
        $this->assertEquals(-8.7644, $result->lng);
        $this->assertEquals(15, $result->zoom);
    }

    public function test_settings_are_cached(): void
    {
        SettingModel::create([
            'key' => 'cached_key',
            'value' => 'original_value',
        ]);

        // First call should cache the value
        $firstResult = $this->settingsService->get('cached_key');
        $this->assertEquals('original_value', $firstResult);

        // Update the database directly (bypassing the service)
        SettingModel::where('key', 'cached_key')->update(['value' => 'updated_value']);

        // Second call should return cached value, not the updated one
        $secondResult = $this->settingsService->get('cached_key');
        $this->assertEquals('original_value', $secondResult);
    }

    public function test_clear_cache_invalidates_cache(): void
    {
        SettingModel::create([
            'key' => 'cached_key',
            'value' => 'original_value',
        ]);

        // Cache the value
        $firstResult = $this->settingsService->get('cached_key');
        $this->assertEquals('original_value', $firstResult);

        // Update the database and clear cache
        SettingModel::where('key', 'cached_key')->update(['value' => 'updated_value']);
        $this->settingsService->clearCache();

        // Should now return the updated value
        $secondResult = $this->settingsService->get('cached_key');
        $this->assertEquals('updated_value', $secondResult);
    }

    public function test_a_late_read_cannot_cache_again_a_value_changed_meanwhile(): void
    {
        SettingModel::create(['key' => 'raced_key', 'value' => 'old_value']);
        // Request A reads the version and starts loading the table (old row)
        $this->settingsService->get('raced_key');
        $versionSeenByA = $this->storedVersion();

        // Request B writes the new value
        $this->startAnotherRequest();
        $this->settingsService->set('raced_key', 'new_value');

        // Request A finishes late and stores the old row it loaded
        Cache::put(SettingsService::CACHE_KEY_PREFIX.$versionSeenByA, ['raced_key' => 'old_value'], 3600);

        $this->startAnotherRequest();
        $this->assertSame('new_value', $this->settingsService->get('raced_key'));
    }

    public function test_the_writing_request_reads_its_own_write(): void
    {
        SettingModel::create(['key' => 'own_key', 'value' => 'old_value']);
        $this->settingsService->get('own_key');

        $this->settingsService->set('own_key', 'new_value');

        $this->assertSame('new_value', $this->settingsService->get('own_key'));
    }

    public function test_a_write_inside_a_transaction_renews_the_cache_only_after_the_commit(): void
    {
        SettingModel::create(['key' => 'committed_key', 'value' => 'old_value']);
        $this->settingsService->get('committed_key');
        $before = $this->storedVersion();

        DB::transaction(function () use ($before): void {
            $this->settingsService->set('committed_key', 'new_value');

            // Renewed now, another request could cache the old row under the new version
            $this->assertSame($before, $this->storedVersion(), 'The version was renewed before the commit');
            // The writer sees its own write, and never caches it before the commit
            $this->assertSame('new_value', $this->settingsService->get('committed_key'));
            $this->assertSame('old_value', Cache::get(SettingsService::CACHE_KEY_PREFIX.$before)['committed_key'] ?? null);
        });

        $this->assertNotSame($before, $this->storedVersion());
        $this->startAnotherRequest();
        $this->assertSame('new_value', $this->settingsService->get('committed_key'));
    }

    public function test_a_rolled_back_write_is_never_cached(): void
    {
        SettingModel::create(['key' => 'rolled_back_key', 'value' => 'old_value']);

        try {
            DB::transaction(function (): void {
                $this->settingsService->set('rolled_back_key', 'new_value');
                $this->settingsService->get('rolled_back_key');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // Rolled back on purpose
        }

        $this->startAnotherRequest();
        $this->assertSame('old_value', $this->settingsService->get('rolled_back_key'));
    }

    public function test_a_missing_version_starts_a_new_one(): void
    {
        SettingModel::create(['key' => 'evicted_key', 'value' => 'original_value']);
        $this->settingsService->get('evicted_key');
        SettingModel::where('key', 'evicted_key')->update(['value' => 'updated_value']);

        // Evicted or flushed: the old entry must not be found again
        Cache::forget(SettingsService::VERSION_KEY);
        $this->startAnotherRequest();

        $this->assertSame('updated_value', $this->settingsService->get('evicted_key'));
    }

    public function test_a_malformed_version_starts_a_new_one(): void
    {
        SettingModel::create(['key' => 'malformed_key', 'value' => 'original_value']);
        Cache::forever(SettingsService::VERSION_KEY, 'not-a-version');
        Cache::forever(SettingsService::CACHE_KEY_PREFIX.'not-a-version', ['malformed_key' => 'stale_value']);

        $this->assertSame('original_value', $this->settingsService->get('malformed_key'));
        $this->assertNotSame('not-a-version', $this->storedVersion());
    }

    public function test_the_version_is_read_once_per_request(): void
    {
        SettingModel::create(['key' => 'request_key', 'value' => 'original_value']);
        $this->settingsService->get('request_key');

        // Another process renews the version: this request keeps the one it read
        Cache::forever(SettingsService::VERSION_KEY, str_repeat('a', 32));
        SettingModel::where('key', 'request_key')->update(['value' => 'updated_value']);
        $this->assertSame('original_value', $this->settingsService->get('request_key'));

        $this->startAnotherRequest();
        $this->assertSame('updated_value', $this->settingsService->get('request_key'));
    }

    public function test_a_failed_renewal_drops_the_version_and_is_reported(): void
    {
        $store = new class extends ArrayStore
        {
            public bool $failVersionWrites = false;

            public function forever($key, $value): bool
            {
                if ($this->failVersionWrites && $key === SettingsService::VERSION_KEY) {
                    throw new RuntimeException('Cache store unavailable');
                }

                return parent::forever($key, $value);
            }
        };
        Cache::extend('failing-versions', fn () => Cache::repository($store));
        config(['cache.stores.failing-versions' => ['driver' => 'failing-versions']]);
        Cache::setDefaultDriver('failing-versions');

        SettingModel::create(['key' => 'failing_key', 'value' => 'old_value']);
        $this->settingsService->get('failing_key');
        $log = Log::spy();
        $store->failVersionWrites = true;

        try {
            $this->settingsService->set('failing_key', 'new_value');
            $this->fail('A failed renewal must not look like a clean save');
        } catch (RuntimeException $e) {
            $this->assertSame('Cache store unavailable', $e->getMessage());
        }

        $this->assertSame('new_value', SettingModel::where('key', 'failing_key')->value('value'));
        // This request keeps reading the table, and the next ones start a new version
        $this->assertSame('new_value', $this->settingsService->get('failing_key'));
        $this->assertNull(Cache::get(SettingsService::VERSION_KEY));
        $log->shouldHaveReceived('error')->once();

        $store->failVersionWrites = false;
        $this->startAnotherRequest();
        $this->assertSame('new_value', $this->settingsService->get('failing_key'));
    }

    public function test_get_stored_reads_the_table_not_the_cache(): void
    {
        SettingModel::create(['key' => 'stored_key', 'value' => 'original_value']);
        $this->settingsService->get('stored_key');
        SettingModel::where('key', 'stored_key')->update(['value' => 'updated_value']);

        $this->assertSame('updated_value', $this->settingsService->getStored('stored_key'));
        $this->assertSame('original_value', $this->settingsService->get('stored_key'));
    }

    public function test_get_stored_returns_the_default_for_a_missing_key(): void
    {
        $this->assertSame('fallback', $this->settingsService->getStored('missing_key', 'fallback'));
    }

    public function test_get_stored_encrypted_decrypts_the_stored_value(): void
    {
        SettingModel::create(['key' => 'stored_secret', 'value' => Crypt::encryptString('old-secret')]);
        $this->settingsService->get('stored_secret');
        SettingModel::where('key', 'stored_secret')->update(['value' => Crypt::encryptString('new-secret')]);

        $this->assertSame('new-secret', $this->settingsService->getStoredEncrypted('stored_secret'));
    }

    #[DataProvider('unreadableSecrets')]
    public function test_get_stored_encrypted_returns_the_default_when_unreadable(?string $value): void
    {
        if ($value !== null) {
            SettingModel::create(['key' => 'stored_secret', 'value' => $value]);
        }

        $this->assertSame('fallback', $this->settingsService->getStoredEncrypted('stored_secret', 'fallback'));
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function unreadableSecrets(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'not encrypted' => ['plain-text'],
        ];
    }

    public function test_is_registration_enabled_returns_true_by_default(): void
    {
        $result = $this->settingsService->isRegistrationEnabled();

        $this->assertTrue($result);
    }

    public function test_is_registration_enabled_returns_false_when_disabled(): void
    {
        SettingModel::create([
            'key' => 'auth_registration_enabled',
            'value' => '0',
        ]);

        $result = $this->settingsService->isRegistrationEnabled();

        $this->assertFalse($result);
    }

    public function test_is_registration_enabled_returns_true_when_enabled(): void
    {
        SettingModel::create([
            'key' => 'auth_registration_enabled',
            'value' => '1',
        ]);

        $result = $this->settingsService->isRegistrationEnabled();

        $this->assertTrue($result);
    }

    public function test_is_maintenance_mode_enabled_returns_false_by_default(): void
    {
        $this->assertFalse($this->settingsService->isMaintenanceModeEnabled());
    }

    public function test_is_maintenance_mode_enabled_returns_true_when_enabled(): void
    {
        SettingModel::create([
            'key' => 'maintenance_enabled',
            'value' => '1',
        ]);

        $this->assertTrue($this->settingsService->isMaintenanceModeEnabled());
    }

    public function test_is_maintenance_mode_enabled_returns_false_when_disabled(): void
    {
        SettingModel::create([
            'key' => 'maintenance_enabled',
            'value' => '',
        ]);

        $this->assertFalse($this->settingsService->isMaintenanceModeEnabled());
    }

    public function test_is_login_enabled_returns_true_by_default(): void
    {
        $result = $this->settingsService->isLoginEnabled();

        $this->assertTrue($result);
    }

    public function test_is_login_enabled_returns_false_when_disabled(): void
    {
        SettingModel::create([
            'key' => 'auth_login_enabled',
            'value' => '0',
        ]);

        $result = $this->settingsService->isLoginEnabled();

        $this->assertFalse($result);
    }

    public function test_is_login_enabled_returns_true_when_enabled(): void
    {
        SettingModel::create([
            'key' => 'auth_login_enabled',
            'value' => '1',
        ]);

        $result = $this->settingsService->isLoginEnabled();

        $this->assertTrue($result);
    }

    public function test_is_email_verification_required_returns_false_by_default(): void
    {
        $result = $this->settingsService->isEmailVerificationRequired();

        $this->assertFalse($result);
    }

    public function test_is_email_verification_required_returns_true_when_enabled(): void
    {
        SettingModel::create([
            'key' => 'auth_email_verification_required',
            'value' => '1',
        ]);

        $result = $this->settingsService->isEmailVerificationRequired();

        $this->assertTrue($result);
    }

    public function test_is_email_verification_required_returns_false_when_disabled(): void
    {
        SettingModel::create([
            'key' => 'auth_email_verification_required',
            'value' => '0',
        ]);

        $result = $this->settingsService->isEmailVerificationRequired();

        $this->assertFalse($result);
    }

    public function test_email_verification_enforced_since_is_read_as_a_date(): void
    {
        SettingModel::query()->updateOrCreate(
            ['key' => 'email_verification_enforced_since'],
            ['value' => '2026-10-14T10:30:00+00:00'],
        );

        $since = $this->settingsService->getEmailVerificationEnforcedSince();

        $this->assertNotNull($since);
        $this->assertSame('2026-10-14T10:30:00+00:00', $since->format(DATE_ATOM));
    }

    public function test_email_verification_enforced_since_is_null_when_missing(): void
    {
        SettingModel::query()->where('key', 'email_verification_enforced_since')->delete();

        $this->assertNull($this->settingsService->getEmailVerificationEnforcedSince());
    }

    #[DataProvider('malformedCutoffs')]
    public function test_email_verification_enforced_since_is_null_when_malformed(string $value): void
    {
        SettingModel::query()->updateOrCreate(
            ['key' => 'email_verification_enforced_since'],
            ['value' => $value],
        );

        $this->assertNull($this->settingsService->getEmailVerificationEnforcedSince());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedCutoffs(): array
    {
        return [
            'empty' => [''],
            'relative' => ['tomorrow'],
            'date only' => ['2026-10-14'],
            'out of range' => ['2026-13-45T10:30:00+00:00'],
        ];
    }

    private function storedVersion(): mixed
    {
        return Cache::get(SettingsService::VERSION_KEY);
    }

    /**
     * Laravel drops scoped instances when a new request or job starts.
     */
    private function startAnotherRequest(): void
    {
        $this->app->forgetScopedInstances();
    }
}
