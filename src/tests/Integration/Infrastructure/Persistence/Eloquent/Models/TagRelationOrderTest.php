<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TagRelationOrderTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{class-string<ArticleModel|EventModel|GalleryModel>}>
     */
    public static function taggableModels(): array
    {
        return [
            'article' => [ArticleModel::class],
            'event' => [EventModel::class],
            'gallery' => [GalleryModel::class],
        ];
    }

    /**
     * @param  class-string<ArticleModel|EventModel|GalleryModel>  $modelClass
     */
    #[DataProvider('taggableModels')]
    public function test_tags_are_ordered_by_sort_order_then_name(string $modelClass): void
    {
        $model = $modelClass::factory()->create();
        $this->attachTagsOutOfOrder($model);

        $this->assertSame(['Zeta', 'Alfa', 'Beta'], $model->tags->pluck('name')->all());
    }

    /**
     * @param  class-string<ArticleModel|EventModel|GalleryModel>  $modelClass
     */
    #[DataProvider('taggableModels')]
    public function test_eager_loaded_tags_are_ordered_by_sort_order_then_name(string $modelClass): void
    {
        $model = $modelClass::factory()->create();
        $this->attachTagsOutOfOrder($model);

        $loaded = $modelClass::query()->with('tags')->whereKey($model->getKey())->firstOrFail();

        $this->assertSame(['Zeta', 'Alfa', 'Beta'], $loaded->tags->pluck('name')->all());
    }

    /**
     * The relation's ORDER BY must not break aggregates: PostgreSQL rejects an ungrouped ORDER BY column.
     *
     * @param  class-string<ArticleModel|EventModel|GalleryModel>  $modelClass
     */
    #[DataProvider('taggableModels')]
    public function test_tags_can_be_aggregated_through_the_relation(string $modelClass): void
    {
        $model = $modelClass::factory()->create();
        $this->attachTagsOutOfOrder($model);

        $this->assertSame(3, $model->tags()->count());
        $this->assertSame(1, $model->tags()->max('sort_order'));
        $this->assertTrue($model->tags()->exists());
        $this->assertSame(3, $modelClass::query()->withCount('tags')->whereKey($model->getKey())->firstOrFail()->tags_count);
        $this->assertSame(1, $modelClass::query()->whereHas('tags', fn ($query) => $query->where('slug', 'zeta'))->count());
    }

    /**
     * Creates and attaches the tags in an order (by insertion and by id) that differs from
     * the expected one, so the test fails unless the relation orders them.
     */
    private function attachTagsOutOfOrder(ArticleModel|EventModel|GalleryModel $model): void
    {
        $tags = [
            TagModel::factory()->withSortOrder(1)->create(['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Beta', 'slug' => 'beta']),
            TagModel::factory()->withSortOrder(1)->create(['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Alfa', 'slug' => 'alfa']),
            TagModel::factory()->withSortOrder(0)->create(['id' => 'ffffffff-ffff-4fff-bfff-ffffffffffff', 'name' => 'Zeta', 'slug' => 'zeta']),
        ];

        foreach ($tags as $tag) {
            $model->tags()->attach($tag->id);
        }
    }
}
