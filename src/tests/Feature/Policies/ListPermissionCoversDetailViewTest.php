<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * Hero slides and tags have no detail permission: the list permission covers viewing one.
 */
final class ListPermissionCoversDetailViewTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_listing_hero_slides_allows_viewing_one(): void
    {
        $slide = HeroSlideModel::factory()->create();

        $this->assertTrue($this->editorWithPermissions(['hero_slides.view_any'])->can('view', $slide));
        $this->assertFalse($this->editorWithPermissions(['events.view_any'])->can('view', $slide));
    }

    public function test_listing_tags_allows_viewing_one(): void
    {
        $tag = TagModel::factory()->create();

        $this->assertTrue($this->editorWithPermissions(['tags.view_any'])->can('view', $tag));
        $this->assertFalse($this->editorWithPermissions(['events.view_any'])->can('view', $tag));
    }
}
