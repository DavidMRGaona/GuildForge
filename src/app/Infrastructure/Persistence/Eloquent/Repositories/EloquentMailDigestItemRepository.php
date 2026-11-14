<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Mail\Repositories\MailDigestItemRepositoryInterface;
use App\Domain\Mail\ValueObjects\MailDigestItem;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserMailDigestItemModel;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final readonly class EloquentMailDigestItemRepository implements MailDigestItemRepositoryInterface
{
    public function __construct(
        private TimezoneServiceInterface $timezone,
    ) {}

    public function add(UserId $userId, string $category, string $line, ?string $url, ?string $subjectType, ?string $subjectId, ?string $actorId = null): void
    {
        UserMailDigestItemModel::query()->create([
            'user_id' => $userId->value(),
            'category' => $category,
            'line' => $line,
            'url' => $url,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'actor_id' => $actorId,
        ]);
    }

    public function deleteForActor(string $actorId): void
    {
        UserMailDigestItemModel::query()->where('actor_id', $actorId)->delete();
    }

    public function deleteForSubject(string $type, string $id, ?string $category = null): int
    {
        return UserMailDigestItemModel::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->when($category !== null, static fn ($query) => $query->where('category', $category))
            ->delete();
    }

    public function userIdsWithItemsBefore(DateTimeInterface $before): array
    {
        /** @var list<string> */
        return UserMailDigestItemModel::query()
            ->where('created_at', '<', $this->bound($before))
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->all();
    }

    public function takeForUser(UserId $userId, DateTimeInterface $before, Closure $send): void
    {
        DB::transaction(function () use ($userId, $before, $send): void {
            // A second run at the same time waits here, then finds nothing left to send
            $rows = UserMailDigestItemModel::query()
                ->where('user_id', $userId->value())
                ->where('created_at', '<', $this->bound($before))
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return;
            }

            $send(array_values($rows->map(fn (UserMailDigestItemModel $row): MailDigestItem => new MailDigestItem(
                id: $row->id,
                userId: $row->user_id,
                category: $row->category,
                line: $row->line,
                url: $row->url,
                subjectType: $row->subject_type,
                subjectId: $row->subject_id,
                createdAt: DateTimeImmutable::createFromInterface($row->created_at),
            ))->all()));

            UserMailDigestItemModel::query()->whereIn('id', $rows->modelKeys())->delete();
        });
    }

    public function pruneBefore(DateTimeInterface $before): int
    {
        return UserMailDigestItemModel::query()->where('created_at', '<', $this->bound($before))->delete();
    }

    /**
     * Query bindings bypass the model's zone handling: compare in the storage zone.
     */
    private function bound(DateTimeInterface $instant): string
    {
        return $this->timezone->toStorage($instant)->format('Y-m-d H:i:s');
    }
}
