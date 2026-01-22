<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services;

use App\Application\DTOs\Response\TagHierarchyDTO;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Services\TagQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TagQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private TagQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagQueryService();
    }

    public function test_get_all_in_hierarchical_order_returns_empty_array_when_no_tags(): void
    {
        $result = $this->service->getAllInHierarchicalOrder();

        $this->assertIsArray($result);
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

        $this->assertIsArray($result);
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
}
