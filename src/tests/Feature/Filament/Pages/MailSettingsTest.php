<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\Settings\MailSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class MailSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_mail_settings_page_requires_admin(): void
    {
        $user = UserModel::factory()->create(); // Regular member

        $this->actingAs($user);

        $this->get(MailSettings::getUrl())
            ->assertForbidden();
    }

    public function test_mail_settings_page_accessible_by_admin(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        $this->get(MailSettings::getUrl())
            ->assertOk();
    }

    public function test_mail_settings_page_renders_form(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        Livewire::test(MailSettings::class)
            ->assertFormExists()
            ->assertFormFieldExists('mail_enabled')
            ->assertFormFieldExists('mail_driver')
            ->assertFormFieldExists('mail_from_address')
            ->assertFormFieldExists('mail_from_name');
    }

    public function test_mail_settings_saves_general_config(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        Livewire::test(MailSettings::class)
            ->fillForm([
                'mail_enabled' => '1',
                'mail_driver' => 'smtp',
                'mail_from_address' => 'noreply@guildforge.es',
                'mail_from_name' => 'GuildForge',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsServiceInterface::class);
        $this->assertEquals('1', $settings->get('mail_enabled'));
        $this->assertEquals('smtp', $settings->get('mail_driver'));
        $this->assertEquals('noreply@guildforge.es', $settings->get('mail_from_address'));
        $this->assertEquals('GuildForge', $settings->get('mail_from_name'));
    }

    public function test_mail_settings_saves_smtp_config(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        Livewire::test(MailSettings::class)
            ->fillForm([
                'mail_enabled' => '1',
                'mail_driver' => 'smtp',
                'mail_from_address' => 'noreply@guildforge.es',
                'mail_from_name' => 'GuildForge',
                'mail_smtp_host' => 'smtp.mailtrap.io',
                'mail_smtp_port' => '587',
                'mail_smtp_username' => 'testuser',
                'mail_smtp_password' => 'secret123',
                'mail_smtp_encryption' => 'tls',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsServiceInterface::class);
        $this->assertEquals('smtp.mailtrap.io', $settings->get('mail_smtp_host'));
        $this->assertEquals('587', $settings->get('mail_smtp_port'));
        $this->assertEquals('testuser', $settings->get('mail_smtp_username'));
        $this->assertEquals('tls', $settings->get('mail_smtp_encryption'));
    }

    public function test_mail_settings_saves_encrypted_fields(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        Livewire::test(MailSettings::class)
            ->fillForm([
                'mail_enabled' => '1',
                'mail_driver' => 'smtp',
                'mail_from_address' => 'noreply@guildforge.es',
                'mail_from_name' => 'GuildForge',
                'mail_smtp_password' => 'my-secret-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsServiceInterface::class);

        // The raw stored value must NOT be the plaintext password
        $rawValue = $settings->get('mail_smtp_password');
        $this->assertNotEquals('my-secret-password', $rawValue);
        $this->assertNotEmpty($rawValue);

        // But decrypting it should return the original value
        $this->assertEquals('my-secret-password', $settings->getEncrypted('mail_smtp_password'));
    }

    public function test_mail_settings_saves_ses_secret_encrypted(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        Livewire::test(MailSettings::class)
            ->fillForm([
                'mail_enabled' => '1',
                'mail_driver' => 'ses',
                'mail_from_address' => 'noreply@guildforge.es',
                'mail_from_name' => 'GuildForge',
                'mail_ses_region' => 'eu-west-1',
                'mail_ses_access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
                'mail_ses_secret_access_key' => 'ses-secret-key',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsServiceInterface::class);

        // The raw stored value must NOT be the plaintext secret
        $rawValue = $settings->get('mail_ses_secret_access_key');
        $this->assertNotEquals('ses-secret-key', $rawValue);
        $this->assertNotEmpty($rawValue);

        // But decrypting it should return the original value
        $this->assertEquals('ses-secret-key', $settings->getEncrypted('mail_ses_secret_access_key'));
    }

    public function test_mail_settings_saves_resend_api_key_encrypted(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        Livewire::test(MailSettings::class)
            ->fillForm([
                'mail_enabled' => '1',
                'mail_driver' => 'resend',
                'mail_from_address' => 'noreply@guildforge.es',
                'mail_from_name' => 'GuildForge',
                'mail_resend_api_key' => 're_123456789',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsServiceInterface::class);

        // The raw stored value must NOT be the plaintext key
        $rawValue = $settings->get('mail_resend_api_key');
        $this->assertNotEquals('re_123456789', $rawValue);
        $this->assertNotEmpty($rawValue);

        // But decrypting it should return the original value
        $this->assertEquals('re_123456789', $settings->getEncrypted('mail_resend_api_key'));
    }

    public function test_mail_settings_loads_existing_config(): void
    {
        $user = UserModel::factory()->admin()->create();

        $settings = app(SettingsServiceInterface::class);
        $settings->set('mail_enabled', '1');
        $settings->set('mail_driver', 'ses');
        $settings->set('mail_from_address', 'existing@guildforge.es');
        $settings->set('mail_from_name', 'Existing Name');
        $settings->set('mail_ses_region', 'eu-west-1');

        $this->actingAs($user);

        Livewire::test(MailSettings::class)
            ->assertFormSet([
                'mail_enabled' => '1',
                'mail_driver' => 'ses',
                'mail_from_address' => 'existing@guildforge.es',
                'mail_from_name' => 'Existing Name',
                'mail_ses_region' => 'eu-west-1',
            ]);
    }

    public function test_mail_settings_applies_runtime_config_after_save(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        Livewire::test(MailSettings::class)
            ->fillForm([
                'mail_enabled' => '1',
                'mail_driver' => 'ses',
                'mail_from_address' => 'runtime@guildforge.es',
                'mail_from_name' => 'Runtime Test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('ses', config('mail.default'));
    }
}
