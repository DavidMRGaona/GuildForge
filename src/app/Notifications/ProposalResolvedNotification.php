<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the author what became of their proposal: approved, returned or rejected, with the
 * reviewer's reason in the last two (core#577).
 */
final class ProposalResolvedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;

    /** @param  'approved'|'returned'|'rejected'  $outcome */
    public function __construct(
        private readonly ProposalType $type,
        private readonly string $proposalId,
        private readonly string $title,
        private readonly string $outcome,
        private readonly ?string $reason = null,
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
        $mail = (new MailMessage)
            ->subject(__("proposals.notices.status.{$this->outcome}.subject", $this->replacements()))
            ->line(__("proposals.notices.status.{$this->outcome}.body", [
                'type' => $this->typeName(),
                'title' => EscapesMarkdown::escape($this->title),
            ]));

        if ($this->reason !== null) {
            $mail->line(__('proposals.notices.reason', ['reason' => EscapesMarkdown::escape($this->reason)]));
        }

        return $mail->action(__('proposals.notices.status_action'), url($this->path()));
    }

    public function toSiteNotification(object $notifiable): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: __("proposals.notices.status.{$this->outcome}.site_title"),
            url: $this->path(),
            body: $this->reason !== null
                ? __('proposals.notices.reason', ['reason' => $this->reason])
                : $this->title,
            icon: 'clipboard-check',
            groupKey: 'core.proposal:'.$this->proposalId,
        );
    }

    public function mailCategory(): string
    {
        return CoreMailCategories::PROPOSALS_STATUS;
    }

    public function mailSubject(): MailSubjectDTO
    {
        return new MailSubjectDTO('core.proposal', $this->proposalId, $this->title, 'proposals.notices.subject_type');
    }

    /**
     * The «Mis propuestas» tab of the profile (core#579).
     */
    private function path(): string
    {
        return route('profile.show', ['tab' => 'proposals'], absolute: false);
    }

    /**
     * @return array{type: string, title: string}
     */
    private function replacements(): array
    {
        return ['type' => $this->typeName(), 'title' => $this->title];
    }

    private function typeName(): string
    {
        return __('proposals.notices.type_label.'.$this->type->value);
    }
}
