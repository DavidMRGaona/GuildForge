<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Mail\Repositories\MailPauseRepositoryInterface;
use App\Domain\Mail\ValueObjects\MailPause;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserMailPauseModel;
use DateTimeImmutable;
use DateTimeInterface;

final class EloquentMailPauseRepository implements MailPauseRepositoryInterface
{
    public function find(UserId $userId): ?MailPause
    {
        $row = UserMailPauseModel::query()->find($userId->value());

        return $row instanceof UserMailPauseModel ? $this->toValueObject($row) : null;
    }

    public function pause(UserId $userId, ?DateTimeInterface $until): MailPause
    {
        $row = UserMailPauseModel::query()->updateOrCreate(
            ['user_id' => $userId->value()],
            ['paused_until' => $until],
        );

        return $this->toValueObject($row);
    }

    public function resume(UserId $userId): void
    {
        UserMailPauseModel::query()->where('user_id', $userId->value())->delete();
    }

    private function toValueObject(UserMailPauseModel $row): MailPause
    {
        return new MailPause($row->paused_until === null ? null : DateTimeImmutable::createFromInterface($row->paused_until));
    }
}
