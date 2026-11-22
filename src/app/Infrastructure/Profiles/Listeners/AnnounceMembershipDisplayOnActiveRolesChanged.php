<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Listeners;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Domain\Events\UserActiveRolesChanged;
use App\Infrastructure\Profiles\Services\ProfileMembershipDisplayAnnouncer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The association member role turns on or off for an account without being granted or taken away (core#824):
 * the account turned two-factor authentication on or off, or had it reset, while the role requires it. The
 * role editor does not let the association role require it, so this only covers one marked by other means.
 * Other roles turning on or off change nothing. It comes after the commit, so it dispatches at once; a
 * failure is logged and never stops the other listeners of the event.
 */
final readonly class AnnounceMembershipDisplayOnActiveRolesChanged
{
    public function __construct(
        private AssociationMembershipServiceInterface $membership,
        private ProfileMembershipDisplayAnnouncer $announcer,
    ) {}

    public function handle(UserActiveRolesChanged $event): void
    {
        try {
            $role = $this->membership->roleName();

            if ($role !== null && (in_array($role, $event->rolesActivated, true) || in_array($role, $event->rolesPaused, true))) {
                $this->announcer->changed($event->userId);
            }
        } catch (Throwable $exception) {
            Log::warning('[Profiles] A change of active roles could not be announced to the membership display.', [
                'user_id' => $event->userId,
                'exception' => $exception::class,
            ]);
        }
    }
}
