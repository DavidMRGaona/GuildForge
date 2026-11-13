<?php

declare(strict_types=1);

namespace App\Console\Commands\Proposals;

use App\Application\Proposals\Services\ProposalServiceInterface;
use Illuminate\Console\Command;

/**
 * Deletes proposals rejected longer ago than the proposals settings keep them (90 days by
 * default). Their images, if nothing else uses them, are left to the media library.
 */
final class PurgeRejectedProposalsCommand extends Command
{
    protected $signature = 'proposals:purge-rejected';

    protected $description = 'Delete event and article proposals rejected longer ago than the proposals settings keep them';

    public function handle(ProposalServiceInterface $proposals): int
    {
        $this->info('Purged: '.$proposals->purgeRejected());

        return self::SUCCESS;
    }
}
