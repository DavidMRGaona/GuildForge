<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AboutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_successfully(): void
    {
        $response = $this->get('/nosotros');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('About')
        );
    }

    /**
     * Verify the about page receives the guild name from settings.
     */
    public function test_about_page_includes_guild_name(): void
    {
        // Arrange
        app(SettingsServiceInterface::class)->set('guild_name', 'GuildForge');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('guildName', 'GuildForge')
        );
    }

    /**
     * Verify the about page receives the about history from settings.
     */
    public function test_about_page_includes_about_history(): void
    {
        // Arrange
        app(SettingsServiceInterface::class)->set('about_history', '<p>Our story</p>');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('aboutHistory', '<p>Our story</p>')
        );
    }

    /**
     * Verify the about page receives contact information from settings.
     */
    public function test_about_page_includes_contact_info(): void
    {
        // Arrange
        $settings = app(SettingsServiceInterface::class);
        $settings->set('contact_email', 'info@guildforge.es');
        $settings->set('contact_phone', '+34 123 456 789');
        $settings->set('contact_address', 'Calle Principal 1');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('contactEmail', 'info@guildforge.es')
                ->where('contactPhone', '+34 123 456 789')
                ->where('contactAddress', 'Calle Principal 1')
        );
    }

    /**
     * Verify the about page uses app.name config as default when guild_name is not set.
     */
    public function test_about_page_uses_app_name_as_default(): void
    {
        // Arrange
        // Without guild_name configured, should use app.name config

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('guildName', config('app.name'))
        );
    }

    /**
     * Verify the about page receives the hero image from settings.
     */
    public function test_about_page_includes_hero_image(): void
    {
        // Arrange
        app(SettingsServiceInterface::class)->set('about_hero_image', 'about/hero.jpg');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('aboutHeroImage', 'about/hero.jpg')
        );
    }

    /**
     * Verify the about page receives the tagline from settings.
     */
    public function test_about_page_includes_tagline(): void
    {
        // Arrange
        app(SettingsServiceInterface::class)->set('about_tagline', 'Tu comunidad de juegos');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('aboutTagline', 'Tu comunidad de juegos')
        );
    }

    /**
     * Verify the about page receives activities from settings.
     */
    public function test_about_page_includes_activities(): void
    {
        // Arrange
        $activities = [
            ['icon' => 'dice', 'title' => 'Juegos de rol', 'description' => 'Partidas semanales'],
        ];
        app(SettingsServiceInterface::class)->set('about_activities', json_encode($activities));

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->has('activities', 1)
                ->where('activities.0.icon', 'dice')
                ->where('activities.0.title', 'Juegos de rol')
                ->where('activities.0.description', 'Partidas semanales')
        );
    }

    /**
     * Verify the about page returns empty activities when not configured.
     */
    public function test_about_page_returns_empty_activities_when_not_configured(): void
    {
        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('activities', [])
        );
    }

    /**
     * Verify the about page handles invalid activities JSON gracefully.
     */
    public function test_about_page_handles_invalid_activities_json(): void
    {
        // Arrange
        app(SettingsServiceInterface::class)->set('about_activities', 'invalid json');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('activities', [])
        );
    }

    /**
     * Verify the about page receives join steps from settings.
     */
    public function test_about_page_includes_join_steps(): void
    {
        // Arrange
        $joinSteps = [
            ['title' => 'Step 1', 'description' => 'Description 1'],
            ['title' => 'Step 2', 'description' => null],
        ];
        app(SettingsServiceInterface::class)->set('join_steps', json_encode($joinSteps));

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->has('joinSteps', 2)
                ->where('joinSteps.0.title', 'Step 1')
                ->where('joinSteps.0.description', 'Description 1')
                ->where('joinSteps.1.title', 'Step 2')
                ->where('joinSteps.1.description', null)
        );
    }

    /**
     * Verify the about page returns empty join steps when not configured.
     */
    public function test_about_page_returns_empty_join_steps_when_not_configured(): void
    {
        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('joinSteps', [])
        );
    }

    /**
     * Verify the about page handles invalid join steps JSON gracefully.
     */
    public function test_about_page_handles_invalid_join_steps_json(): void
    {
        // Arrange
        app(SettingsServiceInterface::class)->set('join_steps', 'invalid json');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('joinSteps', [])
        );
    }

    /**
     * Verify the about page receives location settings.
     */
    public function test_about_page_includes_location(): void
    {
        // Arrange
        $settings = app(SettingsServiceInterface::class);
        $settings->set('location_name', 'Test HQ');
        $settings->set('location_address', 'Test Address, City');
        $settings->set('location_lat', '42.5956');
        $settings->set('location_lng', '-8.7644');
        $settings->set('location_zoom', '15');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->has('location')
                ->where('location.name', 'Test HQ')
                ->where('location.address', 'Test Address, City')
                ->where('location.lat', 42.5956)
                ->where('location.lng', -8.7644)
                ->where('location.zoom', 15)
        );
    }
}
