<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Jobs;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Profiles\Services\ProfileMembershipDisplayAnnouncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The association member role setting changed (core#824): everybody who held the old role stopped being a
 * member and everybody who holds the new one became one, so ProfileMembershipDisplayChanged goes out for
 * every account that holds either (deactivated ones too: they may be restored), one event per account, read
 * in batches. A run that is repeated announces the accounts again; whoever listens asks the reader, which
 * answers the same.
 */
final class AnnounceMembershipDisplayForRolesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const int BATCH_SIZE = 500;

    /**
     * @param  list<string>  $roleNames  the roles held before and after the change; never the same one twice
     */
    public function __construct(
        public readonly array $roleNames,
        public readonly int $batchSize = self::BATCH_SIZE,
    ) {}

    /**
     * Queues the announcement without ever failing the caller: with a sync queue the job runs inline, and a
     * broken queue throws on dispatch.
     */
    public static function dispatchSafely(?string $from, ?string $to): void
    {
        $roleNames = array_values(array_unique(array_filter([$from, $to], static fn (?string $name): bool => $name !== null && $name !== '')));

        if ($roleNames === [] || $from === $to) {
            return;
        }

        try {
            self::dispatch($roleNames)->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('[Profiles] The change of the association member role could not be announced to the membership display.', [
                'exception' => $exception::class,
            ]);
        }
    }

    public function handle(): void
    {
        if ($this->roleNames === []) {
            return;
        }

        $announcer = app(ProfileMembershipDisplayAnnouncer::class);
        $roles = DB::table('roles')->whereIn('name', $this->roleNames)->pluck('id')->all();

        if ($roles === []) {
            return;
        }

        UserModel::withTrashed()
            ->whereIn('id', DB::table('user_role')->whereIn('role_id', $roles)->select('user_id'))
            ->select('id')
            ->chunkById(max(1, $this->batchSize), function (Collection $users) use ($announcer): void {
                foreach ($users as $user) {
                    $announcer->dispatch((string) $user->getKey());
                }
            });
    }
}
