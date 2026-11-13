<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNews\DTOs\SiteNewsItemDTO;
use App\Application\SiteNews\Services\SiteNewsReaderInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use App\Domain\SiteNews\SiteNewsKind;
use App\Infrastructure\Mail\Preferences\CoreMailCategories;
use Illuminate\Notifications\Notification;

/**
 * «Nuevo evento: X», on the site only (core#613). The content is read when the notice is
 * delivered: taken down or deleted by then, nothing is shown. Unread news of a person become one
 * row («3 novedades en el sitio»). Sent in batches by DeliverSiteNewsJob, which is already
 * queued, so the notification itself is not; one instance reads the content once for its batch.
 */
final class SiteNewsNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification
{
    public const string GROUP_KEY = 'core.news';

    private ?SiteNewsItemDTO $item = null;

    private bool $read = false;

    public function __construct(
        public readonly SiteNewsKind $kind,
        public readonly string $contentId,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [HasSiteNotification::CHANNEL];
    }

    public function mailCategory(): string
    {
        return CoreMailCategories::SITE_NEWS;
    }

    /**
     * Ties the notice to its content, so deleting the content forgets it.
     */
    public function mailSubject(): ?MailSubjectDTO
    {
        $item = $this->item();

        return $item instanceof SiteNewsItemDTO
            ? new MailSubjectDTO($this->kind->subjectType(), $this->contentId, $item->title, 'site_news.subjects.'.$this->kind->value)
            : null;
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        $item = $this->item();

        if (! $item instanceof SiteNewsItemDTO) {
            return null;
        }

        return new SiteNotificationDTO(
            title: __('site_news.titles.'.$this->kind->value, ['title' => $item->title]),
            url: $item->url,
            body: $item->summary,
            icon: $this->kind->icon(),
            groupKey: self::GROUP_KEY,
            groupedTitle: static fn (SiteNotificationGroupDTO $group): string => trans_choice(
                'site_news.grouped',
                $group->count,
                ['count' => $group->count],
            ),
        );
    }

    private function item(): ?SiteNewsItemDTO
    {
        if (! $this->read) {
            $this->item = app(SiteNewsReaderInterface::class)->find($this->kind, $this->contentId);
            $this->read = true;
        }

        return $this->item;
    }
}
