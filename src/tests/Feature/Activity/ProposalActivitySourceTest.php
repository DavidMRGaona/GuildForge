<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Services\UserServiceInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Activity\Sources\ProposalActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Activity\ActivitySourceContractTests;
use Tests\Support\Activity\FakesActivityEvents;
use Tests\TestCase;

final class ProposalActivitySourceTest extends TestCase
{
    use ActivitySourceContractTests;
    use FakesActivityEvents;
    use LazilyRefreshDatabase;

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(ProposalActivitySource::class);
    }

    protected function activitySourceOwner(): string
    {
        return 'core';
    }

    protected function createCountedActivitySubject(): string
    {
        return 'events:'.EventModel::factory()->proposal(ProposalStatus::Approved)->create()->id;
    }

    protected function unknownActivitySubjectId(): string
    {
        return 'events:'.fake()->uuid();
    }

    public function test_it_is_the_core_proposal_source_and_offers_the_content_type(): void
    {
        $source = $this->activitySource();

        $this->assertSame('core.proposal', $source->type());
        $this->assertSame(ActivityKind::Proposal, $source->kind());
        $this->assertInstanceOf(OffersActivityAttributes::class, $source);
        $this->assertSame(['content_type'], array_map(static fn ($attribute): string => $attribute->key, $source->attributes()));
        $this->assertSame(['event', 'article'], array_map(static fn ($value): string => $value->id, $source->attributeValues('content_type')));
    }

    public function test_an_approved_event_proposal_is_a_proposer_fact_with_its_content_type(): void
    {
        $proposer = UserModel::factory()->create();
        $event = EventModel::factory()->proposal(ProposalStatus::Approved, $proposer)->create();

        $facts = $this->activitySource()->facts('events:'.$event->id);

        $this->assertCount(1, $facts);
        $this->assertSame((string) $proposer->id, $facts[0]->userId);
        $this->assertSame(ActivityRole::Proposer, $facts[0]->role);
        $this->assertSame($event->reviewed_at?->getTimestamp(), $facts[0]->occurredAt->getTimestamp());
        $this->assertSame('event', $facts[0]->attributes['content_type']->id);
    }

    public function test_an_approved_article_proposal_counts_with_the_article_content_type(): void
    {
        $proposer = UserModel::factory()->create();
        $article = ArticleModel::factory()->proposal(ProposalStatus::Approved, $proposer)->create();

        $facts = $this->activitySource()->facts('articles:'.$article->id);

        $this->assertCount(1, $facts);
        $this->assertSame('article', $facts[0]->attributes['content_type']->id);
    }

    /**
     * @return array<string, array{ProposalStatus}>
     */
    public static function uncountedStatuses(): array
    {
        return [
            'draft' => [ProposalStatus::Draft],
            'in review' => [ProposalStatus::PendingReview],
            'rejected' => [ProposalStatus::Rejected],
        ];
    }

    #[DataProvider('uncountedStatuses')]
    public function test_a_proposal_that_is_not_approved_does_not_count(ProposalStatus $status): void
    {
        $event = EventModel::factory()->proposal($status)->create();
        $article = ArticleModel::factory()->proposal($status)->create();

        $this->assertSame([], $this->activitySource()->facts('events:'.$event->id));
        $this->assertSame([], $this->activitySource()->facts('articles:'.$article->id));
        $this->assertSame([], $this->listedSubjects());
    }

    public function test_content_created_in_the_panel_is_not_a_proposal(): void
    {
        $event = EventModel::factory()->published()->create();

        $this->assertSame([], $this->activitySource()->facts('events:'.$event->id));
    }

    public function test_an_approved_proposal_that_was_unpublished_stops_counting(): void
    {
        $event = EventModel::factory()->proposal(ProposalStatus::Approved)->create();
        $event->update(['is_published' => false]);

        $this->assertSame([], $this->activitySource()->facts('events:'.$event->id));
        $this->assertSame([], $this->listedSubjects());

        $event->update(['is_published' => true]);
        $this->assertCount(1, $this->activitySource()->facts('events:'.$event->id));
    }

    public function test_the_proposals_of_an_anonymized_or_deactivated_account_do_not_count(): void
    {
        $anonymized = UserModel::factory()->create();
        $deactivated = UserModel::factory()->create();
        $first = EventModel::factory()->proposal(ProposalStatus::Approved, $anonymized)->create();
        $second = ArticleModel::factory()->proposal(ProposalStatus::Approved, $deactivated)->create();
        $this->app->make(UserServiceInterface::class)->anonymize((string) $anonymized->id);
        $deactivated->delete();

        $this->assertSame([], $this->activitySource()->facts('events:'.$first->id));
        $this->assertSame([], $this->activitySource()->facts('articles:'.$second->id));
        $this->assertSame([], $this->listedSubjects());
    }

    public function test_a_malformed_subject_id_has_no_facts(): void
    {
        $event = EventModel::factory()->proposal(ProposalStatus::Approved)->create();

        $this->assertSame([], $this->activitySource()->facts((string) $event->id), 'The type prefix is part of the id.');
        $this->assertSame([], $this->activitySource()->facts('events:nope'));
        $this->assertSame([], $this->activitySource()->facts('pages:'.$event->id));
    }

    public function test_the_answer_does_not_depend_on_who_is_signed_in(): void
    {
        $event = EventModel::factory()->proposal(ProposalStatus::Approved)->create();
        $guest = $this->activitySource()->facts('events:'.$event->id);

        $this->actingAs(UserModel::factory()->create());

        $this->assertEquals($guest, $this->activitySource()->facts('events:'.$event->id));
    }

    public function test_history_lists_events_then_articles_across_pages_with_an_opaque_cursor(): void
    {
        $events = EventModel::factory()->proposal(ProposalStatus::Approved)->count(3)->create();
        $articles = ArticleModel::factory()->proposal(ProposalStatus::Approved)->count(3)->create();
        $source = $this->activitySource();

        $first = $source->history(null, 4);
        $second = $source->history($first->nextCursor, 4);

        $this->assertCount(4, $first->subjects);
        $this->assertCount(2, $second->subjects);
        $this->assertNull($second->nextCursor);
        $this->assertEquals($first, $source->history(null, 4), 'Stable order.');

        $listed = array_map(static fn ($subject): string => $subject->subjectId, [...$first->subjects, ...$second->subjects]);
        $expected = [
            ...array_map(static fn (EventModel $event): string => 'events:'.$event->id, $events->all()),
            ...array_map(static fn (ArticleModel $article): string => 'articles:'.$article->id, $articles->all()),
        ];
        sort($expected);
        $sortedListed = $listed;
        sort($sortedListed);
        $this->assertSame($expected, $sortedListed);
    }

    public function test_an_unreadable_cursor_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->activitySource()->history('not a cursor', 10);
    }

    public function test_describe_gives_the_title_and_the_public_link_of_the_published_content(): void
    {
        $event = EventModel::factory()->proposal(ProposalStatus::Approved)->create(['title' => 'Jornada de rol']);
        $article = ArticleModel::factory()->proposal(ProposalStatus::Approved)->create(['title' => 'Crónica del torneo']);

        $described = $this->activitySource()->describe(['events:'.$event->id, 'articles:'.$article->id], null);

        $this->assertSame('Jornada de rol', $described['events:'.$event->id]->title);
        $this->assertSame('/eventos/'.$event->slug, $described['events:'.$event->id]->url);
        $this->assertSame('/articulos/'.$article->slug, $described['articles:'.$article->id]->url);
    }

    public function test_describe_leaves_out_what_is_not_published_or_not_a_proposal(): void
    {
        $unpublished = EventModel::factory()->proposal(ProposalStatus::Approved)->create();
        $unpublished->update(['is_published' => false]);
        $pending = ArticleModel::factory()->proposal(ProposalStatus::PendingReview)->create();

        $described = $this->activitySource()->describe(['events:'.$unpublished->id, 'articles:'.$pending->id, 'garbage'], UserModel::factory()->create());

        $this->assertSame([], $described);
    }

    public function test_approving_unpublishing_and_deleting_publish_the_subject(): void
    {
        $event = EventModel::factory()->proposal(ProposalStatus::PendingReview)->create();
        $this->fakeActivityEvents();

        $event->forceFill(['proposal_status' => ProposalStatus::Approved, 'is_published' => true, 'reviewed_at' => now()])->save();
        $this->assertSubjectPublished('events:'.$event->id);

        $this->fakeActivityEvents();
        $event->update(['is_published' => false]);
        $this->assertSubjectPublished('events:'.$event->id);

        $this->fakeActivityEvents();
        $event->delete();
        $this->assertSubjectPublished('events:'.$event->id);
    }

    public function test_saving_content_that_is_not_a_proposal_publishes_nothing(): void
    {
        $event = EventModel::factory()->published()->create();
        $author = UserModel::factory()->create();
        $this->fakeActivityEvents();

        $event->update(['title' => 'Otro título']);
        $event->update(['is_published' => false]);
        ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $author)->create();

        Event::assertNotDispatched(ActivityRecorded::class, static fn (ActivityRecorded $recorded): bool => $recorded->sourceType === 'core.proposal');
    }

    public function test_anonymizing_an_account_publishes_its_approved_proposals(): void
    {
        $proposer = UserModel::factory()->create();
        $event = EventModel::factory()->proposal(ProposalStatus::Approved, $proposer)->create();
        $article = ArticleModel::factory()->proposal(ProposalStatus::Approved, $proposer)->create();
        $this->fakeActivityEvents();

        $this->app->make(UserServiceInterface::class)->anonymize((string) $proposer->id);

        $this->assertSubjectPublished('events:'.$event->id);
        $this->assertSubjectPublished('articles:'.$article->id);
    }

    private function assertSubjectPublished(string $subjectId): void
    {
        $this->assertActivityPublished('core.proposal', $subjectId);
    }

    /**
     * @return list<string>
     */
    private function listedSubjects(): array
    {
        $listed = [];
        $cursor = null;

        do {
            $page = $this->activitySource()->history($cursor, 50);
            foreach ($page->subjects as $subject) {
                $listed[] = $subject->subjectId;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return $listed;
    }
}
