<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\UserServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * An account that is anonymized or deleted takes its unpublished proposals with it; what was
 * published stays (core#570). Deactivating keeps them: what is in review is still reviewed.
 */
final class ProposalAccountsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{unpublished: list<string>, approved: string}
     */
    private function proposalsOf(UserModel $user): array
    {
        return [
            'unpublished' => [
                EventModel::factory()->proposal(ProposalStatus::Draft, $user)->create()->id,
                EventModel::factory()->proposal(ProposalStatus::PendingReview, $user)->create()->id,
                ArticleModel::factory()->proposal(ProposalStatus::Rejected, $user)->create()->id,
            ],
            'approved' => EventModel::factory()->proposal(ProposalStatus::Approved, $user)->create()->id,
        ];
    }

    private function assertOnlyApprovedRemain(string $approved): void
    {
        $this->assertSame([$approved], EventModel::query()->includingUnsubmittedProposals()->pluck('id')->all());
        $this->assertSame(0, ArticleModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_anonymizing_deletes_the_unpublished_proposals(): void
    {
        $user = UserModel::factory()->create();
        $proposals = $this->proposalsOf($user);

        app(UserServiceInterface::class)->anonymize($user->id);

        $this->assertOnlyApprovedRemain($proposals['approved']);
    }

    public function test_anonymizing_with_content_transfer_deletes_them_too(): void
    {
        $user = UserModel::factory()->create();
        $heir = UserModel::factory()->create();
        $proposals = $this->proposalsOf($user);

        app(UserServiceInterface::class)->anonymizeWithContentTransfer(new AnonymizeUserDTO(
            userId: $user->id,
            contentAction: 'transfer',
            transferToUserId: $heir->id,
        ));

        $this->assertOnlyApprovedRemain($proposals['approved']);
    }

    public function test_deleting_the_account_deletes_them_and_keeps_the_published_event(): void
    {
        $user = UserModel::factory()->create();
        $proposals = $this->proposalsOf($user);

        $user->forceDelete();

        $this->assertOnlyApprovedRemain($proposals['approved']);
        $this->assertNull(EventModel::query()->findOrFail($proposals['approved'])->proposed_by);
    }

    public function test_deactivating_the_account_keeps_them(): void
    {
        $user = UserModel::factory()->create();
        $this->proposalsOf($user);

        $user->delete();

        $this->assertSame(3, EventModel::query()->includingUnsubmittedProposals()->count());
        $this->assertSame(1, ArticleModel::query()->includingUnsubmittedProposals()->count());
    }
}
