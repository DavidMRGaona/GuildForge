<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Knowledge;

use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Pages\KnowledgeBasePage;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\TestCase;

final class HasKnowledgeGuideActionTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->knowledgeCategory('panel-basics', 'panel');
        $this->knowledgeCategory('getting-started');
        $this->knowledgeGuide('core.panel-guide', ['audience' => 'panel', 'category_id' => 'panel-basics']);
        $this->knowledgeGuide('core.hidden-panel', [
            'audience' => 'panel',
            'category_id' => 'panel-basics',
            'conditions' => ['permissions' => ['any' => ['no.such_permission']]],
        ]);
        $this->knowledgeGuide('core.site-guide');
    }

    private function host(): KnowledgeActionHost
    {
        return new KnowledgeActionHost;
    }

    public function test_action_is_null_for_invisible_panel_guides(): void
    {
        $this->actingAs(UserModel::factory()->create());
        $host = $this->host();

        $this->assertNull($host->action('core.hidden-panel'));
        $this->assertNull($host->action('core.site-guide'));
        $this->assertNull($host->action('core.missing'));
    }

    public function test_a_failing_visibility_check_hides_the_action_and_is_reported(): void
    {
        Exceptions::fake();
        $visibility = Mockery::mock(GuideVisibilityServiceInterface::class);
        $visibility->shouldReceive('findVisible')->andThrow(new RuntimeException('visibility broken'));
        $this->app->instance(GuideVisibilityServiceInterface::class, $visibility);
        $this->actingAs(UserModel::factory()->create());

        $this->assertNull($this->host()->action('core.panel-guide'));
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_action_is_null_for_guests(): void
    {
        $this->assertNull($this->host()->action('core.panel-guide'));
    }

    public function test_action_links_to_the_panel_help_page(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $action = $this->host()->action('core.panel-guide');

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('heroicon-o-question-mark-circle', $action->getIcon());
        $this->assertSame(__('knowledge.contextual_help'), $action->getLabel());
        $this->assertSame(KnowledgeBasePage::getUrl(['guia' => 'core.panel-guide']), $action->getUrl());
    }
}

final class KnowledgeActionHost
{
    use HasKnowledgeGuideAction;

    public function action(string $id): ?Action
    {
        return $this->knowledgeGuideAction($id);
    }
}
