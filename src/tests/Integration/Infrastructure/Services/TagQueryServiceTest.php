<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\Response\TagHierarchyDTO;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Services\TagQueryService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class TagQueryServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TagQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagQueryService();
    }

    public function test_get_all_in_hierarchical_order_returns_empty_array_when_no_tags(): void
    {
        $result = $this->service->getAllInHierarchicalOrder();

        $this->assertEmpty($result);
    }

    public function test_get_all_in_hierarchical_order_returns_tags_in_order(): void
    {
        $parent = TagModel::factory()->create([
            'name' => 'Parent',
            'slug' => 'parent',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        $child = TagModel::factory()->create([
            'name' => 'Child',
            'slug' => 'child',
            'sort_order' => 1,
            'parent_id' => $parent->id,
        ]);

        $result = $this->service->getAllInHierarchicalOrder();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TagHierarchyDTO::class, $result[0]);
        $this->assertEquals('Parent', $result[0]->name);
        $this->assertEquals(0, $result[0]->depth);
        $this->assertEquals('Child', $result[1]->name);
        $this->assertEquals(1, $result[1]->depth);
    }

    public function test_get_all_in_hierarchical_order_filters_by_type(): void
    {
        TagModel::factory()->create([
            'name' => 'Events Only',
            'slug' => 'events-only',
            'applies_to' => ['events'],
        ]);

        TagModel::factory()->create([
            'name' => 'Articles Only',
            'slug' => 'articles-only',
            'applies_to' => ['articles'],
        ]);

        $result = $this->service->getAllInHierarchicalOrder('events');

        $this->assertCount(1, $result);
        $this->assertEquals('Events Only', $result[0]->name);
    }

    public function test_get_options_for_select_returns_formatted_array(): void
    {
        $parent = TagModel::factory()->create([
            'name' => 'Parent',
            'slug' => 'parent',
            'sort_order' => 1,
            'parent_id' => null,
        ]);

        TagModel::factory()->create([
            'name' => 'Child',
            'slug' => 'child',
            'sort_order' => 1,
            'parent_id' => $parent->id,
        ]);

        $result = $this->service->getOptionsForSelect();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey($parent->id, $result);
        $this->assertEquals('Parent', $result[$parent->id]);
    }

    public function test_get_usage_count_returns_zero_for_unused_tag(): void
    {
        $tag = TagModel::factory()->create();

        $result = $this->service->getUsageCount($tag->id);

        $this->assertEquals(0, $result);
    }

    public function test_has_children_returns_false_for_tag_without_children(): void
    {
        $tag = TagModel::factory()->create();

        $result = $this->service->hasChildren($tag->id);

        $this->assertFalse($result);
    }

    public function test_has_children_returns_true_for_tag_with_children(): void
    {
        $parent = TagModel::factory()->create();
        TagModel::factory()->create(['parent_id' => $parent->id]);

        $result = $this->service->hasChildren($parent->id);

        $this->assertTrue($result);
    }

    public function test_is_in_use_returns_false_for_unused_tag(): void
    {
        $tag = TagModel::factory()->create();

        $result = $this->service->isInUse($tag->id);

        $this->assertFalse($result);
    }

    public function test_can_delete_returns_true_for_unused_tag_without_children(): void
    {
        $tag = TagModel::factory()->create();

        $result = $this->service->canDelete($tag->id);

        $this->assertTrue($result);
    }

    public function test_can_delete_returns_false_for_tag_with_children(): void
    {
        $parent = TagModel::factory()->create();
        TagModel::factory()->create(['parent_id' => $parent->id]);

        $result = $this->service->canDelete($parent->id);

        $this->assertFalse($result);
    }

    public function test_hierarchical_depth_is_computed_correctly(): void
    {
        $root = TagModel::factory()->create([
            'name' => 'Root',
            'slug' => 'root',
            'parent_id' => null,
        ]);

        $level1 = TagModel::factory()->create([
            'name' => 'Level 1',
            'slug' => 'level-1',
            'parent_id' => $root->id,
        ]);

        $level2 = TagModel::factory()->create([
            'name' => 'Level 2',
            'slug' => 'level-2',
            'parent_id' => $level1->id,
        ]);

        $result = $this->service->getAllInHierarchicalOrder();

        $this->assertCount(3, $result);
        $this->assertEquals(0, $result[0]->depth);
        $this->assertEquals(1, $result[1]->depth);
        $this->assertEquals(2, $result[2]->depth);
    }

    public function test_full_path_is_computed_correctly(): void
    {
        $root = TagModel::factory()->create([
            'name' => 'Games',
            'slug' => 'games',
            'parent_id' => null,
        ]);

        $child = TagModel::factory()->create([
            'name' => 'Board Games',
            'slug' => 'board-games',
            'parent_id' => $root->id,
        ]);

        $result = $this->service->getAllInHierarchicalOrder();

        $this->assertEquals('Games', $result[0]->fullPath);
        $this->assertEquals('Games > Board Games', $result[1]->fullPath);
    }

    public function test_indented_names_are_formatted_correctly(): void
    {
        $root = TagModel::factory()->create([
            'name' => 'Root',
            'slug' => 'root',
            'parent_id' => null,
        ]);

        TagModel::factory()->create([
            'name' => 'Child',
            'slug' => 'child',
            'parent_id' => $root->id,
        ]);

        $result = $this->service->getAllInHierarchicalOrder();

        // Root level - no indentation
        $this->assertEquals('Root', $result[0]->indentedNameForTable);
        $this->assertEquals('Root', $result[0]->indentedNameForSelect);

        // Child level - indented
        $this->assertEquals('- Child', $result[1]->indentedNameForTable);
        $this->assertEquals('  Child', $result[1]->indentedNameForSelect);
    }

    public function test_get_usage_count_uses_efficient_single_query(): void
    {
        // Create a tag with multiple related items
        $tag = TagModel::factory()->create([
            'applies_to' => ['events', 'articles', 'galleries'],
        ]);

        // Create related events, articles, and galleries
        $events = \App\Infrastructure\Persistence\Eloquent\Models\EventModel::factory()->count(3)->create();
        $articles = \App\Infrastructure\Persistence\Eloquent\Models\ArticleModel::factory()->count(2)->create();
        $galleries = \App\Infrastructure\Persistence\Eloquent\Models\GalleryModel::factory()->count(4)->create();

        // Attach tag to items
        foreach ($events as $event) {
            $event->tags()->attach($tag->id);
        }
        foreach ($articles as $article) {
            $article->tags()->attach($tag->id);
        }
        foreach ($galleries as $gallery) {
            $gallery->tags()->attach($tag->id);
        }

        // Reset query log
        \DB::enableQueryLog();
        \DB::flushQueryLog();

        $result = $this->service->getUsageCount($tag->id);

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // Should return total count (3 + 2 + 4 = 9)
        $this->assertEquals(9, $result);

        // Should use only 1 query with withCount instead of 4 queries (1 find + 3 counts)
        // The optimized version should do: 1 query with counts as subqueries
        $this->assertLessThanOrEqual(1, count($queries), sprintf(
            'Expected at most 1 query (using withCount), got %d: %s',
            count($queries),
            collect($queries)->pluck('query')->implode('; ')
        ));
    }

    public function test_get_usage_count_returns_zero_for_nonexistent_tag(): void
    {
        $result = $this->service->getUsageCount('nonexistent-uuid');

        $this->assertEquals(0, $result);
    }

    public function test_get_all_in_hierarchical_order_uses_efficient_queries(): void
    {
        // Create a tree with 3 levels and multiple branches
        $root1 = TagModel::factory()->create([
            'name' => 'Root 1',
            'slug' => 'root-1',
            'parent_id' => null,
        ]);

        $root2 = TagModel::factory()->create([
            'name' => 'Root 2',
            'slug' => 'root-2',
            'parent_id' => null,
        ]);

        // Create children for root1
        $child1_1 = TagModel::factory()->create([
            'name' => 'Child 1.1',
            'slug' => 'child-1-1',
            'parent_id' => $root1->id,
        ]);

        $child1_2 = TagModel::factory()->create([
            'name' => 'Child 1.2',
            'slug' => 'child-1-2',
            'parent_id' => $root1->id,
        ]);

        // Create grandchildren
        TagModel::factory()->create([
            'name' => 'Grandchild 1.1.1',
            'slug' => 'grandchild-1-1-1',
            'parent_id' => $child1_1->id,
        ]);

        TagModel::factory()->create([
            'name' => 'Grandchild 1.1.2',
            'slug' => 'grandchild-1-1-2',
            'parent_id' => $child1_1->id,
        ]);

        // Reset query log
        \DB::enableQueryLog();
        \DB::flushQueryLog();

        $result = $this->service->getAllInHierarchicalOrder();

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // Should return all 6 tags
        $this->assertCount(6, $result);

        // With eager loading, we get 1 query per depth level (roots + each child level)
        // For a 3-level tree: 1 (roots) + 1 (children) + 1 (grandchildren) + 1 (empty check) = 4 queries max
        // Without eager loading (N+1), this would be 1 + 2 + 2 + 2 = 7 queries minimum
        // (1 root query + 2 children queries + 2 grandchildren queries + 2 empty checks)
        $this->assertLessThanOrEqual(4, count($queries), sprintf(
            'Expected at most 4 queries (one per depth level), got %d: %s',
            count($queries),
            collect($queries)->pluck('query')->implode('; ')
        ));
    }
}
