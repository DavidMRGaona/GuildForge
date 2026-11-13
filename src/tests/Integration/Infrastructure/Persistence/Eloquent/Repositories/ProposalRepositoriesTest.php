<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Article;
use App\Domain\Entities\Event;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\ValueObjects\ProposalReview;
use App\Domain\ValueObjects\ArticleId;
use App\Domain\ValueObjects\EventId;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentArticleRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The event and article repositories read and write the review data of proposals (core#570) and
 * see drafts never submitted, which every other query hides.
 */
final class ProposalRepositoriesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_event_repository_reads_the_review_of_an_unsubmitted_draft(): void
    {
        $user = UserModel::factory()->create();
        $model = EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create(['proposal_note' => 'Nota']);

        $event = (new EloquentEventRepository)->findById(new EventId($model->id));

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(ProposalReview::class, $event->proposal());
        $this->assertSame(ProposalStatus::Draft, $event->proposal()->status);
        $this->assertSame($user->id, $event->proposal()->proposedBy);
        $this->assertSame('Nota', $event->proposal()->note);
        $this->assertTrue($event->proposal()->isUnsubmittedDraft());
    }

    public function test_panel_events_have_no_review(): void
    {
        $model = EventModel::factory()->create();

        $this->assertNull((new EloquentEventRepository)->findById(new EventId($model->id))?->proposal());
    }

    public function test_the_event_repository_saves_the_review(): void
    {
        $user = UserModel::factory()->create();
        $reviewer = UserModel::factory()->create();
        $submittedAt = new DateTimeImmutable('2026-11-10 10:00:00');
        $event = new Event(
            id: EventId::generate(),
            title: 'Jornada',
            slug: new Slug('jornada'),
            description: '<p>Texto</p>',
            startDate: new DateTimeImmutable('+1 week'),
            endDate: new DateTimeImmutable('+1 week +2 hours'),
            proposal: new ProposalReview(
                status: ProposalStatus::Rejected,
                proposedBy: $user->id,
                note: 'Nota',
                reason: 'No encaja',
                submittedAt: $submittedAt,
                reviewedAt: new DateTimeImmutable('2026-11-11 10:00:00'),
                reviewedBy: $reviewer->id,
            ),
        );

        (new EloquentEventRepository)->save($event);

        $stored = EventModel::query()->findOrFail($event->id()->value);
        $this->assertSame(ProposalStatus::Rejected, $stored->proposal_status);
        $this->assertSame($user->id, $stored->proposed_by);
        $this->assertSame('No encaja', $stored->proposal_reason);
        $this->assertSame($reviewer->id, $stored->reviewed_by);
        $this->assertSame('2026-11-10 10:00:00', $stored->submitted_at?->format('Y-m-d H:i:s'));
    }

    public function test_the_event_repository_updates_an_unsubmitted_draft_instead_of_inserting_it_again(): void
    {
        $model = EventModel::factory()->proposal()->create(['title' => 'Antes']);
        $repository = new EloquentEventRepository;
        $event = $repository->findById(new EventId($model->id));
        $this->assertInstanceOf(Event::class, $event);

        $repository->save($event);

        $this->assertSame(1, EventModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_the_article_repository_reads_and_saves_the_review(): void
    {
        $user = UserModel::factory()->create();
        $model = ArticleModel::factory()->proposal(ProposalStatus::PendingReview, $user)->create();
        $repository = new EloquentArticleRepository;

        $article = $repository->findById(new ArticleId($model->id));
        $this->assertInstanceOf(Article::class, $article);
        $this->assertInstanceOf(ProposalReview::class, $article->proposal());
        $this->assertSame(ProposalStatus::PendingReview, $article->proposal()->status);
        $this->assertSame($user->id, $article->proposal()->proposedBy);

        $draft = new Article(
            id: ArticleId::generate(),
            title: 'Crónica',
            slug: new Slug('cronica-'.substr($model->id, 0, 8)),
            content: '<p>Texto</p>',
            authorId: $user->id,
            proposal: new ProposalReview(status: ProposalStatus::Draft, proposedBy: $user->id),
        );
        $repository->save($draft);

        $this->assertSame($user->id, $repository->findById($draft->id())?->proposal()?->proposedBy);
        $this->assertNull(ArticleModel::query()->find($draft->id()->value), 'hidden from other queries');
    }
}
