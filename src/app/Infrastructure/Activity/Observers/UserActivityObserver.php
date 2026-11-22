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
 * Never fails the save: each publication runs in a savepoint of its own, so a failing query cannot
 * abort the caller's transaction (PostgreSQL would refuse every later statement), and the failure
 * is logged; the next change publishes again.
 */
final readonly class UserActivityObserver
{
    /** Laravel builds an observer for every event it handles, so it gets the publisher of the request or job at hand */
    public function __construct(
        private ActivityPublisherInterface $activity,
        private ProfileCompletion $completion,
    ) {}

    public function created(UserModel $user): void
    {
        $this->safely($user, fn () => $this->activity->recorded(AccountActivitySource::TYPE, $this->id($user)));
    }

    public function updated(UserModel $user): void
    {
        if ($user->wasChanged('anonymized_at')) {
            $this->everythingOf($user, $this->approvedProposalsOf((string) $user->getKey()));

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
     * proposals are read while the account still has them. Nothing is announced before the deletion
     * commits, so the consumer reads them as they are then; a rolled back deletion announces nothing.
     */
    public function forceDeleting(UserModel $user): void
    {
        $this->safely($user, function () use ($user): void {
            foreach ($this->approvedProposalsOf((string) $user->getKey()) as $subjectId) {
                $this->activity->recorded(ProposalActivitySource::TYPE, $subjectId);
            }
        });
    }

    /** Soft or permanent: either way the account stops counting */
    public function deleted(UserModel $user): void
    {
        $this->everythingOf($user, $this->approvedProposalsOf((string) $user->getKey()));
    }

    public function restored(UserModel $user): void
    {
        $this->everythingOf($user, $this->approvedProposalsOf((string) $user->getKey()));
    }

    /**
     * @param  list<string>  $proposals  the subject ids of the account's approved proposals
     */
    private function everythingOf(UserModel $user, array $proposals): void
    {
        $this->safely($user, function () use ($user, $proposals): void {
            $this->activity->recorded(AccountActivitySource::TYPE, $this->id($user));
            $this->completion->refresh((string) $user->getKey(), publish: true);

            foreach ($proposals as $subjectId) {
                $this->activity->recorded(ProposalActivitySource::TYPE, $subjectId);
            }
        });
    }

    /**
     * @return list<string> the subject ids of the approved proposals of an account, published or not
     */
    private function approvedProposalsOf(string $userId): array
    {
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
            // A savepoint inside the caller's transaction: a failing query is undone alone
            DB::transaction(static fn (): mixed => $publish());
        } catch (Throwable $exception) {
            // Only the class: a message may carry what a person can see
            Log::warning('The activity of an account could not be published.', [
                'user_id' => (string) $user->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
