<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Mail\Repositories\MailSubjectMuteRepositoryInterface;
use App\Domain\Mail\ValueObjects\MutedMailSubject;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserMailSubjectMuteModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentMailSubjectMuteRepository implements MailSubjectMuteRepositoryInterface
{
    public function mutedFor(UserId $userId): array
    {
        $rows = UserMailSubjectMuteModel::query()
            ->where('user_id', $userId->value())
            ->orderByDesc('created_at')
            ->orderBy('label')
            ->get()
            ->all();

        return array_values(array_map($this->toValueObject(...), $rows));
    }

    public function isMuted(UserId $userId, string $type, string $id): bool
    {
        return $this->subject($userId, $type, $id)->exists();
    }

    public function mute(UserId $userId, string $type, string $id, string $label, ?string $typeLabel): void
    {
        UserMailSubjectMuteModel::query()->updateOrCreate(
            ['user_id' => $userId->value(), 'subject_type' => $type, 'subject_id' => $id],
            ['label' => $label, 'type_label' => $typeLabel],
        );
    }

    public function unmute(UserId $userId, string $type, string $id): ?MutedMailSubject
    {
        $row = $this->subject($userId, $type, $id)->first();

        if (! $row instanceof UserMailSubjectMuteModel) {
            return null;
        }

        $row->delete();

        return $this->toValueObject($row);
    }

    public function forgetSubject(string $type, string $id): void
    {
        UserMailSubjectMuteModel::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->delete();
    }

    /**
     * @return Builder<UserMailSubjectMuteModel>
     */
    private function subject(UserId $userId, string $type, string $id): Builder
    {
        return UserMailSubjectMuteModel::query()
            ->where('user_id', $userId->value())
            ->where('subject_type', $type)
            ->where('subject_id', $id);
    }

    private function toValueObject(UserMailSubjectMuteModel $row): MutedMailSubject
    {
        return new MutedMailSubject(
            type: $row->subject_type,
            id: $row->subject_id,
            label: $row->label,
            typeLabel: $row->type_label,
            mutedAt: $row->created_at === null ? new DateTimeImmutable : DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}
