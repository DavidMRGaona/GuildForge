<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Proposals;

use App\Domain\Proposals\Enums\ProposalAuthorAction;
use App\Domain\Proposals\Enums\ProposalStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProposalStatusTest extends TestCase
{
    public function test_it_stores_the_agreed_values(): void
    {
        $this->assertSame(
            ['draft', 'pending_review', 'approved', 'rejected'],
            array_map(static fn (ProposalStatus $status): string => $status->value, ProposalStatus::cases()),
        );
    }

    /**
     * @return array<string, array{ProposalStatus, list<ProposalAuthorAction>}>
     */
    public static function authorActions(): array
    {
        return [
            'draft' => [ProposalStatus::Draft, [ProposalAuthorAction::Edit, ProposalAuthorAction::Submit, ProposalAuthorAction::Delete]],
            'pending review' => [ProposalStatus::PendingReview, [ProposalAuthorAction::Withdraw]],
            'approved' => [ProposalStatus::Approved, []],
            'rejected' => [ProposalStatus::Rejected, [ProposalAuthorAction::Delete]],
        ];
    }

    /**
     * @param  list<ProposalAuthorAction>  $allowed
     */
    #[DataProvider('authorActions')]
    public function test_the_author_may_do_only_what_each_status_allows(ProposalStatus $status, array $allowed): void
    {
        $this->assertSame($allowed, $status->authorActions());

        foreach (ProposalAuthorAction::cases() as $action) {
            $this->assertSame(
                in_array($action, $allowed, true),
                ProposalStatus::allowsAuthor($status, $action),
                $status->value.' / '.$action->value,
            );
        }
    }

    public function test_the_author_may_do_nothing_with_content_created_in_the_panel(): void
    {
        foreach (ProposalAuthorAction::cases() as $action) {
            $this->assertFalse(ProposalStatus::allowsAuthor(null, $action));
        }
    }

    public function test_the_panel_publishes_only_its_own_content_and_approved_proposals(): void
    {
        $this->assertTrue(ProposalStatus::allowsPanelPublishing(null));
        $this->assertTrue(ProposalStatus::allowsPanelPublishing(ProposalStatus::Approved));
        $this->assertFalse(ProposalStatus::allowsPanelPublishing(ProposalStatus::Draft));
        $this->assertFalse(ProposalStatus::allowsPanelPublishing(ProposalStatus::PendingReview));
        $this->assertFalse(ProposalStatus::allowsPanelPublishing(ProposalStatus::Rejected));
    }

    public function test_every_proposal_but_an_approved_one_is_unapproved(): void
    {
        $this->assertFalse(ProposalStatus::isUnapproved(null));
        $this->assertFalse(ProposalStatus::isUnapproved(ProposalStatus::Approved));
        $this->assertTrue(ProposalStatus::isUnapproved(ProposalStatus::Draft));
        $this->assertTrue(ProposalStatus::isUnapproved(ProposalStatus::PendingReview));
        $this->assertTrue(ProposalStatus::isUnapproved(ProposalStatus::Rejected));
    }

    public function test_unapproved_statuses_list_every_status_kept_out_of_public_view(): void
    {
        $this->assertSame(['draft', 'pending_review', 'rejected'], ProposalStatus::unapprovedValues());
    }
}
