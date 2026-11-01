<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Knowledge;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\TestCase;

final class KnowledgeFeedbackControllerTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->knowledgeCategory('getting-started');
        $this->knowledgeGuide('core.signup', ['title' => 'Apuntarse a una partida', 'summary' => 'Cómo apuntarse']);
        $this->knowledgeGuide('core.secret', ['conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->post('/ayuda/core.signup/voto', ['helpful' => true])->assertRedirect('/iniciar-sesion');
    }

    public function test_a_vote_is_counted_and_answers_204(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->postJson('/ayuda/core.signup/voto', ['helpful' => true])->assertNoContent();

        $this->assertSame(1, (int) DB::table('knowledge_guide_feedback')->where('guide_id', 'core.signup')->value('helpful_count'));
    }

    public function test_a_negative_vote_is_counted(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->postJson('/ayuda/core.signup/voto', ['helpful' => false])->assertNoContent();

        $this->assertSame(1, (int) DB::table('knowledge_guide_feedback')->value('not_helpful_count'));
    }

    public function test_second_vote_in_the_same_session_is_ignored(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->postJson('/ayuda/core.signup/voto', ['helpful' => true])->assertNoContent();
        $this->postJson('/ayuda/core.signup/voto', ['helpful' => false])->assertNoContent();

        $row = DB::table('knowledge_guide_feedback')->sole();
        $this->assertSame(1, (int) $row->helpful_count);
        $this->assertSame(0, (int) $row->not_helpful_count);
    }

    public function test_vote_on_invisible_guide_returns_404(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->postJson('/ayuda/core.secret/voto', ['helpful' => true])->assertNotFound();
        $this->postJson('/ayuda/core.missing/voto', ['helpful' => true])->assertNotFound();
        $this->assertSame(0, DB::table('knowledge_guide_feedback')->count());
    }

    public function test_helpful_is_required_and_boolean(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->postJson('/ayuda/core.signup/voto', [])->assertUnprocessable()->assertJsonValidationErrors('helpful');
        $this->postJson('/ayuda/core.signup/voto', ['helpful' => 'maybe'])->assertUnprocessable();
    }

    public function test_voting_is_throttled(): void
    {
        $this->actingAs(UserModel::factory()->create());

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/ayuda/core.signup/voto', ['helpful' => true])->assertNoContent();
        }

        $this->postJson('/ayuda/core.signup/voto', ['helpful' => true])->assertTooManyRequests();
    }

    public function test_results_page_records_a_miss_when_nothing_matches(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda?q=Zzzqx')->assertOk();

        $this->assertSame(1, (int) DB::table('knowledge_search_misses')->where('normalized_query', 'zzzqx')->value('count'));
    }

    public function test_results_page_does_not_record_when_something_matches(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda?q=apuntarse')->assertOk();

        $this->assertSame(0, DB::table('knowledge_search_misses')->count());
    }

    public function test_short_or_invalid_queries_are_not_recorded(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda?q=a')->assertOk();
        $this->get('/ayuda?q='.str_repeat('z', 101));

        $this->assertSame(0, DB::table('knowledge_search_misses')->count());
    }

    public function test_typeahead_searches_do_not_record_misses(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->getJson('/ayuda/buscar?q=zzzqx')->assertOk();

        $this->assertSame(0, DB::table('knowledge_search_misses')->count());
    }
}
