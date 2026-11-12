<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Feed;

use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\CalendarFeedTokenModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Tokens are 32 random bytes (256 bits) in base64url. That much entropy makes a
 * plain SHA-256 enough to store them: there is nothing to brute-force, so a slow
 * password hash would only make every feed request slower. Lookups go by the hash,
 * which has a unique index.
 */
final readonly class EloquentCalendarFeedTokenService implements CalendarFeedTokenServiceInterface
{
    private const int TOKEN_BYTES = 32;

    /** Length of a 32-byte token in base64url without padding. */
    private const string TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}\z/';

    public function issue(string $userId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');

        DB::transaction(static function () use ($userId, $token): void {
            CalendarFeedTokenModel::query()->where('user_id', $userId)->delete();
            CalendarFeedTokenModel::query()->create([
                'user_id' => $userId,
                'token_hash' => self::hash($token),
            ]);
        });

        return $token;
    }

    public function revoke(string $userId): void
    {
        CalendarFeedTokenModel::query()->where('user_id', $userId)->delete();
    }

    public function issuedAt(string $userId): ?DateTimeImmutable
    {
        $token = CalendarFeedTokenModel::query()->where('user_id', $userId)->first();

        return $token?->created_at?->toDateTimeImmutable();
    }

    public function userIdFor(string $token): ?string
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }

        $userId = CalendarFeedTokenModel::query()->where('token_hash', self::hash($token))->value('user_id');

        return is_string($userId) ? $userId : null;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
