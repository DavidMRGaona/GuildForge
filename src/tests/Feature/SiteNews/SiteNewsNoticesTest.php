<?php

declare(strict_types=1);

namespace Tests\Feature\SiteNews;

use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationItemDTO;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\SiteNews\SiteNewsKind;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\SiteNews\Jobs\AnnounceSiteNewsJob;
use App\Notifications\SiteNewsNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * «Novedades del sitio» (core#613): the first publication of an event, an article or a gallery
 * becomes a notice in the bell of every active account, grouped while unread.
 */
final class SiteNewsNoticesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
    }

    public function test_the_category_is_optional_site_only_and_shown_to_everybody(): void
    {
        $category = $this->app->make(MailCategoryRegistryInterface::class)->find(CoreMailCategories::SITE_NEWS);

        $this->assertNotNull($category);
        $this->assertFalse($category->isMandatory());
        $this->assertTrue($category->site);
        $this->assertFalse($category->mail);
        $this->assertNull($category->audience);
        $this->assertSame(CoreMailCategories::SITE_NEWS, $category->key);
    }

    public function test_publishing_an_event_tells_every_other_active_account(): void
    {
        $publisher = UserModel::factory()->create();
        $reader = UserModel::factory()->create();
        $deactivated = UserModel::factory()->create();
        $deactivated->delete();
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $event = EventModel::factory()->upcoming()->create(['title' => 'Torneo de otoño', 'slug' => 'torneo-de-otono', 'location' => 'Sala grande']);

        $this->actingAs($publisher);
        $event->update(['is_published' => true]);

        $items = $this->latest($reader);
        $this->assertCount(1, $items);
        $this->assertSame('Nuevo evento: Torneo de otoño', $items[0]->title);
        $this->assertSame('/eventos/torneo-de-otono', $items[0]->url);
        $this->assertSame('calendar-days', $items[0]->icon);
        $this->assertStringContainsString('Sala grande', (string) $items[0]->body);
        $this->assertSame(0, $this->unread($publisher));
        $this->assertSame(0, $this->unread($deactivated));
        $this->assertSame(0, $this->unread($anonymized));
    }

    public function test_an_article_created_published_is_announced_and_a_draft_is_not(): void
    {
        $reader = UserModel::factory()->create();

        ArticleModel::factory()->draft()->create(['title' => 'Borrador']);
        $this->assertSame(0, $this->unread($reader));

        ArticleModel::factory()->published()->create(['title' => 'Crónica de la jornada', 'slug' => 'cronica', 'excerpt' => 'Lo que pasó el sábado.']);

        $items = $this->latest($reader);
        $this->assertCount(1, $items);
        $this->assertSame('Nuevo artículo: Crónica de la jornada', $items[0]->title);
        $this->assertSame('/articulos/cronica', $items[0]->url);
        $this->assertSame('Lo que pasó el sábado.', $items[0]->body);
        $this->assertSame('newspaper', $items[0]->icon);
    }

    public function test_a_gallery_is_announced_once_however_often_it_is_saved_or_republished(): void
    {
        $reader = UserModel::factory()->create();
        $gallery = GalleryModel::factory()->published()->create(['title' => 'Fotos del torneo', 'slug' => 'fotos-del-torneo']);

        $gallery->update(['title' => 'Fotos del torneo de otoño']);
        $gallery->update(['is_published' => false]);
        $gallery->update(['is_published' => true]);

        $items = $this->latest($reader);
        $this->assertCount(1, $items);
        $this->assertSame('Nueva galería: Fotos del torneo', $items[0]->title);
        $this->assertSame('images', $items[0]->icon);
    }

    public function test_an_event_that_has_already_ended_is_not_news(): void
    {
        $reader = UserModel::factory()->create();

        EventModel::factory()->past()->published()->create();

        $this->assertSame(0, $this->unread($reader));
    }

    public function test_publications_in_a_row_become_one_grouped_row(): void
    {
        $reader = UserModel::factory()->create();

        EventModel::factory()->upcoming()->published()->create();
        ArticleModel::factory()->published()->create();
        GalleryModel::factory()->published()->create(['title' => 'Última galería', 'slug' => 'ultima-galeria']);

        $items = $this->latest($reader);
        $this->assertCount(1, $items);
        $this->assertSame(3, $items[0]->count);
        $this->assertSame('3 novedades en el sitio', $items[0]->title);
        $this->assertSame('/galeria/ultima-galeria', $items[0]->url);
    }

    public function test_an_account_that_turned_the_news_off_on_the_site_gets_nothing(): void
    {
        $reader = UserModel::factory()->create();
        $this->app->make(SiteNotificationPreferenceServiceInterface::class)->update($reader, [CoreMailCategories::SITE_NEWS => false]);

        ArticleModel::factory()->published()->create();

        $this->assertSame(0, $this->unread($reader));
    }

    public function test_whoever_proposed_the_content_is_not_told_about_it_again(): void
    {
        $proposer = UserModel::factory()->create();
        $reader = UserModel::factory()->create();
        $article = ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $proposer, submitted: true)->create();

        $article->forceFill(['proposal_status' => ProposalStatus::Approved, 'is_published' => true])->save();

        $this->assertSame(0, $this->unread($proposer));
        $this->assertSame(1, $this->unread($reader));
    }

    public function test_content_taken_down_before_the_notice_is_delivered_is_not_shown(): void
    {
        $reader = UserModel::factory()->create();
        $article = ArticleModel::factory()->draft()->create();

        $reader->notify(new SiteNewsNotification(SiteNewsKind::Article, (string) $article->id));

        $this->assertSame(0, $this->unread($reader));
    }

    public function test_the_notices_wait_for_the_queue_until_the_publication_commits(): void
    {
        Queue::fake();
        UserModel::factory()->create();

        try {
            DB::transaction(static function (): void {
                ArticleModel::factory()->published()->create();

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
            // The publication never happened
        }

        Queue::assertNotPushed(AnnounceSiteNewsJob::class);
        $this->assertSame(0, DB::table('site_news_announcements')->count());
    }

    public function test_a_rolled_back_publication_can_still_be_announced_later(): void
    {
        $reader = UserModel::factory()->create();
        $gallery = GalleryModel::factory()->draft()->create();

        try {
            DB::transaction(static function () use ($gallery): void {
                $gallery->update(['is_published' => true]);

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
            // The publication never happened
        }

        $gallery->refresh()->update(['is_published' => true]);

        $this->assertSame(1, $this->unread($reader));
    }

    public function test_deleting_the_content_forgets_its_notices(): void
    {
        $reader = UserModel::factory()->create();
        $article = ArticleModel::factory()->published()->create();
        $this->assertSame(1, $this->unread($reader));

        $article->delete();

        $this->assertSame(0, $this->unread($reader));
        $this->assertSame(0, DB::table('site_news_announcements')->count());
    }

    public function test_the_news_goes_straight_to_the_bell_and_never_through_faked_notifications(): void
    {
        Notification::fake();
        $reader = UserModel::factory()->create();

        GalleryModel::factory()->published()->create();

        Notification::assertNothingSent();
        $this->assertSame(1, $this->unread($reader));
    }

    public function test_accounts_are_reached_in_batches(): void
    {
        UserModel::factory()->count(105)->create();

        GalleryModel::factory()->published()->create();

        $this->assertSame(105, DB::table('site_notifications')->where('category', CoreMailCategories::SITE_NEWS)->count());
    }

    private function unread(UserModel $user): int
    {
        return $this->app->make(SiteNotificationServiceInterface::class)->unreadCount($user);
    }

    /**
     * @return list<SiteNotificationItemDTO>
     */
    private function latest(UserModel $user): array
    {
        return $this->app->make(SiteNotificationServiceInterface::class)->latest($user, 10);
    }
}
