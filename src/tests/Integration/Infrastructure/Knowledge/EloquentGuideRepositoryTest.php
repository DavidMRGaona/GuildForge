<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideAliasModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class EloquentGuideRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GuideRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(GuideRepositoryInterface::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function guide(string $guideId, array $overrides = []): KnowledgeGuideModel
    {
        return KnowledgeGuideModel::query()->create([
            'guide_id' => $guideId,
            'locale' => 'es',
            'source' => 'core',
            'module' => null,
            'audience' => 'site',
            'category_id' => 'getting-started',
            'sort_order' => 10,
            'title' => 'Guide '.$guideId,
            'summary' => 'Summary',
            'keywords' => ['one', 'two'],
            'body_format' => 'markdown',
            'body' => '# Body',
            'body_text' => 'Body',
            'conditions' => ['capabilities' => ['!tournaments'], 'permissions' => ['any' => ['events.view_any']]],
            'related' => ['core.other'],
            'featured' => false,
            'content_hash' => str_repeat('a', 40),
            'active' => true,
            ...$overrides,
        ]);
    }

    public function test_all_active_returns_only_active_guides_of_the_audience_and_locale(): void
    {
        $this->guide('core.visible');
        $this->guide('core.inactive', ['active' => false]);
        $this->guide('core.panel', ['audience' => 'panel']);
        $this->guide('core.english', ['locale' => 'en']);

        $guides = $this->repository->allActive(GuideAudience::Site, 'es');

        $this->assertCount(1, $guides);
        $this->assertSame('core.visible', $guides[0]->id->value());
        $this->assertSame(GuideBodyFormat::Markdown, $guides[0]->bodyFormat);
        $this->assertSame(['one', 'two'], $guides[0]->keywords);
        $this->assertSame('core.other', $guides[0]->related[0]->value());
        $this->assertSame(['tournaments'], $guides[0]->conditions->negatedCapabilities);
        $this->assertSame(['events.view_any'], $guides[0]->conditions->permissionsAny);
        $this->assertFalse($guides[0]->hidden);
        $this->assertNull($this->repository->findActive(GuideId::fromString('core.inactive'), 'es'));
        $this->assertNotNull($this->repository->findActive(GuideId::fromString('core.visible'), 'es'));
    }

    public function test_override_marks_guides_hidden_and_featured(): void
    {
        $this->guide('core.hidden');
        $this->guide('core.pinned');
        $this->guide('core.native', ['featured' => true]);
        KnowledgeGuideOverrideModel::query()->create(['guide_id' => 'core.hidden', 'hidden' => true, 'featured' => false]);
        KnowledgeGuideOverrideModel::query()->create(['guide_id' => 'core.pinned', 'hidden' => false, 'featured' => true]);

        $byId = [];
        foreach ($this->repository->allActive(GuideAudience::Site, 'es') as $guide) {
            $byId[$guide->id->value()] = $guide;
        }

        $this->assertTrue($byId['core.hidden']->hidden);
        $this->assertFalse($byId['core.hidden']->featured);
        $this->assertFalse($byId['core.pinned']->hidden);
        $this->assertTrue($byId['core.pinned']->featured);
        $this->assertTrue($byId['core.native']->featured);
    }

    public function test_resolve_alias_returns_the_current_id(): void
    {
        KnowledgeGuideAliasModel::query()->create(['alias_id' => 'core.old', 'guide_id' => 'core.new']);

        $this->assertSame('core.new', $this->repository->resolveAlias(GuideId::fromString('core.old'))?->value());
        $this->assertNull($this->repository->resolveAlias(GuideId::fromString('core.missing')));
    }

    public function test_categories_are_ordered_and_filtered_by_audience(): void
    {
        KnowledgeCategoryModel::query()->create(['category_id' => 'b', 'module' => null, 'audience' => 'site', 'sort_order' => 20, 'icon' => 'x', 'label_key' => 'knowledge.categories.b']);
        KnowledgeCategoryModel::query()->create(['category_id' => 'a', 'module' => 'tournaments', 'audience' => 'site', 'sort_order' => 10, 'icon' => 'x', 'label_key' => 'knowledge.categories.a']);
        KnowledgeCategoryModel::query()->create(['category_id' => 'p', 'module' => null, 'audience' => 'panel', 'sort_order' => 1, 'icon' => 'x', 'label_key' => 'knowledge.categories.p']);

        $categories = $this->repository->categories(GuideAudience::Site);

        $this->assertSame(['a', 'b'], array_map(static fn ($c): string => $c->id, $categories));
        $this->assertSame('tournaments', $categories[0]->module);
        $this->assertSame(GuideAudience::Site, $categories[0]->audience);
    }

    public function test_guides_are_ordered_by_sort_order_then_title(): void
    {
        $this->guide('core.c', ['sort_order' => 5, 'title' => 'Zeta']);
        $this->guide('core.a', ['sort_order' => 5, 'title' => 'Alfa']);
        $this->guide('core.first', ['sort_order' => 1, 'title' => 'Omega']);

        $ids = array_map(
            static fn ($g): string => $g->id->value(),
            $this->repository->allActive(GuideAudience::Site, 'es'),
        );

        $this->assertSame(['core.first', 'core.a', 'core.c'], $ids);
    }

    public function test_has_locale_checks_for_active_guides_in_the_locale(): void
    {
        $this->guide('core.one');
        $this->guide('core.gone', ['locale' => 'en', 'active' => false]);

        $this->assertTrue($this->repository->hasLocale('es'));
        $this->assertFalse($this->repository->hasLocale('en'));
    }
}
