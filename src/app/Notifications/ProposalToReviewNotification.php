<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailDigestLine;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailDigestLineDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever can review a type of proposal that one is waiting (core#577). Checked again when
 * it is delivered: the person may have lost the review permission, or the proposal may have been
 * reviewed or withdrawn, while the notice waited in the queue.
 */
final class ProposalToReviewNotification extends Notification implements HasMailCategory, HasMailDigestLine, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProposalType $type,
        private readonly string $proposalId,
        private readonly string $title,
        private readonly string $authorName,
        private readonly string $authorId,
        /** @var list<string> one line per module request of an event («Inscripciones: aforo de 20»), core#581 */
        private readonly array $requestLines = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    /**
     * Applies to the mail and to the bell alike.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! app(AuthorizationServiceInterface::class)->can($notifiable, $this->type->reviewPermission())) {
            return false;
        }

        $proposal = match ($this->type) {
            ProposalType::Event => EventModel::query()->includingUnsubmittedProposals()->find($this->proposalId),
            ProposalType::Article => ArticleModel::query()->includingUnsubmittedProposals()->find($this->proposalId),
        };

        return $proposal !== null && $proposal->proposal_status === ProposalStatus::PendingReview;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('proposals.notices.review_subject', ['type' => $this->typeName(), 'title' => $this->title]))
            ->line(__('proposals.notices.review_body', [
                'author' => EscapesMarkdown::escape($this->authorName),
                'type' => $this->typeName(),
                'title' => EscapesMarkdown::escape($this->title),
            ]));

        if ($this->requestLines !== []) {
            // The lines hold what the author typed: escaped, as every user value in a mail
            $mail->line(__('proposals.notices.review_requests'));

            foreach ($this->requestLines as $line) {
                $mail->line('- '.EscapesMarkdown::escape($line));
            }
        }

        return $mail->action(__('proposals.notices.review_action'), url($this->panelPath()));
    }

    public function toSiteNotification(object $notifiable): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: __('proposals.notices.review_site_title', ['type' => $this->typeName()]),
            url: $this->panelPath(),
            body: __('proposals.notices.review_site_body', ['title' => $this->title, 'author' => $this->authorName]),
            icon: 'clipboard-check',
            groupKey: 'core.proposal:'.$this->proposalId,
            actorId: $this->authorId,
        );
    }

    public function mailCategory(): string
    {
        return CoreMailCategories::PROPOSALS_REVIEW;
    }

    public function mailSubject(): MailSubjectDTO
    {
        return new MailSubjectDTO('core.proposal', $this->proposalId, $this->title, 'proposals.notices.subject_type');
    }

    public function mailDigestLine(object $notifiable): MailDigestLineDTO
    {
        return new MailDigestLineDTO(
            __('proposals.notices.review_digest_line', [
                'author' => $this->authorName,
                'type' => $this->typeName(),
                'title' => $this->title,
            ]),
            url($this->panelPath()),
            $this->authorId,
        );
    }

    private function typeName(): string
    {
        return __('proposals.notices.type_label.'.$this->type->value);
    }

    private function panelPath(): string
    {
        return match ($this->type) {
            ProposalType::Event => EventResource::getUrl('edit', ['record' => $this->proposalId], isAbsolute: false),
            ProposalType::Article => ArticleResource::getUrl('edit', ['record' => $this->proposalId], isAbsolute: false),
        };
    }
}
