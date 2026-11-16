<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Who may make a role require two-factor authentication (core#772): nobody who holds the role
 * (stored, paused or not) without having it on already, so nobody pauses their own access by
 * mistake. Turning the requirement off, or keeping it, is never refused here.
 */
final readonly class TwoFactorRequirementGuard
{
    public function __construct(
        private TwoFactorHolders $twoFactor,
    ) {}

    /**
     * @param  bool  $before  whether the role requires it now (false for a role not saved yet)
     * @param  bool  $after  whether it is being saved requiring it
     */
    public function refuses(object $actor, ?RoleModel $role, bool $before, bool $after): bool
    {
        if (! $after || $before || ! $role instanceof RoleModel || ! $role->exists) {
            return false;
        }

        $actorId = $actor instanceof UserModel ? (string) $actor->getKey() : null;

        // An actor that cannot be read is refused: the check never lets a mark through by accident
        if ($actorId === null || $actorId === '') {
            return true;
        }

        return $role->users()->whereKey($actorId)->exists() && ! $this->twoFactor->has($actorId);
    }
}
