<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailCategoryOptOutDTO;
use App\Application\Mail\DTOs\MailOptOutReportDTO;
use App\Application\Mail\Services\MailCategoryOptOutStatisticsInterface;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Active" is neither anonymized nor deactivated (soft-deleted). A category without audience
 * is everyone's, so it costs two grouped counts. One with an audience has no SQL equivalent:
 * its closure runs on the active accounts in chunks and the result is cached for a few
 * minutes, so opening the page never repeats that work.
 */
final readonly class MailCategoryOptOutStatistics implements MailCategoryOptOutStatisticsInterface
{
    private const int CHUNK_SIZE = 500;

    private const int AUDIENCE_CACHE_SECONDS = 600;

    public function __construct(
        private MailCategoryRegistryInterface $categories,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function report(): MailOptOutReportDTO
    {
        $optional = array_values(array_filter(
            $this->categories->all(),
            static fn (MailCategoryDTO $category): bool => ! $category->isMandatory(),
        ));

        if ($optional === []) {
            return new MailOptOutReportDTO([], $this->pausedAccounts());
        }

        $optedOut = $this->optedOutByCategory();
        $active = null;
        $rows = [];

        foreach ($optional as $category) {
            if ($category->audience instanceof Closure) {
                [$eligible, $count] = $this->audienceCounts($category);
            } else {
                $active ??= $this->activeAccounts()->count();
                $eligible = $active;
                $count = $optedOut[$category->key] ?? 0;
            }

            $rows[] = new MailCategoryOptOutDTO(
                key: $category->key,
                section: $this->translate($category->section),
                label: $this->translate($category->label),
                optedOut: $count,
                eligible: $eligible,
                percentage: $eligible === 0 ? 0.0 : round($count / $eligible * 100, 1),
            );
        }

        return new MailOptOutReportDTO($rows, $this->pausedAccounts());
    }

    private function activeAccounts(): Builder
    {
        return DB::table('users')->whereNull('deleted_at')->whereNull('anonymized_at');
    }

    /**
     * @return array<string, int> category key => active accounts that turned it off
     */
    private function optedOutByCategory(): array
    {
        return DB::table('user_mail_preferences')
            ->join('users', 'users.id', '=', 'user_mail_preferences.user_id')
            ->whereNull('users.deleted_at')
            ->whereNull('users.anonymized_at')
            ->where('user_mail_preferences.enabled', false)
            ->groupBy('user_mail_preferences.category')
            ->selectRaw('user_mail_preferences.category as category, COUNT(*) as total')
            ->pluck('total', 'category')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    private function pausedAccounts(): int
    {
        $now = $this->timezone->toStorage(now())->format('Y-m-d H:i:s');

        return DB::table('user_mail_pauses')
            ->join('users', 'users.id', '=', 'user_mail_pauses.user_id')
            ->whereNull('users.deleted_at')
            ->whereNull('users.anonymized_at')
            ->where(static function (Builder $query) use ($now): void {
                $query->whereNull('user_mail_pauses.paused_until')
                    ->orWhere('user_mail_pauses.paused_until', '>', $now);
            })
            ->count();
    }

    /**
     * @return array{0: int, 1: int} [accounts that see the category, those that turned it off]
     */
    private function audienceCounts(MailCategoryDTO $category): array
    {
        /** @var array{0: int, 1: int} $counts */
        $counts = Cache::remember(
            'mail_optout_stats.audience.'.$category->key,
            self::AUDIENCE_CACHE_SECONDS,
            fn (): array => $this->computeAudienceCounts($category),
        );

        return $counts;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function computeAudienceCounts(MailCategoryDTO $category): array
    {
        $audience = $category->audience;
        if (! $audience instanceof Closure) {
            return [0, 0];
        }

        /** @var Collection<int, string> $turnedOff */
        $turnedOff = DB::table('user_mail_preferences')
            ->where('category', $category->key)
            ->where('enabled', false)
            ->pluck('user_id');
        $turnedOff = array_flip($turnedOff->all());

        $eligible = 0;
        $optedOut = 0;
        $failed = false;

        UserModel::query()
            ->whereNull('anonymized_at')
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($audience, $turnedOff, &$eligible, &$optedOut, &$failed): void {
                foreach ($users as $user) {
                    try {
                        if ($audience($user) !== true) {
                            continue;
                        }
                    } catch (Throwable) {
                        // Like the profile: whoever the check fails for does not see the category
                        $failed = true;

                        continue;
                    }

                    $eligible++;
                    if (isset($turnedOff[$user->id])) {
                        $optedOut++;
                    }
                }
            });

        if ($failed) {
            Log::warning('The audience check of a mail category failed for some accounts; they are not counted.', [
                'category' => $category->key,
            ]);
        }

        return [$eligible, $optedOut];
    }

    private function translate(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
