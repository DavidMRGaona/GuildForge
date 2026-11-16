<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Listeners;

use App\Application\Services\SiteBrandingServiceInterface;
use App\Domain\Events\TwoFactorRolesPaused;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\TwoFactorRolesPausedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the person (by mail and in the bell) that roles of theirs wait for two-factor
 * authentication (core#772). Only them: nobody else can turn it on for them, and the people who
 * manage roles see it in the panel (widget, filter). A mail that cannot be queued is logged.
 */
final readonly class NotifyTwoFactorRolesPaused
{
    public function __construct(
        private SiteBrandingServiceInterface $branding,
    ) {}

    public function handle(TwoFactorRolesPaused $event): void
    {
        if ($event->roleNames === []) {
            return;
        }

        $person = UserModel::query()->whereNull('anonymized_at')->find($event->userId);
        if (! $person instanceof UserModel) {
            return;
        }

        $roles = RoleModel::query()
            ->whereIn('name', $event->roleNames)
            ->orderBy('name')
            ->get()
            ->map(fn (RoleModel $role): string => $role->display_name ?? $role->name)
            ->implode(', ');

        try {
            $person->notify(new TwoFactorRolesPausedNotification($this->branding->siteName(), $roles));
        } catch (Throwable $exception) {
            Log::error('Could not queue the two-factor roles mail.', [
                'user_id' => $person->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
