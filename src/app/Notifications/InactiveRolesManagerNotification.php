<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailDigestLine;
use App\Application\Mail\DTOs\MailDigestLineDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Filament\Resources\UserResource;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever can grant the roles that someone's roles that require association
 * membership are paused.
 */
final class InactiveRolesManagerNotification extends Notification implements HasMailCategory, HasMailDigestLine, HasSiteNotification, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $personName,
        private readonly string $roles,
        private readonly string $personId,
        /** @var list<string> Names of the roles that went inactive, to re-check the grant on delivery. */
        private readonly array $roleNames,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('authorization.inactive_roles.manager_subject', ['name' => $this->personName]))
            ->line(__('authorization.inactive_roles.manager_body', [
                'name' => EscapesMarkdown::escape($this->personName),
                'roles' => EscapesMarkdown::escape($this->roles),
            ]));
    }

    /**
     * Checked when it is delivered: the manager may have lost the right to grant those roles
     * while the notification waited in the queue.
     */
    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        $policy = app(RoleGrantPolicy::class);
        if ($this->roleNames === [] || ! array_all($this->roleNames, fn (string $name): bool => $policy->canGrant($notifiable, $name))) {
            return null;
        }

        return new SiteNotificationDTO(
            title: __('authorization.inactive_roles.manager_site_title', ['name' => $this->personName]),
            url: UserResource::getUrl('edit', ['record' => $this->personId], isAbsolute: false),
            body: __('authorization.inactive_roles.site_roles', ['roles' => $this->roles]),
            icon: 'shield-check',
            groupKey: 'core.inactive-roles:'.$this->personId,
            actorId: $this->personId,
        );
    }

    public function mailCategory(): string
    {
        return CoreMailCategories::ROLE_MANAGEMENT;
    }

    public function mailDigestLine(object $notifiable): MailDigestLineDTO
    {
        return new MailDigestLineDTO(__('authorization.inactive_roles.manager_digest_line', [
            'name' => $this->personName,
            'roles' => $this->roles,
        ]));
    }
}
