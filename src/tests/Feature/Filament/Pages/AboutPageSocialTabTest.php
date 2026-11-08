<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Social\SocialNetwork;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AboutPageSocialTabTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_every_network_has_an_address_field_and_a_checked_switch_by_default(): void
    {
        $component = Livewire::test(AboutPageSettings::class);

        foreach (SocialNetwork::cases() as $network) {
            $component
                ->assertFormFieldExists($network->settingKey())
                ->assertFormFieldExists($network->visibilityKey())
                ->assertSet('data.'.$network->visibilityKey(), '1');
        }
    }

    /**
     * @return array<string, string>
     */
    private function requiredLocation(): array
    {
        return [
            'location_name' => 'Local de ejemplo',
            'location_address' => 'Calle Ejemplo 1',
            'location_lat' => '40.4',
            'location_lng' => '-3.7',
        ];
    }

    public function test_saving_stores_the_new_networks_and_the_switch(): void
    {
        Livewire::test(AboutPageSettings::class)
            ->fillForm([
                ...$this->requiredLocation(),
                'social_whatsapp' => 'chat.whatsapp.com/AbC123',
                'social_youtube' => 'www.youtube.com/@example',
                'social_twitch' => 'www.twitch.tv/example',
                'social_twitch_visible' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsServiceInterface::class);
        $this->assertSame('chat.whatsapp.com/AbC123', $settings->get('social_whatsapp'));
        $this->assertSame('www.youtube.com/@example', $settings->get('social_youtube'));
        $this->assertSame('www.twitch.tv/example', $settings->get('social_twitch'));
        $this->assertSame('', $settings->get('social_twitch_visible'));
        $this->assertSame('1', $settings->get('social_youtube_visible'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'javascript' => ['social_facebook', 'javascript:alert(1)'],
            'spaces' => ['social_instagram', 'www.instagram.com/a b'],
            'credentials' => ['social_discord', 'user:pass@discord.gg/x'],
            'no dot host' => ['social_tiktok', 'localhost/x'],
            'whatsapp other host' => ['social_whatsapp', 'example.com/x'],
            'whatsapp lookalike' => ['social_whatsapp', 'chat.whatsapp.com.evil.test/x'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function test_invalid_addresses_are_rejected_on_save(string $field, string $value): void
    {
        Livewire::test(AboutPageSettings::class)
            ->fillForm([$field => $value])
            ->call('save')
            ->assertHasFormErrors([$field]);

        $this->assertNull(app(SettingsServiceInterface::class)->get($field));
    }

    public function test_an_address_typed_with_https_is_accepted(): void
    {
        Livewire::test(AboutPageSettings::class)
            ->fillForm([...$this->requiredLocation(), 'social_telegram' => 'https://t.me/example'])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
