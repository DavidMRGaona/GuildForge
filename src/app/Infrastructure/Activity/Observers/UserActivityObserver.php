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
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the activity contract (core#777) what an account's changes do to what it reports, whatever
 * path makes them (registration, the panel, a command, a seeder):
 *
 * - created: the account exists (`core.account`);
 * - public name or avatar changed: the profile may be complete or not (`core.profile-completed`);
 * - anonymized, deactivated, restored or deleted: the account, its profile and its approved
 *   proposals start or stop counting.
 *
 * Never fails the save: a failure is logged and the next change publishes again.
 */
final readonly class UserActivityObserver
{
    /**
     * The container, not the publisher and the tracker: an observer lives as long as the process and
     * the publisher is scoped to a request or job.
     */
    public function __construct(
        private Container $container,
    ) {}

    public function created(UserModel $user): void
    {
        $this->safely($user, function () use ($user): void {
            $this->activity()->recorded(AccountActivitySource::TYPE, $this->id($user));
            $this->completion()->refresh((string) $user->getKey());
        });
    }

    public function updated(UserModel $user): void
    {
        if ($user->wasChanged('anonymized_at')) {
            $this->everythingOf($user);

            return;
        }

        if ($user->wasChanged(['display_name', 'avatar_public_id'])) {
            $this->safely($user, fn () => $this->completion()->refresh((string) $user->getKey()));
        }
    }

    /** Soft or permanent: either way the account stops counting */
    public function deleted(UserModel $user): void
    {
        $this->everythingOf($user);
    }

    public function restored(UserModel $user): void
    {
        $this->everythingOf($user);
    }

    private function everythingOf(UserModel $user): void
    {
        $this->safely($user, function () use ($user): void {
            $userId = (string) $user->getKey();

            $this->activity()->recorded(AccountActivitySource::TYPE, $this->id($user));
            $this->completion()->refresh($userId);

            foreach ($this->approvedProposalsOf($userId) as $subjectId) {
                $this->activity()->recorded(ProposalActivitySource::TYPE, $subjectId);
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

    private function activity(): ActivityPublisherInterface
    {
        return $this->container->make(ActivityPublisherInterface::class);
    }

    private function completion(): ProfileCompletion
    {
        return $this->container->make(ProfileCompletion::class);
    }

    private function id(UserModel $user): string
    {
        return strtolower((string) $user->getKey());
    }

    private function safely(UserModel $user, callable $publish): void
    {
        try {
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
