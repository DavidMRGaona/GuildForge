<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\GuideOverrideServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class GuideOverrideServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GuideOverrideServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(GuideOverrideServiceInterface::class);
    }

    public function test_hiding_and_showing_an_official_guide(): void
    {
        $this->guide('core.signup');
        $this->guide('core.signup', locale: 'en');

        $this->service->setHidden(GuideId::fromString('core.signup'), true);

        $this->assertTrue($this->find('core.signup')->hidden);
        $this->assertSame(1, KnowledgeGuideOverrideModel::query()->count());

        $this->service->setHidden(GuideId::fromString('core.signup'), false);

        $this->assertFalse($this->find('core.signup')->hidden);
        $this->assertSame(0, KnowledgeGuideOverrideModel::query()->count());
    }

    public function test_featured_has_three_states(): void
    {
        $this->guide('tournaments.join', featured: true, source: 'module');
        $id = GuideId::fromString('tournaments.join');

        $this->service->setFeatured($id, false);
        $this->assertFalse($this->find('tournaments.join')->featured);

        $this->service->setHidden($id, true);
        $this->service->setFeatured($id, null);
        $this->assertTrue($this->find('tournaments.join')->featured);
        $this->assertNull(KnowledgeGuideOverrideModel::query()->sole()->featured);

        $this->service->setHidden($id, false);
        $this->assertSame(0, KnowledgeGuideOverrideModel::query()->count());

        $this->service->setFeatured($id, true);
        $this->assertTrue(KnowledgeGuideOverrideModel::query()->sole()->featured);
    }

    public function test_site_and_unknown_guides_cannot_be_overridden(): void
    {
        $this->guide('site.house-rules', source: 'site');

        foreach (['site.house-rules', 'core.missing'] as $id) {
            try {
                $this->service->setHidden(GuideId::fromString($id), true);
                $this->fail('Expected a SiteGuideException');
            } catch (SiteGuideException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, KnowledgeGuideOverrideModel::query()->count());
    }

    /**
     * The repository returns hidden guides too, flagged with `hidden`.
     */
    private function find(string $id): Guide
    {
        $guide = $this->app->make(GuideRepositoryInterface::class)->findActive(GuideId::fromString($id), 'es');
        $this->assertInstanceOf(Guide::class, $guide);

        return $guide;
    }

    private function guide(string $id, string $locale = 'es', bool $featured = false, string $source = 'core'): void
    {
        KnowledgeGuideModel::query()->create([
            'guide_id' => $id, 'locale' => $locale, 'source' => $source, 'module' => $source === 'module' ? 'tournaments' : null,
            'audience' => 'site', 'category_id' => 'community', 'sort_order' => 1, 'title' => 'T', 'summary' => 'S',
            'keywords' => [], 'body_format' => 'markdown', 'body' => 'x', 'body_text' => 'x', 'conditions' => [],
            'related' => [], 'featured' => $featured, 'content_hash' => str_repeat('a', 40), 'active' => true,
        ]);
    }
}
