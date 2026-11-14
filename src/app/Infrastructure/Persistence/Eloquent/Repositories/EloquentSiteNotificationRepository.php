<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Services\TimezoneServiceInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Domain\SiteNotifications\ValueObjects\SiteNotice;
use App\Domain\SiteNotifications\ValueObjects\StoredSiteNotice;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\SiteNotificationModel;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final readonly class EloquentSiteNotificationRepository implements SiteNotificationRepositoryInterface
{
    /** Distinct actors kept per group: enough to say «y 99 más», small enough to stay a short list. */
    public const int MAX_ACTORS = 100;

    public function __construct(
        private TimezoneServiceInterface $timezone,
    ) {}

    public function deliver(UserId $userId, SiteNotice $notice): void
    {
        DB::transaction(function () use ($userId, $notice): void {
            $group = $notice->groupKey === null ? null : SiteNotificationModel::query()
                ->where('user_id', $userId->value())
                ->where('group_key', $notice->groupKey)
                ->whereNull('read_at')
                ->orderByDesc('sort_key')
                ->lockForUpdate()
                ->first();

            if (! $group instanceof SiteNotificationModel) {
                SiteNotificationModel::query()->create([
                    ...$this->texts($notice, $notice->title),
                    'user_id' => $userId->value(),
                    'group_key' => $notice->groupKey,
                    'count' => 1,
                    'actor_ids' => $notice->actorId === null ? null : [$notice->actorId],
                    'notified_at' => now(),
                    'sort_key' => $this->sortKey(),
                ]);

                return;
            }

            $actors = $this->withActor($group->actor_ids ?? [], $notice->actorId);
            $count = $group->count + 1;
            // The newest actor leads («Ana y 3 más»); without actors, every other notice counts
            $otherActors = $actors === [] ? $count - 1 : count($actors) - ($notice->actorId === null ? 0 : 1);

            $group->fill([
                ...$this->texts($notice, $notice->titleFor($count, max(0, $otherActors))),
                'count' => $count,
                'actor_ids' => $actors === [] ? null : $actors,
                'notified_at' => now(),
                'sort_key' => $this->sortKey(),
            ])->save();
        });
    }

    public function unreadCount(UserId $userId): int
    {
        return SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('read_at')
            ->count();
    }

    public function count(UserId $userId): int
    {
        return SiteNotificationModel::query()->where('user_id', $userId->value())->count();
    }

    public function latest(UserId $userId, int $limit, int $offset): array
    {
        if ($limit < 1) {
            return [];
        }

        return array_values(SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->orderByDesc('sort_key')
            ->orderByDesc('id')
            ->offset(max(0, $offset))
            ->limit($limit)
            ->get()
            ->map(fn (SiteNotificationModel $row): StoredSiteNotice => $this->toStored($row))
            ->all());
    }

    public function markRead(UserId $userId, string $id): ?StoredSiteNotice
    {
        $row = SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereKey($id)
            ->first();

        if (! $row instanceof SiteNotificationModel) {
            return null;
        }

        if ($row->read_at === null) {
            $row->read_at = now();
            $row->save();
        }

        return $this->toStored($row);
    }

    public function markAllRead(UserId $userId): int
    {
        return SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('read_at')
            ->update(['read_at' => $this->bound(now()), 'updated_at' => $this->bound(now())]);
    }

    public function deleteForUser(UserId $userId): void
    {
        SiteNotificationModel::query()->where('user_id', $userId->value())->delete();
    }

    public function deleteForSubject(string $type, string $id): void
    {
        SiteNotificationModel::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->delete();
    }

    public function markReadForSubject(string $category, string $type, string $id): int
    {
        return SiteNotificationModel::query()
            ->where('category', $category)
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => $this->bound(now()), 'updated_at' => $this->bound(now())]);
    }

    public function deleteForActor(string $actorId): void
    {
        SiteNotificationModel::query()->whereJsonContains('actor_ids', $actorId)->delete();
    }

    public function prune(DateTimeInterface $readBefore, DateTimeInterface $unreadBefore): int
    {
        $read = SiteNotificationModel::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', $this->bound($readBefore))
            ->delete();

        $unread = SiteNotificationModel::query()
            ->whereNull('read_at')
            ->where('notified_at', '<', $this->bound($unreadBefore))
            ->delete();

        return $read + $unread;
    }

    /**
     * @return array<string, string|null>
     */
    private function texts(SiteNotice $notice, string $title): array
    {
        return [
            'category' => $notice->category,
            'title' => $title,
            'body' => $notice->body,
            'url' => $notice->url,
            'icon' => $notice->icon,
            'subject_type' => $notice->subjectType,
            'subject_id' => $notice->subjectId,
        ];
    }

    /**
     * The group's actors with this one last (it is the newest), each once.
     *
     * @param  array<int, mixed>  $actors
     * @return list<string>
     */
    private function withActor(array $actors, ?string $actorId): array
    {
        $kept = array_values(array_filter($actors, static fn (mixed $actor): bool => is_string($actor) && $actor !== $actorId));

        if ($actorId !== null) {
            $kept[] = $actorId;
        }

        return array_slice($kept, -self::MAX_ACTORS);
    }

    private function toStored(SiteNotificationModel $row): StoredSiteNotice
    {
        return new StoredSiteNotice(
            id: $row->id,
            category: $row->category,
            title: $row->title,
            body: $row->body,
            url: $row->url,
            icon: $row->icon,
            count: $row->count,
            readAt: $row->read_at instanceof CarbonInterface ? DateTimeImmutable::createFromInterface($row->read_at) : null,
            notifiedAt: DateTimeImmutable::createFromInterface($row->notified_at),
        );
    }

    /**
     * Microseconds since the epoch: the latest notice of a row sorts it, even within one second.
     */
    private function sortKey(): int
    {
        return (int) now()->format('Uu');
    }

    /**
     * Query bindings bypass the model's zone handling: compare in the storage zone.
     */
    private function bound(DateTimeInterface $instant): string
    {
        return $this->timezone->toStorage($instant)->format('Y-m-d H:i:s');
    }
}
