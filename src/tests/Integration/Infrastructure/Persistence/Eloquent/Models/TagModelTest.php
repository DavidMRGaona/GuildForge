<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TagModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_tag_in_database(): void
    {
        $tag = TagModel::factory()->create([
            'name' => 'Warhammer 40k',
            'slug' => 'warhammer-40k',
            'applies_to' => ['events', 'articles'],
            'color' => '#FF5733',
            'description' => 'Games Workshop miniature wargame',
            'sort_order' => 5,
        ]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Warhammer 40k',
            'slug' => 'warhammer-40k',
            'color' => '#FF5733',
            'sort_order' => 5,
        ]);
    }

    public function test_it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'id',
            'name',
            'slug',
            'parent_id',
            'applies_to',
            'color',
            'description',
            'sort_order',
        ];

        $model = new TagModel();

        $this->assertEquals($fillable, $model->getFillable());
    }

    public function test_it_casts_applies_to_as_array(): void
    {
        $tag = TagModel::factory()->create([
            'applies_to' => ['events', 'articles', 'galleries'],
        ]);

        $this->assertIsArray($tag->applies_to);
        $this->assertEquals(['events', 'articles', 'galleries'], $tag->applies_to);
    }

    public function test_it_casts_sort_order_as_integer(): void
    {
        $tag = TagModel::factory()->create([
            'sort_order' => 42,
        ]);

        $this->assertIsInt($tag->sort_order);
        $this->assertEquals(42, $tag->sort_order);
    }

    public function test_parent_relationship_returns_parent_tag(): void
    {
        $parent = TagModel::factory()->create([
            'name' => 'Miniature Games',
        ]);

        $child = TagModel::factory()->create([
            'name' => 'Warhammer 40k',
            'parent_id' => $parent->id,
        ]);

        $this->assertNotNull($child->parent);
        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertEquals('Miniature Games', $child->parent->name);
    }

    public function test_parent_relationship_returns_null_for_root_tag(): void
    {
        $tag = TagModel::factory()->create([
            'parent_id' => null,
        ]);

        $this->assertNull($tag->parent);
    }

    public function test_children_relationship_returns_children_tags(): void
    {
        $parent = TagModel::factory()->create(['name' => 'Miniature Games']);

        $child1 = TagModel::factory()->create([
            'name' => 'Warhammer 40k',
            'parent_id' => $parent->id,
            'sort_order' => 2,
        ]);

        $child2 = TagModel::factory()->create([
            'name' => 'Age of Sigmar',
            'parent_id' => $parent->id,
            'sort_order' => 1,
        ]);

        $children = $parent->children;

        $this->assertCount(2, $children);
        $this->assertEquals('Age of Sigmar', $children[0]->name);
        $this->assertEquals('Warhammer 40k', $children[1]->name);
    }

    public function test_children_relationship_orders_by_sort_order_then_name(): void
    {
        $parent = TagModel::factory()->create(['name' => 'RPG']);

        TagModel::factory()->create([
            'name' => 'Dungeons & Dragons',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        TagModel::factory()->create([
            'name' => 'Pathfinder',
            'parent_id' => $parent->id,
            'sort_order' => 0,
        ]);

        TagModel::factory()->create([
            'name' => 'Call of Cthulhu',
            'parent_id' => $parent->id,
            'sort_order' => 10,
        ]);

        $children = $parent->fresh()->children;

        $this->assertEquals('Dungeons & Dragons', $children[0]->name);
        $this->assertEquals('Pathfinder', $children[1]->name);
        $this->assertEquals('Call of Cthulhu', $children[2]->name);
    }

    public function test_events_relationship_returns_associated_events(): void
    {
        $tag = TagModel::factory()->forEvents()->create();
        $event1 = EventModel::factory()->create(['title' => 'Event 1']);
        $event2 = EventModel::factory()->create(['title' => 'Event 2']);

        $tag->events()->attach([$event1->id, $event2->id]);

        $this->assertCount(2, $tag->events);
        $this->assertTrue($tag->events->contains($event1));
        $this->assertTrue($tag->events->contains($event2));
    }

    public function test_articles_relationship_returns_associated_articles(): void
    {
        $tag = TagModel::factory()->forArticles()->create();
        $article1 = ArticleModel::factory()->create(['title' => 'Article 1']);
        $article2 = ArticleModel::factory()->create(['title' => 'Article 2']);

        $tag->articles()->attach([$article1->id, $article2->id]);

        $this->assertCount(2, $tag->articles);
        $this->assertTrue($tag->articles->contains($article1));
        $this->assertTrue($tag->articles->contains($article2));
    }

    public function test_galleries_relationship_returns_associated_galleries(): void
    {
        $tag = TagModel::factory()->forGalleries()->create();
        $gallery1 = GalleryModel::factory()->create(['title' => 'Gallery 1']);
        $gallery2 = GalleryModel::factory()->create(['title' => 'Gallery 2']);

        $tag->galleries()->attach([$gallery1->id, $gallery2->id]);

        $this->assertCount(2, $tag->galleries);
        $this->assertTrue($tag->galleries->contains($gallery1));
        $this->assertTrue($tag->galleries->contains($gallery2));
    }

    public function test_scope_roots_returns_only_tags_without_parent(): void
    {
        $root1 = TagModel::factory()->create(['name' => 'Root 1', 'parent_id' => null]);
        $root2 = TagModel::factory()->create(['name' => 'Root 2', 'parent_id' => null]);
        $child = TagModel::factory()->create(['name' => 'Child', 'parent_id' => $root1->id]);

        $roots = TagModel::roots()->get();

        $this->assertCount(2, $roots);
        $this->assertTrue($roots->contains($root1));
        $this->assertTrue($roots->contains($root2));
        $this->assertFalse($roots->contains($child));
    }

    public function test_scope_for_type_returns_tags_for_events(): void
    {
        $eventTag = TagModel::factory()->forEvents()->create();
        $articleTag = TagModel::factory()->forArticles()->create();
        $bothTag = TagModel::factory()->forEventsAndArticles()->create();

        $eventTags = TagModel::forType('events')->get();

        $this->assertTrue($eventTags->contains($eventTag));
        $this->assertFalse($eventTags->contains($articleTag));
        $this->assertTrue($eventTags->contains($bothTag));
    }

    public function test_scope_for_type_returns_tags_for_articles(): void
    {
        $eventTag = TagModel::factory()->forEvents()->create();
        $articleTag = TagModel::factory()->forArticles()->create();
        $bothTag = TagModel::factory()->forEventsAndArticles()->create();

        $articleTags = TagModel::forType('articles')->get();

        $this->assertFalse($articleTags->contains($eventTag));
        $this->assertTrue($articleTags->contains($articleTag));
        $this->assertTrue($articleTags->contains($bothTag));
    }

    public function test_scope_for_type_returns_tags_for_galleries(): void
    {
        $eventTag = TagModel::factory()->forEvents()->create();
        $galleryTag = TagModel::factory()->forGalleries()->create();

        $galleryTags = TagModel::forType('galleries')->get();

        $this->assertFalse($galleryTags->contains($eventTag));
        $this->assertTrue($galleryTags->contains($galleryTag));
    }

    public function test_scope_ordered_sorts_by_sort_order_then_name(): void
    {
        TagModel::factory()->create(['name' => 'Zulu', 'sort_order' => 0]);
        TagModel::factory()->create(['name' => 'Alpha', 'sort_order' => 0]);
        TagModel::factory()->create(['name' => 'Beta', 'sort_order' => 5]);
        TagModel::factory()->create(['name' => 'Charlie', 'sort_order' => 5]);

        $tags = TagModel::ordered()->get();

        $this->assertEquals('Alpha', $tags[0]->name);
        $this->assertEquals('Zulu', $tags[1]->name);
        $this->assertEquals('Beta', $tags[2]->name);
        $this->assertEquals('Charlie', $tags[3]->name);
    }

    public function test_applies_to_method_returns_true_when_type_matches(): void
    {
        $tag = TagModel::factory()->create([
            'applies_to' => ['events', 'articles'],
        ]);

        $this->assertTrue($tag->appliesTo('events'));
        $this->assertTrue($tag->appliesTo('articles'));
        $this->assertFalse($tag->appliesTo('galleries'));
    }

    // Note: Tests for getFullPath, getUsageCount, hasChildren, isInUse
    // were moved to TagQueryServiceTest.php as part of architecture cleanup

    public function test_factory_creates_tag_with_default_applies_to(): void
    {
        $tag = TagModel::factory()->create();

        $this->assertEquals(['events', 'articles', 'galleries'], $tag->applies_to);
    }

    public function test_factory_for_events_state_creates_event_only_tag(): void
    {
        $tag = TagModel::factory()->forEvents()->create();

        $this->assertEquals(['events'], $tag->applies_to);
    }

    public function test_factory_for_articles_state_creates_article_only_tag(): void
    {
        $tag = TagModel::factory()->forArticles()->create();

        $this->assertEquals(['articles'], $tag->applies_to);
    }

    public function test_factory_for_galleries_state_creates_gallery_only_tag(): void
    {
        $tag = TagModel::factory()->forGalleries()->create();

        $this->assertEquals(['galleries'], $tag->applies_to);
    }

    public function test_factory_for_events_and_articles_state_creates_combined_tag(): void
    {
        $tag = TagModel::factory()->forEventsAndArticles()->create();

        $this->assertEquals(['events', 'articles'], $tag->applies_to);
    }

    public function test_factory_with_parent_state_creates_child_tag(): void
    {
        $parent = TagModel::factory()->create(['name' => 'Parent Tag']);
        $child = TagModel::factory()->withParent($parent)->create();

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals('Parent Tag', $child->parent->name);
    }

    public function test_factory_with_color_state_creates_tag_with_specific_color(): void
    {
        $tag = TagModel::factory()->withColor('#FF0000')->create();

        $this->assertEquals('#FF0000', $tag->color);
    }

    public function test_factory_with_sort_order_state_creates_tag_with_specific_order(): void
    {
        $tag = TagModel::factory()->withSortOrder(99)->create();

        $this->assertEquals(99, $tag->sort_order);
    }
}
