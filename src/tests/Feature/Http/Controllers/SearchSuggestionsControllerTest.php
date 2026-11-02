<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Search\Services\SearchMetricsServiceInterface;
use App\Application\Search\Services\SiteSearchInterface;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\TestCase;

final class SearchSuggestionsControllerTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));
    }

    public function test_returns_at_most_six_results_and_total(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->document('events', ['title' => 'Partida de dragones '.$i, 'starts_at' => CarbonImmutable::parse('2026-11-21 09:00:00', 'UTC')]);
        }

        $response = $this->getJson(route('search.suggestions', ['q' => 'dragones']));

        $response->assertOk()
            ->assertJsonCount(6, 'results')
            ->assertJsonPath('total', 8)
            ->assertJsonPath('results.0.type', 'events')
            ->assertJsonPath('results.0.typeLabel', 'Evento')
            ->assertJsonPath('results.0.date', 'sáb 21 nov · 10:00')
            ->assertJsonStructure(['results' => [['type', 'typeLabel', 'title', 'url', 'meta', 'image', 'date', 'pastLabel', 'snippet']], 'total']);
    }

    public function test_short_query_returns_empty(): void
    {
        $this->document('events', ['title' => 'a']);

        $this->getJson('/buscar/sugerencias?q=a')->assertOk()->assertExactJson(['results' => [], 'total' => 0]);
        $this->getJson('/buscar/sugerencias')->assertOk()->assertExactJson(['results' => [], 'total' => 0]);
        $this->getJson('/buscar/sugerencias?q=%20%20')->assertOk()->assertExactJson(['results' => [], 'total' => 0]);
    }

    public function test_too_long_or_malformed_query_returns_empty_json(): void
    {
        $this->get('/buscar/sugerencias?q='.str_repeat('a', 101))->assertOk()->assertExactJson(['results' => [], 'total' => 0]);
        $this->get('/buscar/sugerencias?q[]=dragones')->assertOk()->assertExactJson(['results' => [], 'total' => 0]);
    }

    public function test_answers_json_to_plain_and_xhr_requests(): void
    {
        $this->document('events', ['title' => 'Torneo de dragones']);

        $this->get('/buscar/sugerencias?q=dragones')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('total', 1);
        $this->get('/buscar/sugerencias?q=dragones', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('total', 1);
    }

    public function test_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/buscar/sugerencias?q=a')->assertOk();
        }

        $this->getJson('/buscar/sugerencias?q=a')->assertStatus(429);
        // Per IP: another address still gets answers
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->getJson('/buscar/sugerencias?q=a')->assertOk();
    }

    public function test_does_not_record_misses(): void
    {
        $metrics = Mockery::mock(SearchMetricsServiceInterface::class);
        $metrics->shouldNotReceive('recordMiss');
        $this->app->instance(SearchMetricsServiceInterface::class, $metrics);

        $this->getJson('/buscar/sugerencias?q=zzqq')->assertOk()->assertExactJson(['results' => [], 'total' => 0]);
    }

    public function test_help_rows_only_for_signed_in_users(): void
    {
        $this->knowledgeCategory('getting-started');
        $this->knowledgeGuide('core.signup', ['title' => 'Apuntarse a una partida', 'summary' => 'Cómo apuntarse']);

        $this->getJson('/buscar/sugerencias?q=apuntarse')->assertOk()->assertExactJson(['results' => [], 'total' => 0]);

        $user = UserModel::factory()->create();
        $this->app->make(MemberRoleServiceInterface::class)->assignTo($user);
        $this->actingAs($user)
            ->getJson('/buscar/sugerencias?q=apuntarse')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('results.0.type', 'help')
            ->assertJsonPath('results.0.typeLabel', 'Ayuda');
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

        $this->getJson('/buscar/sugerencias?q=dragones')->assertOk()->assertExactJson(['results' => [], 'total' => 0]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function document(string $type, array $attributes): void
    {
        SearchDocumentModel::query()->create([
            'type' => $type,
            'source_id' => (string) Str::uuid(),
            'body' => '',
            'url' => '/'.Str::uuid(),
            'audience' => SearchAudience::Public->value,
            'locale' => 'es',
            ...$attributes,
        ]);
    }
}
