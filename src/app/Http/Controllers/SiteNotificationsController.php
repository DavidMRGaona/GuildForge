<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Resources\SiteNotificationResource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notification centre of the signed-in account (core#491): the bell's counter and latest
 * notices (JSON, light enough to ask every minute), the «Avisos» page, opening a notice and
 * marking everything read. Like every signed-in route, closed to accounts that must still verify
 * their email.
 */
final class SiteNotificationsController extends Controller
{
    use BuildsPaginatedResponse;

    public const int PER_PAGE = 20;

    /** Rows in the bell's list. */
    public const int RECENT = 8;

    public function __construct(
        private readonly SiteNotificationServiceInterface $notices,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $page = $this->getCurrentPage();

        return Inertia::render('Notifications/Index', [
            'notifications' => $this->buildPaginatedResponse(
                items: $this->notices->latest($user, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
                total: $this->notices->total($user),
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: SiteNotificationResource::class,
            ),
            'unread' => $this->notices->unreadCount($user),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.notifications'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread' => $this->notices->unreadCount($this->user($request))]);
    }

    public function recent(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'unread' => $this->notices->unreadCount($user),
            'items' => SiteNotificationResource::rows($this->notices->latest($user, self::RECENT)),
        ]);
    }

    /**
     * Mark the notice read and go where it leads. A notice of someone else is not found.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $url = $this->notices->open($this->user($request), $notification);

        abort_if($url === null, 404);

        return redirect()->to($url);
    }

    public function markRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->notices->markRead($user, $notification), 404);

        return $this->answer($request, $user);
    }

    public function markUnread(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->notices->markUnread($user, $notification), 404);

        return $this->answer($request, $user);
    }

    /**
     * Hide the notice for the person (core#670). A notice of someone else is not found.
     */
    public function hide(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->notices->hide($user, $notification), 404);

        return $this->answer($request, $user);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->user($request);
        $this->notices->markAllRead($user);

        return $this->answer($request, $user);
    }

    /**
     * The bell gets the new counter as JSON; the «Avisos» page goes back where it was.
     */
    private function answer(Request $request, UserModel $user): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['unread' => $this->notices->unreadCount($user)]);
        }

        return back();
    }

    private function user(Request $request): UserModel
    {
        $user = $request->user();
        abort_unless($user instanceof UserModel, 403);

        return $user;
    }
}
