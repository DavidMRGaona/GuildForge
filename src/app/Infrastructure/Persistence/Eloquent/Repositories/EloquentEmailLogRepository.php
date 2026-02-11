<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Mail\Entities\EmailLog;
use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use App\Domain\Mail\ValueObjects\EmailLogId;
use App\Infrastructure\Persistence\Eloquent\Models\EmailLogModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final readonly class EloquentEmailLogRepository implements EmailLogRepositoryInterface
{
    public function save(EmailLog $emailLog): void
    {
        EmailLogModel::query()->updateOrCreate(
            ['id' => $emailLog->id()->value],
            $this->toArray($emailLog),
        );
    }

    /**
     * @return Collection<int, EmailLog>
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return EmailLogModel::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EmailLogModel $model): EmailLog => $this->toDomain($model));
    }

    public function countByStatusSince(EmailStatus $status, \DateTimeInterface $since): int
    {
        return EmailLogModel::query()
            ->where('status', $status)
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * @return Collection<int, EmailLog>
     */
    public function getRecentFailed(int $limit = 10): Collection
    {
        return EmailLogModel::query()
            ->where('status', EmailStatus::Failed)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (EmailLogModel $model): EmailLog => $this->toDomain($model));
    }

    public function updateStatusByMessageId(string $messageId, EmailStatus $status): void
    {
        EmailLogModel::query()
            ->where('message_id', $messageId)
            ->update(['status' => $status->value]);
    }

    private function toDomain(EmailLogModel $model): EmailLog
    {
        return new EmailLog(
            id: new EmailLogId($model->id),
            recipient: $model->recipient,
            sender: $model->sender,
            subject: $model->subject,
            mailer: $model->mailer,
            status: $model->status,
            errorMessage: $model->error_message,
            messageId: $model->message_id,
            sentAt: $model->sent_at !== null
                ? new DateTimeImmutable($model->sent_at->toDateTimeString())
                : null,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(EmailLog $emailLog): array
    {
        return [
            'id' => $emailLog->id()->value,
            'recipient' => $emailLog->recipient(),
            'sender' => $emailLog->sender(),
            'subject' => $emailLog->subject(),
            'mailer' => $emailLog->mailer(),
            'status' => $emailLog->status()->value,
            'error_message' => $emailLog->errorMessage(),
            'message_id' => $emailLog->messageId(),
            'sent_at' => $emailLog->sentAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
