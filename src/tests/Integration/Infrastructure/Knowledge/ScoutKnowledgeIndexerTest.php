<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Search\ScoutKnowledgeIndexer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class ScoutKnowledgeIndexerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_refresh_pushes_active_rows_and_removes_inactive_ones(): void
    {
        $this->guide('site.rules', 'es', true);
        $this->guide('site.rules', 'en', false);
        $this->guide('site.other', 'es', true);

        $engine = $this->engine(function (MockInterface $engine): void {
            $engine->shouldReceive('update')->once()->withArgs(fn (Collection $guides): bool => $guides->pluck('locale')->all() === ['es'] && $guides->pluck('guide_id')->all() === ['site.rules']);
            $engine->shouldReceive('delete')->once()->withArgs(fn (Collection $guides): bool => $guides->pluck('locale')->values()->all() === ['en']);
        });

        $this->indexer($engine)->refresh(GuideId::fromString('site.rules'));
    }

    public function test_forget_removes_every_row_of_the_guide(): void
    {
        $this->guide('site.rules', 'es', true);
        $this->guide('site.rules', 'en', false);

        $engine = $this->engine(function (MockInterface $engine): void {
            $engine->shouldReceive('delete')->once()->withArgs(fn (Collection $guides): bool => $guides->count() === 2);
            $engine->shouldNotReceive('update');
        });

        $this->indexer($engine)->forget(GuideId::fromString('site.rules'));
    }

    public function test_engine_failures_are_reported_not_thrown(): void
    {
        Exceptions::fake();
        $this->guide('site.rules', 'es', true);

        $engine = $this->engine(function (MockInterface $engine): void {
            $engine->shouldReceive('update')->andThrow(new RuntimeException('down'));
            $engine->shouldReceive('delete')->andThrow(new RuntimeException('down'));
        });

        $this->indexer($engine)->refresh(GuideId::fromString('site.rules'));
        $this->indexer($engine)->forget(GuideId::fromString('site.rules'));

        Exceptions::assertReported(RuntimeException::class);
    }

    private function engine(callable $expectations): Engine
    {
        $engine = Mockery::mock(Engine::class);
        $expectations($engine);

        return $engine;
    }

    private function indexer(Engine $engine): ScoutKnowledgeIndexer
    {
        $this->app->make(EngineManager::class)->extend('spy', fn (): Engine => $engine);
        config(['scout.driver' => 'spy']);

        return $this->app->make(ScoutKnowledgeIndexer::class);
    }

    private function guide(string $id, string $locale, bool $active): void
    {
        KnowledgeGuideModel::query()->create([
            'guide_id' => $id, 'locale' => $locale, 'source' => 'site', 'module' => null, 'audience' => 'site',
            'category_id' => 'community', 'sort_order' => 1, 'title' => 'T', 'summary' => 'S', 'keywords' => [],
            'body_format' => 'html', 'body' => '<p>x</p>', 'body_text' => 'x', 'conditions' => [], 'related' => [],
            'featured' => false, 'content_hash' => str_repeat('a', 40), 'active' => $active,
        ]);
    }
}
