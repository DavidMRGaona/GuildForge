<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use App\Infrastructure\Services\SettingsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class SettingsServiceEncryptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->settingsService = new SettingsService;
    }

    public function test_set_encrypted_stores_value_that_is_not_plain_text(): void
    {
        $plainText = 'my-secret-api-key';

        $this->settingsService->setEncrypted('api_key', $plainText);

        $storedValue = SettingModel::where('key', 'api_key')->first();
        $this->assertNotNull($storedValue);
        $this->assertNotEquals($plainText, $storedValue->value);
    }

    public function test_get_encrypted_returns_decrypted_value(): void
    {
        $plainText = 'my-secret-api-key';
        $encrypted = Crypt::encryptString($plainText);

        SettingModel::create([
            'key' => 'api_key',
            'value' => $encrypted,
        ]);

        $result = $this->settingsService->getEncrypted('api_key');

        $this->assertEquals($plainText, $result);
    }

    public function test_get_encrypted_returns_default_when_key_does_not_exist(): void
    {
        $result = $this->settingsService->getEncrypted('non_existent_key', 'fallback_value');

        $this->assertEquals('fallback_value', $result);
    }

    public function test_get_encrypted_returns_default_when_decryption_fails(): void
    {
        SettingModel::create([
            'key' => 'corrupted_key',
            'value' => 'this-is-not-valid-encrypted-data',
        ]);

        $result = $this->settingsService->getEncrypted('corrupted_key', 'default_on_failure');

        $this->assertEquals('default_on_failure', $result);
    }

    public function test_set_encrypted_then_get_encrypted_returns_original_value(): void
    {
        $originalValue = 'super-secret-password-123!@#';

        $this->settingsService->setEncrypted('secret_setting', $originalValue);
        $result = $this->settingsService->getEncrypted('secret_setting');

        $this->assertEquals($originalValue, $result);
    }

    public function test_set_encrypted_invalidates_cache(): void
    {
        $this->settingsService->setEncrypted('cached_secret', 'initial-secret');

        // Populate cache by reading
        $this->settingsService->getEncrypted('cached_secret');

        // Update the encrypted value
        $this->settingsService->setEncrypted('cached_secret', 'updated-secret');

        $result = $this->settingsService->getEncrypted('cached_secret');

        $this->assertEquals('updated-secret', $result);
    }

    public function test_get_encrypted_returns_null_default_when_key_does_not_exist(): void
    {
        $result = $this->settingsService->getEncrypted('missing_key');

        $this->assertNull($result);
    }

    public function test_set_encrypted_stores_value_that_can_be_decrypted_by_crypt_facade(): void
    {
        $plainText = 'verify-with-crypt-facade';

        $this->settingsService->setEncrypted('verifiable_key', $plainText);

        $storedValue = SettingModel::where('key', 'verifiable_key')->first();
        $this->assertNotNull($storedValue);

        $decrypted = Crypt::decryptString($storedValue->value);
        $this->assertEquals($plainText, $decrypted);
    }

    public function test_get_encrypted_returns_default_when_stored_value_is_empty_string(): void
    {
        SettingModel::create([
            'key' => 'empty_encrypted',
            'value' => '',
        ]);

        $result = $this->settingsService->getEncrypted('empty_encrypted', 'default_for_empty');

        $this->assertEquals('default_for_empty', $result);
    }

    public function test_set_encrypted_overwrites_previous_encrypted_value(): void
    {
        $this->settingsService->setEncrypted('overwrite_key', 'first-value');
        $this->settingsService->setEncrypted('overwrite_key', 'second-value');

        $result = $this->settingsService->getEncrypted('overwrite_key');

        $this->assertEquals('second-value', $result);
        $this->assertDatabaseCount('settings', 1);
    }

    public function test_get_encrypted_does_not_expose_encrypted_value_via_plain_get(): void
    {
        $plainText = 'sensitive-data';

        $this->settingsService->setEncrypted('sensitive_key', $plainText);

        $rawValue = $this->settingsService->get('sensitive_key');

        $this->assertNotEquals($plainText, $rawValue);
        $this->assertIsString($rawValue);
    }
}
