<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Review columns of events and articles and what keeps unsubmitted drafts and unapproved
 * proposals where they belong (core#570).
 */
final class ProposalStorageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_events_and_articles_have_the_review_columns(): void
    {
        foreach (['events', 'articles'] as $table) {
            $this->assertTrue(Schema::hasColumns($table, [
                'proposal_status',
                'proposed_by',
                'proposal_note',
                'proposal_reason',
                'submitted_at',
                'reviewed_at',
                'reviewed_by',
            ]), $table);
        }
    }

    public function test_deleting_the_proposer_keeps_the_event_without_proposer(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->proposal(ProposalStatus::Approved, $user)->create(['reviewed_by' => $user->id]);

        $user->forceDelete();

        $event = EventModel::query()->findOrFail($event->id);
        $this->assertNull($event->proposed_by);
        $this->assertNull($event->reviewed_by);
    }

    public function test_unsubmitted_drafts_are_hidden_from_every_query(): void
    {
        $user = UserModel::factory()->create();
        $panel = EventModel::factory()->create();
        $draft = EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create();
        $returned = EventModel::factory()->proposal(ProposalStatus::Draft, $user, submitted: true)->create();
        $pending = EventModel::factory()->proposal(ProposalStatus::PendingReview, $user)->create();
        $approved = EventModel::factory()->proposal(ProposalStatus::Approved, $user)->create();
        $rejected = EventModel::factory()->proposal(ProposalStatus::Rejected, $user)->create();

        $visible = EventModel::query()->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$panel->id, $returned->id, $pending->id, $approved->id, $rejected->id],
            $visible,
        );
        $this->assertNull(EventModel::query()->find($draft->id));
        $this->assertSame(5, EventModel::query()->count());
    }

    public function test_the_proposals_flow_sees_unsubmitted_drafts_explicitly(): void
    {
        $draft = EventModel::factory()->proposal()->create();
        $article = ArticleModel::factory()->proposal()->create();

        $this->assertNotNull(EventModel::query()->includingUnsubmittedProposals()->find($draft->id));
        $this->assertNotNull(ArticleModel::query()->includingUnsubmittedProposals()->find($article->id));
        $this->assertSame(1, EventModel::query()->unapprovedProposals()->count());
    }

    public function test_unsubmitted_article_drafts_are_hidden_too(): void
    {
        $user = UserModel::factory()->create();
        $draft = ArticleModel::factory()->proposal(ProposalStatus::Draft, $user)->create();
        $pending = ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $user)->create();

        $this->assertSame([$pending->id], ArticleModel::query()->pluck('id')->all());
        $this->assertNull(ArticleModel::query()->find($draft->id));
        // Not even through the author's relation
        $this->assertSame(0, ArticleModel::query()->where('author_id', $user->id)->whereKey($draft->id)->count());
    }

    public function test_unsubmitted_drafts_are_hidden_from_relations(): void
    {
        $tag = TagModel::factory()->create(['applies_to' => ['events']]);
        $draft = EventModel::factory()->proposal()->create();
        $panel = EventModel::factory()->create();
        $draft->tags()->attach($tag->id);
        $panel->tags()->attach($tag->id);

        $this->assertSame([$panel->id], $tag->events()->pluck('events.id')->all());
    }

    /**
     * The event selectors of the tournaments and game-tables panels, as those modules write them.
     */
    public function test_module_event_selectors_never_offer_unsubmitted_drafts_or_unapproved_proposals(): void
    {
        $user = UserModel::factory()->create();
        $published = EventModel::factory()->published()->upcoming()->create();
        $draft = EventModel::factory()->upcoming()->proposal(ProposalStatus::Draft, $user)->create();
        $pending = EventModel::factory()->upcoming()->proposal(ProposalStatus::PendingReview, $user)->create();

        // tournaments: TournamentResource form (published events)
        $tournamentForm = EventModel::query()
            ->where('is_published', true)
            ->orderBy('start_date', 'desc')
            ->pluck('title', 'id');
        // tournaments: TournamentResource filter (every event)
        $tournamentFilter = EventModel::query()
            ->orderBy('start_date', 'desc')
            ->pluck('title', 'id');
        // game-tables: GameTableResource form (upcoming published events)
        $gameTablesForm = EventModel::query()
            ->where('is_published', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date', 'asc')
            ->pluck('title', 'id');

        $this->assertSame([$published->id], $tournamentForm->keys()->all());
        $this->assertSame([$published->id], $gameTablesForm->keys()->all());
        $this->assertArrayNotHasKey($draft->id, $tournamentFilter->all());
        $this->assertArrayHasKey($pending->id, $tournamentFilter->all());
        // tournaments: RegistrationClosingDateRule reads one event by id
        $this->assertNull(EventModel::query()->find($draft->id, ['id', 'start_date']));
    }

    public function test_an_unapproved_event_proposal_is_never_stored_as_published(): void
    {
        foreach ([ProposalStatus::Draft, ProposalStatus::PendingReview, ProposalStatus::Rejected] as $status) {
            $event = EventModel::factory()->proposal($status)->create(['is_published' => true]);
            $this->assertFalse($event->fresh()?->is_published, $status->value);

            // A forged panel toggle or code saving it published
            $event->is_published = true;
            $event->save();
            $this->assertFalse((bool) EventModel::query()->includingUnsubmittedProposals()->whereKey($event->id)->value('is_published'), $status->value);
        }
    }

    public function test_an_unapproved_article_proposal_is_never_stored_as_published(): void
    {
        foreach ([ProposalStatus::Draft, ProposalStatus::PendingReview, ProposalStatus::Rejected] as $status) {
            $article = ArticleModel::factory()->proposal($status)->create();

            $article->update(['is_published' => true, 'published_at' => now()]);

            $stored = ArticleModel::query()->includingUnsubmittedProposals()->findOrFail($article->id);
            $this->assertFalse($stored->is_published, $status->value);
            $this->assertNull($stored->published_at, $status->value);
        }
    }

    public function test_approved_proposals_and_panel_content_publish_as_always(): void
    {
        $approved = EventModel::factory()->proposal(ProposalStatus::Approved)->create(['is_published' => false]);
        $approved->update(['is_published' => true]);
        $this->assertTrue($approved->fresh()?->is_published);

        $panel = ArticleModel::factory()->draft()->create();
        $panel->update(['is_published' => true, 'published_at' => now()]);
        $stored = $panel->fresh();
        $this->assertInstanceOf(ArticleModel::class, $stored);
        $this->assertTrue($stored->is_published);
        $this->assertNotNull($stored->published_at);
    }

    public function test_the_review_columns_are_not_mass_assignable(): void
    {
        $event = EventModel::factory()->create();

        $event->update(['proposal_status' => ProposalStatus::Approved->value, 'proposed_by' => UserModel::factory()->create()->id]);

        $this->assertNull($event->fresh()?->proposal_status);
        $this->assertNull($event->fresh()?->proposed_by);
    }

    public function test_slugs_stay_unique_against_hidden_drafts(): void
    {
        $draft = EventModel::factory()->proposal()->create([
            'id' => '11111111-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'title' => 'Jornada',
            'slug' => null,
        ]);
        $this->assertSame('jornada-11111111', $draft->slug);

        // Another event whose uuid starts the same way must not take the hidden draft's slug
        $slug = (new EventModel)->generateUniqueSlug('Jornada', '11111111-bbbb-4bbb-8bbb-bbbbbbbbbbbb');

        $this->assertNotSame($draft->slug, $slug);
    }
}
