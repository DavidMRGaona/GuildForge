<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebPush;

use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Domain\SiteNotifications\ValueObjects\StoredSiteNotice;
use App\Domain\ValueObjects\UserId;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the service worker shows for a push message (core#803): the message carried the notice's
 * id only, so its texts are read here with the person's session and checked again. Another
 * account's notice, or a hidden one, answers 404 like an unknown id. Reading it marks nothing:
 * the notification opens `/avisos/{id}`, which marks it read as the bell does.
 */
final class WebPushNoticeController extends Controller
{
    public function __construct(
        private readonly SiteNotificationRepositoryInterface $notices,
    ) {}

    public function __invoke(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof UserModel, 403);

        $notice = $this->notices->findVisible(UserId::fromString((string) $user->id), $notification);
        abort_unless($notice instanceof StoredSiteNotice, 404);

        $response = response()->json([
            'title' => $notice->title,
            'body' => $notice->body,
            'url' => route('site-notifications.open', ['notification' => $notice->id], absolute: false),
            'tag' => 'notice-'.$notice->id,
        ]);

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
