<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Search\Services\SearchMetricsServiceInterface;
use App\Application\Search\Services\SiteSearchInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Search\Enums\SearchAudience;
use App\Http\Controllers\SearchController;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\Support\Search\PublishedSearchRecords;
use Tests\TestCase;

final class SearchControllerTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));
    }

    public function test_renders_tabs_with_counts_and_mixed_results(): void
    {
        $this->document('events', [
            'title' => 'Torneo de dragones',
            'body' => 'Una tarde con dragones y dados',
            'meta' => 'Sala grande',
            'image_public_id' => 'events/2026/11/dragon',
            'starts_at' => CarbonImmutable::parse('2026-11-21 09:00:00', 'UTC'),
        ]);
        $this->document('events', [
            'title' => 'Liga de dragones',
            'starts_at' => CarbonImmutable::parse('2026-10-03 16:30:00', 'UTC'),
        ]);
        $this->document('articles', [
            'title' => 'Pintar dragones',
            'published_at' => CarbonImmutable::parse('2026-09-15 08:00:00', 'UTC'),
        ]);
        $this->document('galleries', ['title' => 'Sin coincidencias']);

        $this->get('/buscar?q=dragones')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'dragones')
                ->where('type', null)
                ->where('tabs', [
                    ['key' => null, 'label' => 'Todo', 'count' => 3],
                    ['key' => 'events', 'label' => 'Eventos', 'count' => 2],
                    ['key' => 'articles', 'label' => 'Artículos', 'count' => 1],
                ])
                ->where('total', 3)
                ->where('page', 1)
                ->where('hasMore', false)
                ->where('error', null)
                ->has('seo')
                ->missing('events')
                ->missing('articles')
                ->missing('sources')
                ->has('results', 3)
                ->where('results.0', [
                    'type' => 'events',
                    'typeLabel' => 'Evento',
                    'title' => 'Torneo de dragones',
                    'url' => '/torneo-de-dragones',
                    'meta' => 'Sala grande',
                    'image' => 'events/2026/11/dragon',
                    'date' => 'sáb 21 nov · 10:00',
                    'pastLabel' => null,
                    'snippet' => [
                        ['text' => 'Una tarde con ', 'match' => false],
                        ['text' => 'dragones', 'match' => true],
                        ['text' => ' y dados', 'match' => false],
                    ],
                ])
        );

        $results = $this->results('/buscar?q=dragones');
        $byTitle = array_column($results, null, 'title');
        $this->assertSame('sáb 3 oct · 18:30', $byTitle['Liga de dragones']['date']);
        $this->assertSame('Celebrado', $byTitle['Liga de dragones']['pastLabel']);
        $this->assertSame('15 sep 2026', $byTitle['Pintar dragones']['date']);
        $this->assertSame('Artículo', $byTitle['Pintar dragones']['typeLabel']);
        $this->assertNull($byTitle['Pintar dragones']['pastLabel']);
        $this->assertNull($byTitle['Pintar dragones']['image']);
    }

    public function test_results_are_merged_by_the_next_page_request(): void
    {
        $this->document('events', ['title' => 'Torneo de dragones']);

        $page = $this->get('/buscar?q=dragones')->viewData('page');

        $this->assertIsArray($page);
        $this->assertContains('results', $page['mergeProps'] ?? []);
        // A row repeated by the next page replaces the one shown, matched on its URL
        $this->assertSame(['results.url'], $page['matchPropsOn'] ?? null);
    }

    public function test_tab_filters_results(): void
    {
        $this->document('events', ['title' => 'Torneo de dragones']);
        $this->document('articles', ['title' => 'Pintar dragones']);

        $this->get('/buscar?q=dragones&tipo=articles')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('type', 'articles')
                ->where('total', 1)
                ->has('tabs', 3)
                ->where('tabs.0.count', 2)
                ->has('results', 1)
                ->where('results.0.title', 'Pintar dragones')
        );
    }

    public function test_content_unpublished_before_its_document_is_refreshed_is_not_shown(): void
    {
        $this->document('events', ['title' => 'Torneo de dragones']);
        $this->document('articles', ['title' => 'Pintar dragones']);
        // Unpublished while the queued refresh of its document has not run yet
        $this->document('articles', ['title' => 'Dragones retirados', 'source_id' => PublishedSearchRecords::sourceId('articles', published: false)]);

        $this->get('/buscar?q=dragones')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('total', 2)
                ->where('tabs.0.count', 2)
                ->where('tabs.2.count', 1)
                ->has('results', 2)
                ->where('hasMore', false)
        );
    }

    public function test_unknown_tab_falls_back_to_all(): void
    {
        $this->document('events', ['title' => 'Torneo de dragones']);
        $this->document('articles', ['title' => 'Pintar dragones']);

        foreach (['nope', 'help', ''] as $tab) {
            $this->get('/buscar?q=dragones&tipo='.$tab)->assertOk()->assertInertia(
                fn (Assert $page) => $page
                    ->where('type', null)
                    ->where('total', 2)
                    ->has('results', 2)
            );
        }
    }

    public function test_second_page_returns_next_results_and_has_more(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->document('events', ['title' => 'Partida de dragones '.$i]);
        }

        $this->get('/buscar?q=dragones')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('page', 1)
                ->where('total', 25)
                ->where('hasMore', true)
                ->has('results', 20)
        );

        $first = array_column($this->results('/buscar?q=dragones'), 'title');
        $second = array_column($this->results('/buscar?q=dragones&pagina=2'), 'title');

        $this->assertCount(5, $second);
        $this->assertSame([], array_intersect($first, $second));

        $this->get('/buscar?q=dragones&pagina=2')->assertInertia(
            fn (Assert $page) => $page->where('page', 2)->where('hasMore', false)
        );
        $this->get('/buscar?q=dragones&pagina=9')->assertInertia(
            fn (Assert $page) => $page->where('page', 9)->where('hasMore', false)->where('results', [])->where('total', 25)
        );
    }

    public function test_a_page_left_empty_by_the_check_is_gone_past(): void
    {
        // The whole first page was unpublished before its documents were refreshed
        for ($i = 1; $i <= SearchController::PER_PAGE; $i++) {
            $this->document('articles', [
                'title' => 'Dragones retirados '.$i,
                'published_at' => CarbonImmutable::parse('2026-11-14 12:00:00', 'UTC')->subMinutes($i),
                'source_id' => PublishedSearchRecords::sourceId('articles', published: false),
            ]);
        }
        $this->document('articles', ['title' => 'Dragones a la vista', 'published_at' => CarbonImmutable::parse('2026-11-01 12:00:00', 'UTC')]);

        // The emptied page is gone past: the answer is the next page, so "show more" carries on from it
        $this->get('/buscar?q=dragones')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('page', 2)
                ->has('results', 1)
                ->where('results.0.title', 'Dragones a la vista')
                ->where('total', 21)
                ->where('hasMore', false)
        );

        $this->get('/buscar?q=dragones&pagina=2')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('page', 2)
                ->has('results', 1)
                ->where('results.0.title', 'Dragones a la vista')
                ->where('total', 21)
                ->where('hasMore', false)
        );
    }

    public function test_tab_and_page_sent_as_arrays_are_ignored(): void
    {
        $this->document('events', ['title' => 'Torneo de dragones']);

        // «?tipo[]=…&pagina[]=…» reads as no tab and the first page, never as an error
        $this->get('/buscar?q=dragones&tipo[]=events&pagina[]=2')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('type', null)
                ->where('page', 1)
                ->where('total', 1)
        );
    }

    public function test_invalid_page_is_read_as_the_first(): void
    {
        $this->document('events', ['title' => 'Torneo de dragones']);

        foreach (['0', '-3', 'abc', '99999999999999999999999'] as $value) {
            $this->get('/buscar?q=dragones&pagina='.$value)->assertOk()->assertInertia(
                fn (Assert $page) => $page->where('page', 1)->has('results', 1)
            );
        }
    }

    public function test_records_miss_only_for_first_page_without_results(): void
    {
        $this->document('events', ['title' => 'Torneo de dragones']);
        $metrics = Mockery::mock(SearchMetricsServiceInterface::class);
        $metrics->shouldReceive('recordMiss')->once()->with('zzqq');
        $this->app->instance(SearchMetricsServiceInterface::class, $metrics);

        $this->get('/buscar?q=zzqq')->assertOk();
        $this->get('/buscar?q=zzqq&pagina=2')->assertOk();
        $this->get('/buscar?q=zzqq&tipo=events')->assertOk();
        $this->get('/buscar?q=dragones')->assertOk();
        $this->get('/buscar?q=z')->assertOk();
        $this->get('/buscar')->assertOk();
    }

    public function test_guest_does_not_see_help(): void
    {
        $this->seedHelpGuides();
        $this->document('events', ['title' => 'Apuntarse al torneo']);

        $this->get('/buscar?q=apuntarse')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('tabs', [
                    ['key' => null, 'label' => 'Todo', 'count' => 1],
                    ['key' => 'events', 'label' => 'Eventos', 'count' => 1],
                ])
                ->has('results', 1)
                ->where('results.0.type', 'events')
        );
    }

    public function test_signed_in_user_sees_visible_site_guides_as_help_rows(): void
    {
        $this->seedHelpGuides();
        $this->document('events', ['title' => 'Apuntarse al torneo']);
        $this->actingAs($this->member());

        $this->get('/buscar?q=apuntarse')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('tabs', [
                    ['key' => null, 'label' => 'Todo', 'count' => 2],
                    ['key' => 'events', 'label' => 'Eventos', 'count' => 1],
                    ['key' => 'help', 'label' => 'Ayuda', 'count' => 1],
                ])
                ->has('results', 2)
                ->where('results.1.type', 'help')
                ->where('results.1.typeLabel', 'Ayuda')
                ->where('results.1.title', 'Apuntarse a una partida')
                ->where('results.1.url', '/ayuda/core.signup')
                ->where('results.1.date', null)
                ->where('results.1.pastLabel', null)
                ->where('results.1.image', null)
        );

        $this->get('/buscar?q=apuntarse&tipo=help')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('type', 'help')
                ->where('total', 1)
                ->has('results', 1)
                ->where('results.0.type', 'help')
        );
    }

    public function test_user_who_must_verify_does_not_see_help(): void
    {
        $this->seedHelpGuides();
        $settings = app(SettingsServiceInterface::class);
        $settings->set('auth_email_verification_required', '1');
        $settings->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        $user = UserModel::factory()->unverified()->create();
        $this->app->make(MemberRoleServiceInterface::class)->assignTo($user);
        $this->actingAs($user);

        $this->get('/buscar?q=apuntarse')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('tabs', [])
                ->where('results', [])
                ->where('total', 0)
        );
        $this->get('/buscar?q=apuntarse&tipo=help')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('type', null)->where('results', [])
        );
    }

    public function test_query_too_short_returns_min_chars(): void
    {
        $this->document('events', ['title' => 'a']);

        $this->get('/buscar?q=a')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'a')
                ->where('error', 'minChars')
                ->where('tabs', [])
                ->where('results', [])
                ->where('total', 0)
                ->where('hasMore', false)
        );
    }

    public function test_empty_query_returns_an_empty_page(): void
    {
        $this->get('/buscar')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', '')
                ->where('type', null)
                ->where('error', null)
                ->where('tabs', [])
                ->where('results', [])
                ->where('total', 0)
                ->where('page', 1)
                ->where('hasMore', false)
        );
    }

    public function test_survives_engine_failure(): void
    {
        $this->app->instance(SiteSearchInterface::class, new class implements SiteSearchInterface
        {
            public function search(SiteSearchQueryDTO $query): SiteSearchResultDTO
            {
                throw new RuntimeException('Search engine is down');
            }
        });
        $metrics = Mockery::mock(SearchMetricsServiceInterface::class);
        $metrics->shouldNotReceive('recordMiss');
        $this->app->instance(SearchMetricsServiceInterface::class, $metrics);
        $log = Log::spy();

        $this->get('/buscar?q=dragones')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'dragones')
                ->where('tabs', [])
                ->where('results', [])
                ->where('total', 0)
                ->where('hasMore', false)
                ->where('error', null)
        );

        // Answered with nothing, but never silently
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_starts_with($message, '[SiteSearch] Search failed'))->once();
    }

    private function seedHelpGuides(): void
    {
        $this->knowledgeCategory('getting-started');
        $this->knowledgeGuide('core.signup', ['title' => 'Apuntarse a una partida', 'summary' => 'Cómo apuntarse']);
        $this->knowledgeGuide('core.secret', ['title' => 'Apuntarse en secreto', 'summary' => 'Cómo apuntarse', 'conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
        $this->knowledgeGuide('panel.signup', ['title' => 'Apuntarse en el panel', 'summary' => 'Cómo apuntarse', 'audience' => 'panel']);
    }

    private function member(): UserModel
    {
        $user = UserModel::factory()->create();
        $this->app->make(MemberRoleServiceInterface::class)->assignTo($user);

        return $user;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function results(string $url): array
    {
        $page = $this->get($url)->viewData('page');
        $this->assertIsArray($page);

        return $page['props']['results'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function document(string $type, array $attributes): void
    {
        SearchDocumentModel::query()->create([
            'type' => $type,
            // The core types check their results against the record
            'source_id' => PublishedSearchRecords::sourceId($type),
            'body' => '',
            'url' => '/'.Str::slug((string) ($attributes['title'] ?? Str::uuid())),
            'audience' => SearchAudience::Public->value,
            'locale' => 'es',
            ...$attributes,
        ]);
    }
}
