<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

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
            return $this->upsert($hash, $values);
        } catch (UniqueConstraintViolationException) {
            // Two requests of the same browser at once: the other one created it, update it now
            return $this->upsert($hash, $values);
        }
    }

    public function unsubscribe(string $userId, string $endpoint): bool
    {
        return WebPushSubscriptionModel::query()
            ->where('user_id', $userId)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete() > 0;
    }

    public function forgetForUser(string $userId, string $subscriptionId): void
    {
        WebPushSubscriptionModel::query()->where('user_id', $userId)->whereKey($subscriptionId)->delete();
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
        WebPushSubscriptionModel::query()->whereKey($subscriptionId)->update(['last_used_at' => now()]);
    }

    public function prune(DateTimeInterface $before): int
    {
        return WebPushSubscriptionModel::query()
            ->where(static fn ($query) => $query
                ->where('last_used_at', '<', $before)
                ->orWhere(static fn ($never) => $never->whereNull('last_used_at')->where('created_at', '<', $before)))
            ->delete();
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
