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
use Illuminate\Support\Str;

final readonly class EloquentSiteNotificationRepository implements SiteNotificationRepositoryInterface
{
    /** Distinct actors kept per group: enough to say «y 99 más», small enough to stay a short list. */
    public const int MAX_ACTORS = 100;

    /** When each person last looked (core#800): the `sort_key` of the newest notice they had then. */
    private const string SEEN_TABLE = 'user_site_notification_seen';

    public function __construct(
        private TimezoneServiceInterface $timezone,
    ) {}

    public function deliver(UserId $userId, SiteNotice $notice): string
    {
        return DB::transaction(function () use ($userId, $notice): string {
            $group = $notice->groupKey === null ? null : SiteNotificationModel::query()
                ->where('user_id', $userId->value())
                ->where('group_key', $notice->groupKey)
                ->whereNull('read_at')
                ->whereNull('hidden_at')
                ->orderByDesc('sort_key')
                ->lockForUpdate()
                ->first();

            if (! $group instanceof SiteNotificationModel) {
                return SiteNotificationModel::query()->create([
                    ...$this->texts($notice, $notice->title),
                    'user_id' => $userId->value(),
                    'group_key' => $notice->groupKey,
                    'count' => 1,
                    'actor_ids' => $notice->actorId === null ? null : [$notice->actorId],
                    'notified_at' => now(),
                    'sort_key' => $this->sortKey(),
                ])->id;
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

            return $group->id;
        });
    }

    public function findVisible(UserId $userId, string $id): ?StoredSiteNotice
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $row = SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereKey($id)
            ->whereNull('hidden_at')
            ->first();

        return $row instanceof SiteNotificationModel ? $this->toStored($row) : null;
    }

    public function unreadCount(UserId $userId): int
    {
        return SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('read_at')
            ->whereNull('hidden_at')
            ->count();
    }

    public function unseenCount(UserId $userId): int
    {
        return SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('read_at')
            ->whereNull('hidden_at')
            // One query: no mark yet means everything unread is new
            ->where('sort_key', '>', static fn ($query) => $query
                ->selectRaw('coalesce(max(seen_key), 0)')
                ->from(self::SEEN_TABLE)
                ->where('user_id', $userId->value()))
            ->count();
    }

    public function markSeen(UserId $userId): void
    {
        // The newest notice already stored, never the clock: deliver() sets its sort_key inside a
        // transaction, before the commit, so a notice being delivered while the person looks has
        // a key below the clock and would never count as new. One not committed yet is not seen
        // here, so it stays above the mark
        $key = SiteNotificationModel::query()->where('user_id', $userId->value())->max('sort_key');

        if (! is_numeric($key)) {
            // Nothing stored: no mark, everything that arrives counts
            return;
        }

        $key = (int) $key;

        DB::table(self::SEEN_TABLE)->insertOrIgnore(['user_id' => $userId->value(), 'seen_key' => $key]);
        // Never back: once seen, a notice never counts as new again
        DB::table(self::SEEN_TABLE)
            ->where('user_id', $userId->value())
            ->where('seen_key', '<', $key)
            ->update(['seen_key' => $key]);
    }

    public function count(UserId $userId, bool $onlyUnread = false): int
    {
        return SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('hidden_at')
            ->when($onlyUnread, static fn ($query) => $query->whereNull('read_at'))
            ->count();
    }

    public function latest(UserId $userId, int $limit, int $offset, bool $onlyUnread = false): array
    {
        if ($limit < 1) {
            return [];
        }

        return array_values(SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('hidden_at')
            ->when($onlyUnread, static fn ($query) => $query->whereNull('read_at'))
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

    public function markUnread(UserId $userId, string $id): bool
    {
        $row = SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereNull('hidden_at')
            ->whereKey($id)
            ->first();

        if (! $row instanceof SiteNotificationModel) {
            return false;
        }

        if ($row->read_at !== null) {
            $row->read_at = null;
            $row->save();
        }

        return true;
    }

    public function hide(UserId $userId, string $id): bool
    {
        $row = SiteNotificationModel::query()
            ->where('user_id', $userId->value())
            ->whereKey($id)
            ->first();

        if (! $row instanceof SiteNotificationModel) {
            return false;
        }

        if ($row->hidden_at === null) {
            $row->hidden_at = now();
            $row->read_at ??= now();
            $row->save();
        }

        return true;
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
            groupKey: $row->group_key,
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
