<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNews\Jobs;

use App\Application\SiteNews\DTOs\SiteNewsItemDTO;
use App\Application\SiteNews\Services\SiteNewsReaderInterface;
use App\Domain\SiteNews\SiteNewsKind;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Splits the news of one publication into batches of accounts, one DeliverSiteNewsJob each, so
 * no job grows with the number of accounts. Deactivated and anonymized accounts are left out.
 */
final class AnnounceSiteNewsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const int BATCH_SIZE = 100;

    /** A retry would queue the batches already queued a second time */
    public int $tries = 1;

    /**
     * @param  list<string>  $excludedIds  Accounts already told in another way: who published it, who proposed it.
     */
    public function __construct(
        public readonly SiteNewsKind $kind,
        public readonly string $id,
        public readonly array $excludedIds = [],
    ) {}

    public function handle(SiteNewsReaderInterface $reader): void
    {
        // Taken down or deleted while it waited: there is no news
        if (! $reader->find($this->kind, $this->id) instanceof SiteNewsItemDTO) {
            return;
        }

        UserModel::query()
            ->whereNull('anonymized_at')
            ->whereNotIn('id', $this->excludedIds)
            ->select('id')
            ->chunkById(self::BATCH_SIZE, function (Collection $accounts): void {
                /** @var list<string> $ids */
                $ids = $accounts->modelKeys();

                DeliverSiteNewsJob::dispatch($this->kind, $this->id, $ids);
            });
    }
}
