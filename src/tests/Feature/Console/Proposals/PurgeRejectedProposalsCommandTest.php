<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Proposals;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class PurgeRejectedProposalsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_deletes_proposals_rejected_more_than_90_days_ago_by_default(): void
    {
        $old = EventModel::factory()->proposal(ProposalStatus::Rejected)->create(['reviewed_at' => now()->subDays(91)]);
        $recent = EventModel::factory()->proposal(ProposalStatus::Rejected)->create(['reviewed_at' => now()->subDays(89)]);

        $command = $this->artisan('proposals:purge-rejected');
        assert($command instanceof PendingCommand);
        $command->expectsOutput('Purged: 1')
            ->assertSuccessful()
            ->run();

        $this->assertNull(EventModel::query()->find($old->id));
        $this->assertNotNull(EventModel::query()->find($recent->id));
    }
}
