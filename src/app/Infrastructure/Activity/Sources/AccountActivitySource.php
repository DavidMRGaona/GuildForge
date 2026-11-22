<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Sources;

use App\Domain\Activity\ActivityKind;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Account age (core#777): one subject per active account with a public name and a profile every
 * member may see, the account as `owner`, dated when it was created. Never an anonymized account,
 * never a deactivated one until it is restored.
 *
 * A module's `years of membership` style rules count from `occurredAt`; the consumer decides what
 * seniority means.
 */
final class AccountActivitySource extends AccountBasedActivitySource
{
    public const string TYPE = 'core.account';

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::Account;
    }

    public function label(): string
    {
        return __('activity.account.label');
    }

    protected function occurredAt(Collection $users): array
    {
        $instants = [];

        foreach ($users as $user) {
            if ($user->created_at !== null) {
                $instants[strtolower((string) $user->id)] = DateTimeImmutable::createFromInterface($user->created_at);
            }
        }

        return $instants;
    }

    protected function titleOf(string $publicName): string
    {
        return __('activity.account.title', ['name' => $publicName]);
    }

    protected function anonymousTitle(): string
    {
        return __('activity.account.title_private');
    }
}
