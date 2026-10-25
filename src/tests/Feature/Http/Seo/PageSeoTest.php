<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Seo;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PageSeoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        config([
            'app.name' => 'Sitio de ejemplo',
            'app.description' => 'Asociación de juegos',
            'cloudinary.cloud_name' => 'demo',
            'cloudinary.prefix' => 'guildforge',
        ]);
        Storage::fake('images');
    }

    private function event(?string $image): EventModel
    {
        return EventModel::factory()->published()->create([
            'title' => 'Club de lectura',
            'slug' => 'club-de-lectura',
            'description' => '<p>Traed vuestro libro</p>',
            // app.timezone is Europe/Madrid, so these are venue local times
            'start_date' => '2026-01-15 18:00:00',
            'end_date' => '2026-01-15 22:00:00',
            'location' => 'La Taberna',
            'image_public_id' => $image,
            'member_price' => null,
            'non_member_price' => null,
        ]);
    }

    public function test_event_page_html_has_its_preview(): void
    {
        $this->event('events/2026/01/club.jpg');

        $response = $this->get('/eventos/club-de-lectura?fbclid=abc');

        $response->assertOk();
        $response->assertSee('<title>Club de lectura - Sitio de ejemplo</title>', false);
        $response->assertSee('<meta property="og:title" content="Club de lectura" data-seo-server>', false);
        // The query string (fbclid) is dropped from the canonical URL
        $response->assertSee('<link rel="canonical" href="'.url('/eventos/club-de-lectura').'" data-seo-server>', false);
        $response->assertSee('<meta property="og:image" content="https://res.cloudinary.com/demo/image/upload/e_trim/w_1200,h_630,c_fill,g_auto,q_auto,f_jpg/guildforge/events/2026/01/club" data-seo-server>', false);
        $response->assertSee('content="15 de enero de 2026, 18:00 · La Taberna · Traed vuestro libro"', false);
        $response->assertSee('"@type":"Event"', false);
        $response->assertSee('"price":"0.00"', false);
    }

    public function test_event_without_image_shows_the_custom_logo_and_never_the_default(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'settings/logo.png');
        $this->event(null);

        $response = $this->get('/eventos/club-de-lectura');

        $response->assertSee('<meta property="og:image" content="https://res.cloudinary.com/demo/image/upload/w_600,h_600,c_pad,b_white,q_auto,f_png/guildforge/settings/logo" data-seo-server>', false);
        $response->assertDontSee('images/defaults/logo', false);
    }

    public function test_article_page_has_article_metadata(): void
    {
        ArticleModel::factory()->published()->withAuthor(UserModel::factory()->create(['name' => 'Ana']))->create([
            'title' => 'Crónica',
            'slug' => 'cronica',
            'excerpt' => 'Resumen de la jornada',
            'featured_image_public_id' => 'articles/cronica.jpg',
        ]);

        $response = $this->get('/articulos/cronica');

        $response->assertSee('<meta property="og:type" content="article" data-seo-server>', false);
        $response->assertSee('<meta name="description" content="Resumen de la jornada" data-seo-server>', false);
        $response->assertSee('"@type":"Article"', false);
        $response->assertSee('<meta property="article:published_time"', false);
    }

    public function test_home_has_the_organization(): void
    {
        $response = $this->get('/');

        $response->assertSee('<title>Sitio de ejemplo</title>', false);
        $response->assertSee('"@type":"Organization"', false);
    }

    public function test_section_pages_have_their_titles(): void
    {
        $this->get('/eventos')->assertSee('<title>Eventos - Sitio de ejemplo</title>', false);
        $this->get('/articulos')->assertSee('<title>Artículos - Sitio de ejemplo</title>', false);
        $this->get('/galeria')->assertSee('<title>Galería - Sitio de ejemplo</title>', false);
        $this->get('/calendario')->assertSee('<title>Calendario de eventos - Sitio de ejemplo</title>', false);
    }

    public function test_private_and_utility_pages_are_noindex(): void
    {
        $noindex = '<meta name="robots" content="noindex, nofollow" data-seo-server>';

        $this->get('/iniciar-sesion')->assertSee($noindex, false);
        $this->get('/buscar?q=rol')->assertSee($noindex, false);
        $this->actingAs(UserModel::factory()->create())->get('/perfil')->assertSee($noindex, false);
    }

    public function test_maintenance_page_is_noindex(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');

        $response = $this->get('/eventos');

        $response->assertStatus(503);
        $response->assertSee('<title>Estamos en mantenimiento - Sitio de ejemplo</title>', false);
        $response->assertSee('content="noindex, nofollow"', false);
    }

    public function test_client_navigation_receives_the_page_metadata(): void
    {
        $this->event(null);

        $this->get('/eventos/club-de-lectura')->assertInertia(
            fn (Assert $page) => $page
                ->where('seo.scope', 'page')
                ->where('seo.title', 'Club de lectura'),
        );
    }
}
