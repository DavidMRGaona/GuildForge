<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class GuideVisibilityServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GuideVisibilityServiceInterface $visibility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visibility = $this->app->make(GuideVisibilityServiceInterface::class);
    }

    private function category(string $id, string $audience = 'site'): void
    {
        KnowledgeCategoryModel::query()->create(['category_id' => $id, 'module' => null, 'audience' => $audience, 'sort_order' => 10, 'icon' => 'x', 'label_key' => 'knowledge.categories.'.$id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function guide(string $guideId, array $overrides = []): void
    {
        KnowledgeGuideModel::query()->create([
            'guide_id' => $guideId,
            'locale' => 'es',
            'source' => 'core',
            'module' => null,
            'audience' => 'site',
            'category_id' => 'basics',
            'sort_order' => 10,
            'title' => 'Guide '.$guideId,
            'summary' => 'Summary',
            'keywords' => [],
            'body_format' => 'markdown',
            'body' => '# Body',
            'body_text' => 'Body',
            'conditions' => [],
            'related' => [],
            'featured' => false,
            'content_hash' => str_repeat('a', 40),
            'active' => true,
            ...$overrides,
        ]);
    }

    public function test_hidden_guides_are_never_visible(): void
    {
        $this->category('basics');
        $this->guide('core.shown');
        $this->guide('core.secret');
        KnowledgeGuideOverrideModel::query()->create(['guide_id' => 'core.secret', 'hidden' => true, 'featured' => false]);
        $user = UserModel::factory()->create();

        $ids = array_map(fn ($g): string => $g->id->value(), $this->visibility->visibleGuides($user, GuideAudience::Site));

        $this->assertSame(['core.shown'], $ids);
        $this->assertNull($this->visibility->findVisible($user, GuideId::fromString('core.secret'), GuideAudience::Site));
        $this->assertNotNull($this->visibility->findVisible($user, GuideId::fromString('core.shown'), GuideAudience::Site));
    }

    public function test_categories_without_visible_guides_are_omitted(): void
    {
        $this->category('basics');
        $this->category('restricted');
        $this->category('empty');
        $this->guide('core.one');
        $this->guide('core.two');
        $this->guide('core.locked', ['category_id' => 'restricted', 'conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
        $user = UserModel::factory()->create();

        $categories = $this->visibility->visibleCategories($user, GuideAudience::Site);

        $this->assertCount(1, $categories);
        $this->assertSame('basics', $categories[0]['category']->id);
        $this->assertSame(2, $categories[0]['guideCount']);
    }

    public function test_find_visible_returns_null_for_guides_of_the_other_audience(): void
    {
        $this->category('basics');
        $this->category('panel-basics', 'panel');
        $this->guide('core.panel-guide', ['audience' => 'panel', 'category_id' => 'panel-basics']);
        $user = UserModel::factory()->create();

        $this->assertNull($this->visibility->findVisible($user, GuideId::fromString('core.panel-guide'), GuideAudience::Site));
        $this->assertNotNull($this->visibility->findVisible($user, GuideId::fromString('core.panel-guide'), GuideAudience::Panel));
    }

    public function test_guides_in_another_locale_use_the_fallback_when_the_current_one_has_none(): void
    {
        $this->category('basics');
        $this->guide('core.spanish');
        $this->app->setLocale('en');
        $user = UserModel::factory()->create();

        $this->assertCount(1, $this->visibility->visibleGuides($user, GuideAudience::Site));
    }
}
