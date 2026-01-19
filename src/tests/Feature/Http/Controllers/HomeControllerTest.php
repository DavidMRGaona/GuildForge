<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_upcoming_published_events(): void
    {
        EventModel::factory()->published()->upcoming()->count(3)->create();
        EventModel::factory()->published()->past()->count(2)->create();
        EventModel::factory()->draft()->upcoming()->count(2)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('upcomingEvents', 3)
        );
    }

    public function test_index_limits_upcoming_events_to_three(): void
    {
        EventModel::factory()->published()->upcoming()->count(5)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('upcomingEvents', 3)
        );
    }

    public function test_index_orders_upcoming_events_by_date_ascending(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Later Event',
            'start_date' => now()->addDays(10),
        ]);
        EventModel::factory()->published()->create([
            'title' => 'Soonest Event',
            'start_date' => now()->addDays(1),
        ]);
        EventModel::factory()->published()->create([
            'title' => 'Middle Event',
            'start_date' => now()->addDays(5),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('upcomingEvents', 3)
                ->where('upcomingEvents.0.title', 'Soonest Event')
                ->where('upcomingEvents.1.title', 'Middle Event')
                ->where('upcomingEvents.2.title', 'Later Event')
        );
    }

    public function test_index_returns_empty_array_when_no_upcoming_events(): void
    {
        EventModel::factory()->published()->past()->count(2)->create();
        EventModel::factory()->draft()->upcoming()->count(2)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('upcomingEvents', 0)
        );
    }

    public function test_index_displays_latest_published_articles(): void
    {
        ArticleModel::factory()->published()->count(3)->create();
        ArticleModel::factory()->draft()->count(2)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('latestArticles', 3)
        );
    }

    public function test_index_limits_articles_to_three(): void
    {
        ArticleModel::factory()->published()->count(5)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('latestArticles', 3)
        );
    }

    public function test_index_orders_articles_by_published_date_descending(): void
    {
        ArticleModel::factory()->published()->create([
            'title' => 'Oldest Article',
            'published_at' => now()->subDays(10),
        ]);
        ArticleModel::factory()->published()->create([
            'title' => 'Newest Article',
            'published_at' => now(),
        ]);
        ArticleModel::factory()->published()->create([
            'title' => 'Middle Article',
            'published_at' => now()->subDays(5),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('latestArticles', 3)
                ->where('latestArticles.0.title', 'Newest Article')
                ->where('latestArticles.1.title', 'Middle Article')
                ->where('latestArticles.2.title', 'Oldest Article')
        );
    }

    public function test_index_includes_article_author_data(): void
    {
        $author = UserModel::factory()->create([
            'name' => 'John Doe',
            'display_name' => 'JohnD',
        ]);

        ArticleModel::factory()->published()->withAuthor($author)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('latestArticles', 1)
                ->has('latestArticles.0.author')
                ->where('latestArticles.0.author.name', 'John Doe')
                ->where('latestArticles.0.author.displayName', 'JohnD')
        );
    }

    public function test_index_displays_featured_gallery(): void
    {
        $gallery = GalleryModel::factory()->published()->create();
        PhotoModel::factory()->forGallery($gallery)->count(5)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('featuredGallery')
                ->where('featuredGallery.photoCount', 5)
        );
    }

    public function test_index_returns_null_when_no_galleries(): void
    {
        GalleryModel::factory()->draft()->count(2)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->where('featuredGallery', null)
        );
    }

    public function test_index_returns_most_recent_gallery(): void
    {
        GalleryModel::factory()->published()->create([
            'title' => 'Older Gallery',
            'created_at' => now()->subDays(5),
        ]);
        GalleryModel::factory()->published()->create([
            'title' => 'Newest Gallery',
            'created_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('featuredGallery')
                ->where('featuredGallery.title', 'Newest Gallery')
        );
    }

    public function test_index_returns_correct_event_data_format(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Test Event',
            'slug' => 'test-event',
            'description' => 'Test description',
            'start_date' => now()->addDays(5),
            'location' => 'Test Location',
            'image_public_id' => 'test-image.jpg',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('upcomingEvents', 1)
                ->has('upcomingEvents.0.id')
                ->has('upcomingEvents.0.title')
                ->has('upcomingEvents.0.slug')
                ->has('upcomingEvents.0.description')
                ->has('upcomingEvents.0.startDate')
                ->has('upcomingEvents.0.endDate')
                ->has('upcomingEvents.0.location')
                ->has('upcomingEvents.0.imagePublicId')
                ->has('upcomingEvents.0.memberPrice')
                ->has('upcomingEvents.0.nonMemberPrice')
                ->has('upcomingEvents.0.isPublished')
                ->has('upcomingEvents.0.createdAt')
                ->has('upcomingEvents.0.updatedAt')
        );
    }

    public function test_index_displays_active_hero_slides(): void
    {
        HeroSlideModel::factory()->active()->withOrder(1)->create(['title' => 'First Slide']);
        HeroSlideModel::factory()->active()->withOrder(2)->create(['title' => 'Second Slide']);
        HeroSlideModel::factory()->inactive()->create(['title' => 'Inactive Slide']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('heroSlides', 2)
                ->where('heroSlides.0.title', 'First Slide')
                ->where('heroSlides.1.title', 'Second Slide')
        );
    }

    public function test_index_returns_empty_array_when_no_active_slides(): void
    {
        HeroSlideModel::factory()->inactive()->count(3)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('heroSlides', 0)
        );
    }

    public function test_index_returns_correct_hero_slide_data_format(): void
    {
        HeroSlideModel::factory()->active()->create([
            'title' => 'Test Slide',
            'subtitle' => 'Test Subtitle',
            'button_text' => 'Click Me',
            'button_url' => '/test-url',
            'image_public_id' => 'test-image-id',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('heroSlides', 1)
                ->has('heroSlides.0.id')
                ->where('heroSlides.0.title', 'Test Slide')
                ->where('heroSlides.0.subtitle', 'Test Subtitle')
                ->where('heroSlides.0.buttonText', 'Click Me')
                ->where('heroSlides.0.buttonUrl', '/test-url')
                ->where('heroSlides.0.imagePublicId', 'test-image-id')
        );
    }
}
