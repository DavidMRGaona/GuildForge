<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever can grant the roles that someone's roles that require association
 * membership are paused.
 */
final class InactiveRolesManagerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $personName,
        private readonly string $roles,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('authorization.inactive_roles.manager_subject', ['name' => $this->personName]))
            ->line(__('authorization.inactive_roles.manager_body', [
                'name' => $this->personName,
                'roles' => $this->roles,
            ]));
    }
}
