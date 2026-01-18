<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_minimum_two_characters(): void
    {
        $response = $this->get('/buscar?q=a');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'a')
                ->where('events', [])
                ->where('articles', [])
                ->where('error', 'minChars')
        );
    }

    public function test_search_with_empty_query_returns_empty_results(): void
    {
        $response = $this->get('/buscar');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', '')
                ->where('events', [])
                ->where('articles', [])
                ->where('error', null)
        );
    }

    public function test_search_finds_events_by_title(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Torneo Warhammer 40K',
            'slug' => 'torneo-warhammer-40k',
            'description' => 'Un torneo épico',
        ]);
        EventModel::factory()->published()->create([
            'title' => 'Partida de Rol',
            'slug' => 'partida-de-rol',
            'description' => 'Aventura medieval',
        ]);

        $response = $this->get('/buscar?q=warhammer');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'warhammer')
                ->has('events', 1)
                ->where('events.0.title', 'Torneo Warhammer 40K')
                ->where('events.0.slug', 'torneo-warhammer-40k')
                ->where('error', null)
        );
    }

    public function test_search_finds_events_by_description(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Torneo de Miniaturas',
            'slug' => 'torneo-miniaturas',
            'description' => 'Torneo de Warhammer 40K con premios',
        ]);
        EventModel::factory()->published()->create([
            'title' => 'Taller de Pintura',
            'slug' => 'taller-pintura',
            'description' => 'Aprende a pintar miniaturas',
        ]);

        $response = $this->get('/buscar?q=40K');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', '40K')
                ->has('events', 1)
                ->where('events.0.title', 'Torneo de Miniaturas')
                ->where('error', null)
        );
    }

    public function test_search_finds_articles_by_title(): void
    {
        ArticleModel::factory()->published()->create([
            'title' => 'Guía de pintura para principiantes',
            'slug' => 'guia-pintura-principiantes',
            'content' => 'Contenido del artículo',
        ]);
        ArticleModel::factory()->published()->create([
            'title' => 'Reglas de Warhammer',
            'slug' => 'reglas-warhammer',
            'content' => 'Otro contenido',
        ]);

        $response = $this->get('/buscar?q=pintura');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'pintura')
                ->has('articles', 1)
                ->where('articles.0.title', 'Guía de pintura para principiantes')
                ->where('articles.0.slug', 'guia-pintura-principiantes')
                ->where('error', null)
        );
    }

    public function test_search_finds_articles_by_content(): void
    {
        ArticleModel::factory()->published()->create([
            'title' => 'Tutorial completo',
            'slug' => 'tutorial-completo',
            'content' => 'Aprende a pintar miniaturas con esta guía detallada',
        ]);
        ArticleModel::factory()->published()->create([
            'title' => 'Otra guía',
            'slug' => 'otra-guia',
            'content' => 'Contenido diferente sobre actividades',
        ]);

        $response = $this->get('/buscar?q=miniaturas');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'miniaturas')
                ->has('articles', 1)
                ->where('articles.0.title', 'Tutorial completo')
                ->where('error', null)
        );
    }

    public function test_search_only_returns_published_content(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Evento Publicado',
            'slug' => 'evento-publicado',
            'description' => 'Buscar esta palabra clave',
        ]);
        EventModel::factory()->draft()->create([
            'title' => 'Evento Borrador',
            'slug' => 'evento-borrador',
            'description' => 'Buscar esta palabra clave',
        ]);

        ArticleModel::factory()->published()->create([
            'title' => 'Artículo Publicado',
            'slug' => 'articulo-publicado',
            'content' => 'Buscar esta palabra clave',
        ]);
        ArticleModel::factory()->draft()->create([
            'title' => 'Artículo Borrador',
            'slug' => 'articulo-borrador',
            'content' => 'Buscar esta palabra clave',
        ]);

        $response = $this->get('/buscar?q=clave');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'clave')
                ->has('events', 1)
                ->where('events.0.title', 'Evento Publicado')
                ->has('articles', 1)
                ->where('articles.0.title', 'Artículo Publicado')
                ->where('error', null)
        );
    }

    public function test_search_returns_no_results_message(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Torneo de Warhammer',
            'slug' => 'torneo-warhammer',
            'description' => 'Descripción del torneo',
        ]);
        ArticleModel::factory()->published()->create([
            'title' => 'Guía de pintura',
            'slug' => 'guia-pintura',
            'content' => 'Contenido de la guía',
        ]);

        $response = $this->get('/buscar?q=xyznoresults');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'xyznoresults')
                ->where('events', [])
                ->where('articles', [])
                ->where('error', null)
        );
    }

    public function test_search_is_case_insensitive(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'WARHAMMER Tournament',
            'slug' => 'warhammer-tournament',
            'description' => 'Epic tournament',
        ]);
        EventModel::factory()->published()->create([
            'title' => 'warhammer league',
            'slug' => 'warhammer-league',
            'description' => 'Regular league',
        ]);

        $response = $this->get('/buscar?q=warhammer');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'warhammer')
                ->has('events', 2)
                ->where('error', null)
        );
    }
}
