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
     * Verify the about page receives the association name from settings.
     */
    public function test_about_page_includes_association_name(): void
    {
        // Arrange
        app(SettingsServiceInterface::class)->set('association_name', 'Runesword');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('associationName', 'Runesword')
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
        $settings->set('contact_email', 'info@runesword.com');
        $settings->set('contact_phone', '+34 123 456 789');
        $settings->set('contact_address', 'Calle Principal 1');

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('contactEmail', 'info@runesword.com')
                ->where('contactPhone', '+34 123 456 789')
                ->where('contactAddress', 'Calle Principal 1')
        );
    }

    /**
     * Verify the about page uses app.name config as default when association_name is not set.
     */
    public function test_about_page_uses_app_name_as_default(): void
    {
        // Arrange
        // Without association_name configured, should use app.name config

        // Act
        $response = $this->get('/nosotros');

        // Assert
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('About')
                ->where('associationName', config('app.name'))
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
}
