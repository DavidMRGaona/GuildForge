<?php

declare(strict_types=1);

namespace App\Console\Commands\Mail;

use App\Application\Mail\Services\MailDigestServiceInterface;
use Illuminate\Console\Command;

/**
 * Scheduled every hour: queues the daily digest of every person with notices kept before the last
 * digest hour (core#333). Running it again sends nothing twice.
 */
final class SendMailDigestsCommand extends Command
{
    protected $signature = 'mail:send-digests';

    protected $description = 'Queue the daily mail digests that are due and drop notices kept for over a week';

    public function handle(MailDigestServiceInterface $digests): int
    {
        $this->info('Digests queued: '.$digests->dispatchDue());

        return self::SUCCESS;
    }
}
