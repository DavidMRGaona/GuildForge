<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Article;
use App\Domain\Entities\Event;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Exceptions\ProposalNotApprovedException;
use App\Domain\Proposals\ValueObjects\ProposalReview;
use App\Domain\ValueObjects\ArticleId;
use App\Domain\ValueObjects\EventId;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProposalEntitiesTest extends TestCase
{
    private function event(?ProposalReview $proposal): Event
    {
        return new Event(
            id: EventId::generate(),
            title: 'Jornada de rol',
            slug: new Slug('jornada-de-rol'),
            description: 'Descripción',
            startDate: new DateTimeImmutable('+1 week'),
            endDate: new DateTimeImmutable('+1 week +3 hours'),
            proposal: $proposal,
        );
    }

    private function article(?ProposalReview $proposal): Article
    {
        return new Article(
            id: ArticleId::generate(),
            title: 'Crónica',
            slug: new Slug('cronica'),
            content: '<p>Texto</p>',
            authorId: 'author-1',
            proposal: $proposal,
        );
    }

    private function review(ProposalStatus $status, ?DateTimeImmutable $submittedAt = null): ProposalReview
    {
        return new ProposalReview(status: $status, proposedBy: 'author-1', submittedAt: $submittedAt);
    }

    public function test_content_created_in_the_panel_has_no_proposal_and_publishes(): void
    {
        $event = $this->event(null);
        $event->publish();
        $this->assertNull($event->proposal());
        $this->assertTrue($event->isPublished());

        $article = $this->article(null);
        $article->publish();
        $this->assertNull($article->proposal());
        $this->assertTrue($article->isPublished());
    }

    public function test_an_approved_proposal_publishes_like_any_other(): void
    {
        $event = $this->event($this->review(ProposalStatus::Approved, new DateTimeImmutable));
        $event->publish();
        $this->assertTrue($event->isPublished());

        $article = $this->article($this->review(ProposalStatus::Approved, new DateTimeImmutable));
        $article->publish();
        $this->assertTrue($article->isPublished());
    }

    public function test_an_unapproved_event_proposal_cannot_be_published(): void
    {
        foreach ([ProposalStatus::Draft, ProposalStatus::PendingReview, ProposalStatus::Rejected] as $status) {
            $event = $this->event($this->review($status));

            try {
                $event->publish();
                $this->fail('Published a '.$status->value.' proposal');
            } catch (ProposalNotApprovedException) {
                $this->assertFalse($event->isPublished());
            }
        }
    }

    public function test_an_unapproved_article_proposal_cannot_be_published(): void
    {
        foreach ([ProposalStatus::Draft, ProposalStatus::PendingReview, ProposalStatus::Rejected] as $status) {
            $article = $this->article($this->review($status));

            try {
                $article->publish();
                $this->fail('Published a '.$status->value.' proposal');
            } catch (ProposalNotApprovedException) {
                $this->assertFalse($article->isPublished());
                $this->assertNull($article->publishedAt());
            }
        }
    }

    public function test_the_review_tells_unsubmitted_drafts_from_returned_ones(): void
    {
        $this->assertTrue($this->review(ProposalStatus::Draft)->isUnsubmittedDraft());
        $this->assertFalse($this->review(ProposalStatus::Draft)->isReturned());

        $returned = $this->review(ProposalStatus::Draft, new DateTimeImmutable);
        $this->assertFalse($returned->isUnsubmittedDraft());
        $this->assertTrue($returned->isReturned());

        $this->assertFalse($this->review(ProposalStatus::PendingReview, new DateTimeImmutable)->isUnsubmittedDraft());
    }
}
