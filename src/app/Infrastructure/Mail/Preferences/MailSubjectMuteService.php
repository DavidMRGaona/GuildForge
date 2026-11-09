<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Domain\Mail\Repositories\MailSubjectMuteRepositoryInterface;
use App\Domain\Mail\ValueObjects\MutedMailSubject;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

final readonly class MailSubjectMuteService implements MailSubjectMuteServiceInterface
{
    public function __construct(
        private MailSubjectMuteRepositoryInterface $mutes,
    ) {}

    public function mutedFor(object $user): array
    {
        return $user instanceof UserModel ? $this->mutes->mutedFor(UserId::fromString($user->id)) : [];
    }

    public function isMuted(object $user, MailSubjectDTO $subject): bool
    {
        return $user instanceof UserModel
            && $this->mutes->isMuted(UserId::fromString($user->id), $subject->type, $subject->id);
    }

    public function mute(object $user, MailSubjectDTO $subject): void
    {
        if (! $user instanceof UserModel) {
            return;
        }

        $this->mutes->mute(UserId::fromString($user->id), $subject->type, $subject->id, $subject->label, $subject->typeLabel);
    }

    public function unmute(object $user, string $type, string $id): ?MutedMailSubject
    {
        return $user instanceof UserModel ? $this->mutes->unmute(UserId::fromString($user->id), $type, $id) : null;
    }

    public function forgetSubject(string $type, string $id): void
    {
        $this->mutes->forgetSubject($type, $id);
    }
}
