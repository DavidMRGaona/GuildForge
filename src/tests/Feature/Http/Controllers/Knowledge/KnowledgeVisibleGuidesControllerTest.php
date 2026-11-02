<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Knowledge;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\TestCase;

final class KnowledgeVisibleGuidesControllerTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/ayuda/visibles')->assertUnauthorized();
    }

    public function test_visible_ids_lists_only_visible_site_guides(): void
    {
        $this->knowledgeCategory('getting-started');
        $this->knowledgeCategory('panel-basics', 'panel');
        $this->knowledgeGuide('core.signup');
        $this->knowledgeGuide('core.secret', ['conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
        $this->knowledgeGuide('core.panel-only', ['audience' => 'panel', 'category_id' => 'panel-basics']);
        $this->actingAs(UserModel::factory()->create());

        $this->getJson('/ayuda/visibles')
            ->assertOk()
            ->assertExactJson(['ids' => ['core.signup']]);
    }

    public function test_route_is_not_swallowed_by_the_guide_pattern(): void
    {
        $this->assertSame('/ayuda/visibles', route('knowledge.visible', absolute: false));
    }
}
