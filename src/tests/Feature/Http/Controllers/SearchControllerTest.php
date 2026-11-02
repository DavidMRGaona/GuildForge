<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\Support\Search\FakeSearchSource;
use Tests\Support\Search\ThrowingSearchSource;
use Tests\TestCase;

final class SearchControllerTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

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

    private function seedHelpGuides(): void
    {
        $this->knowledgeCategory('getting-started');
        $this->knowledgeGuide('core.signup', ['title' => 'Apuntarse a una partida', 'summary' => 'Cómo apuntarse']);
        $this->knowledgeGuide('core.secret', ['title' => 'Apuntarse en secreto', 'summary' => 'Cómo apuntarse', 'conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
        $this->knowledgeGuide('panel.signup', ['title' => 'Apuntarse en el panel', 'summary' => 'Cómo apuntarse', 'audience' => 'panel']);
    }

    public function test_signed_in_users_get_a_help_block(): void
    {
        $this->seedHelpGuides();
        $this->actingAs(UserModel::factory()->create());

        $this->get('/buscar?q=apuntarse')->assertInertia(
            fn (Assert $page) => $page
                ->where('sources.0.key', 'help')
                ->where('sources.0.label', 'Ayuda')
                ->has('sources.0.results', 1)
                ->where('sources.0.results.0.title', 'Apuntarse a una partida')
                ->where('sources.0.results.0.url', route('knowledge.show', 'core.signup'))
                ->where('sources.0.results.0.context', 'Primeros pasos')
                ->has('sources.0.results.0.snippet')
        );
    }

    public function test_guests_get_no_help_block(): void
    {
        $this->seedHelpGuides();

        $this->get('/buscar?q=apuntarse')->assertInertia(
            fn (Assert $page) => $page->where('sources', [])
        );
    }

    public function test_users_who_must_verify_their_email_get_no_help_block(): void
    {
        $this->seedHelpGuides();
        $user = UserModel::factory()->unverified()->create();
        $policy = Mockery::mock(EmailVerificationPolicyInterface::class);
        $policy->shouldReceive('mustVerify')->andReturnTrue();
        $this->app->instance(EmailVerificationPolicyInterface::class, $policy);
        $this->actingAs($user);

        $this->get('/buscar?q=apuntarse')->assertInertia(
            fn (Assert $page) => $page->where('sources', [])
        );
    }

    public function test_help_block_only_contains_visible_site_guides(): void
    {
        $this->seedHelpGuides();
        $this->actingAs(UserModel::factory()->create());

        $this->get('/buscar?q=apuntarse')->assertInertia(
            fn (Assert $page) => $page
                ->has('sources', 1)
                ->has('sources.0.results', 1)
                ->where('sources.0.results.0.title', 'Apuntarse a una partida')
        );
    }

    public function test_sources_without_results_are_left_out(): void
    {
        $this->seedHelpGuides();
        $this->actingAs(UserModel::factory()->create());

        $this->get('/buscar?q=xyznoresults')->assertInertia(
            fn (Assert $page) => $page->where('sources', [])
        );
    }

    public function test_empty_and_short_queries_return_no_sources(): void
    {
        $this->seedHelpGuides();
        $this->actingAs(UserModel::factory()->create());

        $this->get('/buscar')->assertInertia(fn (Assert $page) => $page->where('sources', []));
        $this->get('/buscar?q=a')->assertInertia(fn (Assert $page) => $page->where('sources', []));
    }

    public function test_a_failing_source_is_skipped(): void
    {
        $this->seedHelpGuides();
        $this->actingAs(UserModel::factory()->create());
        $log = Log::spy();
        $registry = $this->app->make(SearchSourceRegistryInterface::class);
        $registry->register(ThrowingSearchSource::class);
        $registry->register(FakeSearchSource::class);

        $this->get('/buscar?q=apuntarse')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('sources', 2)
                    ->where('sources.0.key', 'help')
                    ->where('sources.1.key', 'fake')
            );

        $log->shouldHaveReceived('warning')->once();
    }
}
