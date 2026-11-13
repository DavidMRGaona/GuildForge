<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Publications\Events\PublicationChanged;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Publications\Sources\EventPublicationSource;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\PendingCommand;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

/**
 * No proposal that is not approved reaches any public output (core#570): listings, details, the
 * home page, search, the calendar and its feed, the sitemap and publication listeners. Each one
 * is created as published, as a forged panel toggle or careless code would, to prove the model
 * keeps it unpublished.
 */
final class ProposalsNeverPublicTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));
    }

    /**
     * @return array<string, array{ProposalStatus, bool}>
     */
    public static function unapproved(): array
    {
        return [
            'unsubmitted draft' => [ProposalStatus::Draft, false],
            'returned draft' => [ProposalStatus::Draft, true],
            'pending review' => [ProposalStatus::PendingReview, false],
            'rejected' => [ProposalStatus::Rejected, false],
        ];
    }

    private function eventProposal(ProposalStatus $status, bool $submitted): EventModel
    {
        return EventModel::factory()->proposal($status, UserModel::factory()->create(), $submitted)->create([
            'title' => 'Dragones secretos',
            'start_date' => '2026-11-20 17:00:00',
            'end_date' => '2026-11-20 21:00:00',
            'is_published' => true,
        ]);
    }

    private function articleProposal(ProposalStatus $status, bool $submitted): ArticleModel
    {
        return ArticleModel::factory()->proposal($status, UserModel::factory()->create(), $submitted)->create([
            'title' => 'Dragones escondidos',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    #[DataProvider('unapproved')]
    public function test_event_listings_and_details_leave_unapproved_proposals_out(ProposalStatus $status, bool $submitted): void
    {
        $event = $this->eventProposal($status, $submitted);

        $this->get('/eventos')->assertOk()->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
        $this->get('/eventos/'.$event->slug)->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Dragones secretos');
    }

    #[DataProvider('unapproved')]
    public function test_article_listings_and_details_leave_unapproved_proposals_out(ProposalStatus $status, bool $submitted): void
    {
        $article = $this->articleProposal($status, $submitted);

        $this->get('/articulos')->assertOk()->assertInertia(fn (Assert $page) => $page->has('articles.data', 0));
        $this->get('/articulos/'.$article->slug)->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Dragones escondidos');
    }

    #[DataProvider('unapproved')]
    public function test_search_never_indexes_unapproved_proposals(ProposalStatus $status, bool $submitted): void
    {
        $event = $this->eventProposal($status, $submitted);
        $article = $this->articleProposal($status, $submitted);
        $sync = $this->artisan('search:sync');
        assert($sync instanceof PendingCommand);
        $sync->assertSuccessful()->run();

        $this->assertFalse(SearchDocumentModel::query()->whereIn('source_id', [$event->id, $article->id])->exists());
        $this->get('/buscar?q=Dragones')->assertOk()->assertDontSee('Dragones secretos')->assertDontSee('Dragones escondidos');
    }

    #[DataProvider('unapproved')]
    public function test_the_calendar_and_its_feeds_leave_unapproved_proposals_out(ProposalStatus $status, bool $submitted): void
    {
        $event = $this->eventProposal($status, $submitted);

        $this->getJson('/eventos/calendario?start=2026-11-01&end=2026-11-30')->assertOk()->assertJsonCount(0);
        $this->assertStringNotContainsString('Dragones secretos', (string) $this->get('/calendario.ics')->assertOk()->content());
        $this->get('/calendario/events/'.$event->id.'.ics')->assertNotFound();
    }

    #[DataProvider('unapproved')]
    public function test_the_sitemap_leaves_unapproved_proposals_out(ProposalStatus $status, bool $submitted): void
    {
        $event = $this->eventProposal($status, $submitted);
        $article = $this->articleProposal($status, $submitted);

        $sitemap = (string) $this->get('/sitemap.xml')->assertOk()->content();

        $this->assertStringNotContainsString($event->slug, $sitemap);
        $this->assertStringNotContainsString($article->slug, $sitemap);
    }

    #[DataProvider('unapproved')]
    public function test_publication_listeners_hear_nothing_of_unapproved_proposals(ProposalStatus $status, bool $submitted): void
    {
        Event::fake([PublicationChanged::class]);

        $event = $this->eventProposal($status, $submitted);
        $event->update(['title' => 'Otro título', 'is_published' => true]);

        Event::assertNotDispatched(PublicationChanged::class);
        $this->assertNull(app(EventPublicationSource::class)->find($event->id));
    }

    public function test_an_approved_proposal_is_public_like_any_event(): void
    {
        $event = EventModel::factory()->proposal(ProposalStatus::Approved)->create([
            'title' => 'Dragones aprobados',
            'start_date' => '2026-11-20 17:00:00',
            'end_date' => '2026-11-20 21:00:00',
        ]);

        $this->get('/eventos/'.$event->slug)->assertOk();
        $this->getJson('/eventos/calendario?start=2026-11-01&end=2026-11-30')->assertOk()->assertJsonCount(1);
        $this->assertStringContainsString($event->slug, (string) $this->get('/sitemap.xml')->content());
    }
}
