<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Digest\Jobs;

use App\Infrastructure\Mail\Digest\MailDigestSender;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one person's daily digest (core#333). It carries only the account id and the cutoff, so
 * nothing personal waits in the queue; a failure leaves the notices for a retry or the next run.
 */
final class SendMailDigestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * @param  string  $cutoff  ISO 8601 instant: notices kept before it go in this digest.
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $cutoff,
    ) {}

    public function handle(MailDigestSender $sender): void
    {
        $sender->send($this->userId, CarbonImmutable::parse($this->cutoff));
    }
}
