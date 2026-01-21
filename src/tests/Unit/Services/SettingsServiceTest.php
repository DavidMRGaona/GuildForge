<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use App\Infrastructure\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settingsService = new SettingsService();
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
        $this->assertDatabaseCount('settings', 1);
    }

    public function test_get_location_settings_returns_all_location_keys(): void
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

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('address', $result);
        $this->assertArrayHasKey('lat', $result);
        $this->assertArrayHasKey('lng', $result);
        $this->assertArrayHasKey('zoom', $result);
        $this->assertEquals('Test HQ', $result['name']);
        $this->assertEquals('Test Address, City', $result['address']);
        $this->assertEquals(42.5956, $result['lat']);
        $this->assertEquals(-8.7644, $result['lng']);
        $this->assertEquals(15, $result['zoom']);
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
}
