<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

use App\Application\Services\TimezoneServiceInterface;
use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Application\WebPush\DTOs\WebPushSubscriptionInputDTO;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\WebPushSubscriptionModel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class EloquentWebPushSubscriptionService implements WebPushSubscriptionServiceInterface
{
    /** Browsers kept per account: subscribing one more forgets the one used longest ago. */
    public const int MAX_PER_USER = 20;

    public function __construct(
        private TimezoneServiceInterface $timezone,
    ) {}

    public function subscribe(string $userId, WebPushSubscriptionInputDTO $input): string
    {
        $device = UserAgentDevice::parse($input->userAgent);
        $values = [
            'user_id' => $userId,
            'endpoint' => $input->endpoint,
            'p256dh' => $input->p256dh,
            'auth' => $input->auth,
            'browser' => $device['browser'],
            'platform' => $device['platform'],
            'last_used_at' => now(),
        ];
        $hash = hash('sha256', $input->endpoint);

        try {
            $id = $this->upsert($hash, $values);
        } catch (UniqueConstraintViolationException) {
            // Two requests of the same browser at once: the other one created it, update it now
            $id = $this->upsert($hash, $values);
        }

        $this->keepNewest($userId);

        return $id;
    }

    public function refresh(string $userId, #[\SensitiveParameter] string $endpoint): ?string
    {
        $id = WebPushSubscriptionModel::query()
            ->where('user_id', $userId)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->value('id');

        if (! is_string($id)) {
            return null;
        }

        $this->markUsed($id);

        return $id;
    }

    public function unsubscribe(string $userId, #[\SensitiveParameter] string $endpoint): bool
    {
        return WebPushSubscriptionModel::query()
            ->where('user_id', $userId)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete() > 0;
    }

    public function forgetForUser(string $userId, string $subscriptionId): bool
    {
        return WebPushSubscriptionModel::query()->where('user_id', $userId)->whereKey($subscriptionId)->delete() > 0;
    }

    public function forget(string $subscriptionId): void
    {
        WebPushSubscriptionModel::query()->whereKey($subscriptionId)->delete();
    }

    public function forgetUser(string $userId): void
    {
        WebPushSubscriptionModel::query()->where('user_id', $userId)->delete();
    }

    public function find(string $subscriptionId): ?WebPushSubscriptionDTO
    {
        $model = WebPushSubscriptionModel::query()->find($subscriptionId);

        return $model instanceof WebPushSubscriptionModel ? $this->toDto($model) : null;
    }

    public function forUser(string $userId): array
    {
        return array_values(WebPushSubscriptionModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map($this->toDto(...))
            ->all());
    }

    public function markUsed(string $subscriptionId): void
    {
        // Query bindings bypass the model's zone handling: written in the storage zone
        WebPushSubscriptionModel::query()->whereKey($subscriptionId)->update(['last_used_at' => $this->bound(now())]);
    }

    public function prune(DateTimeInterface $before): int
    {
        return WebPushSubscriptionModel::query()
            ->where(fn ($query) => $query
                ->where('last_used_at', '<', $this->bound($before))
                ->orWhere(fn ($never) => $never->whereNull('last_used_at')->where('created_at', '<', $this->bound($before))))
            ->delete();
    }

    /**
     * Query bindings bypass the model's zone handling: compare and write in the storage zone.
     */
    private function bound(DateTimeInterface $instant): string
    {
        return $this->timezone->toStorage($instant)->format('Y-m-d H:i:s');
    }

    private function keepNewest(string $userId): void
    {
        $kept = WebPushSubscriptionModel::query()
            ->where('user_id', $userId)
            ->orderByRaw('coalesce(last_used_at, created_at) desc')
            ->orderByDesc('id')
            ->take(self::MAX_PER_USER)
            ->pluck('id')
            ->all();

        WebPushSubscriptionModel::query()->where('user_id', $userId)->whereKeyNot($kept)->delete();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function upsert(string $hash, array $values): string
    {
        return DB::transaction(static function () use ($hash, $values): string {
            $model = WebPushSubscriptionModel::query()->where('endpoint_hash', $hash)->lockForUpdate()->first()
                ?? new WebPushSubscriptionModel(['endpoint_hash' => $hash]);

            $model->fill($values)->save();

            return $model->id;
        });
    }

    private function toDto(WebPushSubscriptionModel $model): WebPushSubscriptionDTO
    {
        return new WebPushSubscriptionDTO(
            id: $model->id,
            userId: $model->user_id,
            endpoint: $model->endpoint,
            p256dh: $model->p256dh,
            auth: $model->auth,
            browser: $model->browser,
            platform: $model->platform,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            lastUsedAt: $model->last_used_at === null ? null : DateTimeImmutable::createFromInterface($model->last_used_at),
        );
    }
}
