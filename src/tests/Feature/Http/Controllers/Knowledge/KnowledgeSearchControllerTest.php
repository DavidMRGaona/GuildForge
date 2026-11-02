<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Knowledge;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\TestCase;

final class KnowledgeSearchControllerTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->knowledgeCategory('getting-started');
        $this->knowledgeGuide('core.signup', ['title' => 'Apuntarse a una partida', 'summary' => 'Cómo apuntarse']);
        $this->knowledgeGuide('core.secret', ['title' => 'Apuntarse en secreto', 'summary' => 'Cómo apuntarse', 'conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->getJson('/ayuda/buscar?q=apuntarse')->assertUnauthorized();
    }

    public function test_search_returns_visible_hits_as_json(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $response = $this->getJson('/ayuda/buscar?q=apuntarse')->assertOk();

        $response->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', 'core.signup')
            ->assertJsonPath('results.0.title', 'Apuntarse a una partida')
            ->assertJsonPath('results.0.categoryId', 'getting-started')
            ->assertJsonStructure(['results' => [['id', 'title', 'categoryId', 'snippet']]]);
    }

    public function test_short_queries_return_no_results(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->getJson('/ayuda/buscar?q=a')->assertOk()->assertExactJson(['results' => []]);
        $this->getJson('/ayuda/buscar')->assertOk()->assertExactJson(['results' => []]);
    }

    public function test_too_long_queries_are_rejected(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->getJson('/ayuda/buscar?q='.str_repeat('a', 101))->assertUnprocessable();
    }

    public function test_search_is_throttled(): void
    {
        $this->actingAs(UserModel::factory()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/ayuda/buscar?q=apuntarse')->assertOk();
        }

        $this->getJson('/ayuda/buscar?q=apuntarse')->assertStatus(429);
    }
}
