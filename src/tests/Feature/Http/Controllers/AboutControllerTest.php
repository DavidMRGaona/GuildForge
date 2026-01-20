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
}
