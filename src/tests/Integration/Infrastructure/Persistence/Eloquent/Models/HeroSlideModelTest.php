<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class HeroSlideModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_hero_slide_in_database(): void
    {
        $slide = HeroSlideModel::factory()->create([
            'title' => 'Welcome to GuildForge',
            'subtitle' => 'Join our gaming community',
            'button_text' => 'Learn More',
            'button_url' => '/about',
            'image_public_id' => 'hero_slides/sample_image',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('hero_slides', [
            'id' => $slide->id,
            'title' => 'Welcome to GuildForge',
            'subtitle' => 'Join our gaming community',
            'button_text' => 'Learn More',
            'button_url' => '/about',
            'image_public_id' => 'hero_slides/sample_image',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'id',
            'title',
            'subtitle',
            'button_text',
            'button_url',
            'image_public_id',
            'is_active',
            'sort_order',
        ];

        $model = new HeroSlideModel();

        $this->assertEquals($fillable, $model->getFillable());
    }

    public function test_it_casts_boolean_correctly(): void
    {
        $slide = HeroSlideModel::factory()->create([
            'is_active' => true,
        ]);

        $this->assertTrue($slide->is_active);
        $this->assertIsBool($slide->is_active);
    }

    public function test_it_casts_sort_order_as_integer(): void
    {
        $slide = HeroSlideModel::factory()->create([
            'sort_order' => 5,
        ]);

        $this->assertIsInt($slide->sort_order);
        $this->assertEquals(5, $slide->sort_order);
    }

    public function test_scope_active_ordered_returns_active_slides_in_order(): void
    {
        // Create inactive slide (should not be returned)
        HeroSlideModel::factory()->inactive()->withOrder(0)->create([
            'title' => 'Inactive Slide',
        ]);

        // Create active slide with order 2
        $slideTwo = HeroSlideModel::factory()->active()->withOrder(2)->create([
            'title' => 'Second Slide',
        ]);

        // Create active slide with order 1
        $slideOne = HeroSlideModel::factory()->active()->withOrder(1)->create([
            'title' => 'First Slide',
        ]);

        $activeSlides = HeroSlideModel::activeOrdered()->get();

        // Should return only 2 active slides
        $this->assertCount(2, $activeSlides);

        // Should be ordered by sort_order ascending
        $this->assertEquals('First Slide', $activeSlides[0]->title);
        $this->assertEquals(1, $activeSlides[0]->sort_order);
        $this->assertEquals('Second Slide', $activeSlides[1]->title);
        $this->assertEquals(2, $activeSlides[1]->sort_order);
    }

    public function test_factory_creates_inactive_slide_by_default(): void
    {
        $slide = HeroSlideModel::factory()->create();

        $this->assertFalse($slide->is_active);
    }

    public function test_factory_active_state_creates_active_slide(): void
    {
        $slide = HeroSlideModel::factory()->active()->create();

        $this->assertTrue($slide->is_active);
    }
}
