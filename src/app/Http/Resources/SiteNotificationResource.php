<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\SiteNotifications\DTOs\SiteNotificationItemDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the notification centre for the bell and the «Avisos» page. Its link goes through
 * the core (`/avisos/{id}`), which marks it read and then sends the person where it leads.
 *
 * @property SiteNotificationItemDTO $resource
 */
final class SiteNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $notice = $this->resource;

        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'body' => $notice->body,
            'icon' => $notice->icon,
            'count' => $notice->count,
            'unread' => $notice->unread,
            'notifiedAt' => $notice->notifiedAt,
            'href' => route('site-notifications.open', ['notification' => $notice->id], absolute: false),
        ];
    }

    /**
     * @param  list<SiteNotificationItemDTO>  $notices
     * @return list<array<string, mixed>>
     */
    public static function rows(array $notices): array
    {
        /** @var list<array<string, mixed>> */
        return self::collection($notices)->resolve();
    }
}
