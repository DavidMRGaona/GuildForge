<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Observers;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Activity\ProfileCompletion;
use App\Infrastructure\Activity\Sources\AccountActivitySource;
use App\Infrastructure\Activity\Sources\ProposalActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use WeakMap;

/**
 * Tells the activity contract (core#777) what an account's changes do to what it reports, whatever
 * path makes them (registration, the panel, a command, a seeder):
 *
 * - created: the account exists (`core.account`);
 * - public name set or cleared: the account and its profile start or stop counting;
 * - public name or avatar changed: the profile may be complete or not (`core.profile-completed`);
 * - anonymized, deactivated, restored or deleted: the account, its profile and its approved
 *   proposals start or stop counting.
 *
 * Never fails the save: a failure is logged and the next change publishes again. The queries that
 * can fail run in a savepoint of their own, so they cannot abort the caller's transaction
 * (PostgreSQL would refuse every later statement); the publications run in the caller's, so the
 * publisher announces a subject once per transaction.
 */
final class UserActivityObserver
{
    /** @var WeakMap<UserModel, list<string>>|null the approved proposals of an account being deleted for good */
    private static ?WeakMap $proposalsOfDeletedAccounts = null;

    /** Laravel builds an observer for every event it handles, so it gets the publisher of the request or job at hand */
    public function __construct(
        private readonly ActivityPublisherInterface $activity,
        private readonly ProfileCompletion $completion,
    ) {}

    public function created(UserModel $user): void
    {
        $this->safely($user, fn () => $this->activity->recorded(AccountActivitySource::TYPE, $this->id($user)));
    }

    public function updated(UserModel $user): void
    {
        if ($user->wasChanged('anonymized_at')) {
            $this->everythingOf($user, readProposals: true);

            return;
        }

        if ($user->wasChanged('display_name')) {
            $this->safely($user, function () use ($user): void {
                $previous = $user->getPrevious()['display_name'] ?? null;

                // Only a name set or cleared changes who counts; a new one changes nothing reported
                if ($this->filled($previous) !== $this->filled($user->display_name)) {
                    $this->activity->recorded(AccountActivitySource::TYPE, $this->id($user));
                }

                $this->completion->refresh((string) $user->getKey());
            });
        }

        if ($user->wasChanged('avatar_public_id')) {
            $this->safely($user, fn () => $this->completion->refresh((string) $user->getKey()));
        }
    }

    /**
     * Before a permanent deletion, which empties the proposals' proposer (the column goes null): the
     * proposals are read while the account still has them and announced once it is gone. An observer
     * is built for every event, so what it remembers lives next to the model.
     */
    public function forceDeleting(UserModel $user): void
    {
        $this->safely($user, function () use ($user): void {
            $this->deletions()[$user] = $this->approvedProposalsOf((string) $user->getKey());
        });
    }

    public function forceDeleted(UserModel $user): void
    {
        $subjects = $this->deletions()[$user] ?? [];
        unset($this->deletions()[$user]);

        $this->safely($user, function () use ($subjects): void {
            foreach ($subjects as $subjectId) {
                $this->activity->recorded(ProposalActivitySource::TYPE, $subjectId);
            }
        });
    }

    /** Soft or permanent: either way the account stops counting */
    public function deleted(UserModel $user): void
    {
        // A permanent deletion has no proposals left to read: forceDeleted announces what forceDeleting read
        $this->everythingOf($user, readProposals: ! $user->isForceDeleting());
    }

    public function restored(UserModel $user): void
    {
        $this->everythingOf($user, readProposals: true);
    }

    /**
     * The account, its profile and, unless $readProposals is false, its approved proposals. Each part
     * is guarded on its own, and the proposals are read inside the guard: a read that fails loses
     * only their publication, never the anonymizing, deletion or restoration being observed.
     */
    private function everythingOf(UserModel $user, bool $readProposals): void
    {
        $this->safely($user, function () use ($user): void {
            $this->activity->recorded(AccountActivitySource::TYPE, $this->id($user));
            $this->completion->refresh((string) $user->getKey(), publish: true);
        });

        if (! $readProposals) {
            return;
        }

        $this->safely($user, function () use ($user): void {
            foreach ($this->approvedProposalsOf((string) $user->getKey()) as $subjectId) {
                $this->activity->recorded(ProposalActivitySource::TYPE, $subjectId);
            }
        });
    }

    /**
     * @return WeakMap<UserModel, list<string>>
     */
    private function deletions(): WeakMap
    {
        return self::$proposalsOfDeletedAccounts ??= new WeakMap;
    }

    /**
     * @return list<string> the subject ids of the approved proposals of an account, published or not
     */
    private function approvedProposalsOf(string $userId): array
    {
        // A savepoint of its own: a failing read cannot abort the caller's transaction
        return DB::transaction(static function () use ($userId): array {
            $subjects = [];

            foreach (ProposalType::cases() as $type) {
                $ids = match ($type) {
                    ProposalType::Event => EventModel::query()->where('proposed_by', $userId)->where('proposal_status', ProposalStatus::Approved->value)->pluck('id'),
                    ProposalType::Article => ArticleModel::query()->where('proposed_by', $userId)->where('proposal_status', ProposalStatus::Approved->value)->pluck('id'),
                };

                foreach ($ids as $id) {
                    $subjects[] = ProposalActivitySource::subjectIdOf($type, (string) $id);
                }
            }

            return $subjects;
        });
    }

    private function id(UserModel $user): string
    {
        return strtolower((string) $user->getKey());
    }

    private function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function safely(UserModel $user, Closure $publish): void
    {
        try {
            // No savepoint here: the publisher announces a subject once per transaction, and a savepoint
            // would split what one change publishes. The queries that can fail open their own.
            $publish();
        } catch (Throwable $exception) {
            // Only the class: a message may carry what a person can see
            Log::warning('The activity of an account could not be published.', [
                'user_id' => (string) $user->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
