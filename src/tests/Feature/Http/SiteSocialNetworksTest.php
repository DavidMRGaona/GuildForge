<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Social networks are stored once (`social_<network>`), and each has a «shown on the public site»
 * switch (`social_<network>_visible`, shown when missing) that filters the footer and «Sobre nosotros».
 */
final class SiteSocialNetworksTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setting(string $key, string $value): void
    {
        app(SettingsServiceInterface::class)->set($key, $value);
    }

    public function test_new_networks_reach_the_shared_prop_and_the_about_page(): void
    {
        $this->setting('social_whatsapp', 'chat.whatsapp.com/AbC123');
        $this->setting('social_youtube', 'www.youtube.com/@example');
        $this->setting('social_twitch', 'www.twitch.tv/example');

        $this->get('/nosotros')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('socialLinks.whatsapp', 'https://chat.whatsapp.com/AbC123')
            ->where('socialLinks.youtube', 'https://www.youtube.com/@example')
            ->where('socialLinks.twitch', 'https://www.twitch.tv/example')
            ->where('socialWhatsapp', 'https://chat.whatsapp.com/AbC123')
            ->where('socialYoutube', 'https://www.youtube.com/@example')
            ->where('socialTwitch', 'https://www.twitch.tv/example'));
    }

    public function test_a_network_is_shown_when_its_switch_was_never_saved(): void
    {
        $this->setting('social_discord', 'discord.gg/example');

        $this->get('/nosotros')->assertInertia(fn (Assert $page) => $page
            ->where('socialLinks.discord', 'https://discord.gg/example')
            ->where('socialDiscord', 'https://discord.gg/example'));
    }

    public function test_an_unchecked_network_is_left_out_of_the_footer_prop_and_the_about_page(): void
    {
        $this->setting('social_discord', 'discord.gg/example');
        $this->setting('social_discord_visible', '');
        $this->setting('social_instagram', 'www.instagram.com/example');
        $this->setting('social_instagram_visible', '1');

        $this->get('/nosotros')->assertInertia(fn (Assert $page) => $page
            ->where('socialLinks.discord', '')
            ->where('socialDiscord', '')
            ->where('socialLinks.instagram', 'https://www.instagram.com/example')
            ->where('socialInstagram', 'https://www.instagram.com/example'));
    }

    public function test_a_checked_network_without_address_shows_nothing(): void
    {
        $this->setting('social_twitch_visible', '1');

        $this->get('/nosotros')->assertInertia(fn (Assert $page) => $page
            ->where('socialLinks.twitch', '')
            ->where('socialTwitch', ''));
    }

    public function test_stored_addresses_that_are_not_https_urls_are_not_published(): void
    {
        $this->setting('social_facebook', 'javascript:alert(1)');
        $this->setting('social_telegram', 'user:pass@t.me/x');
        $this->setting('social_whatsapp', 'example.com/not-whatsapp');

        $this->get('/nosotros')->assertInertia(fn (Assert $page) => $page
            ->where('socialLinks.facebook', '')
            ->where('socialLinks.telegram', '')
            ->where('socialLinks.whatsapp', '')
            ->where('socialFacebook', ''));
    }

    public function test_structured_data_lists_every_network_with_an_address_even_if_hidden(): void
    {
        $this->setting('social_youtube', 'www.youtube.com/@example');
        $this->setting('social_youtube_visible', '');
        $this->setting('social_whatsapp', 'wa.me/34600123456');
        $this->setting('social_facebook', 'javascript:alert(1)');

        $urls = app(SiteBrandingServiceInterface::class)->socialProfileUrls();

        $this->assertSame(['https://wa.me/34600123456', 'https://www.youtube.com/@example'], $urls);
    }
}
