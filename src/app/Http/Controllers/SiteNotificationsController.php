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
 * The notification centre of the signed-in account (core#491): the bell's counters and latest
 * notices (JSON, light enough to ask every minute), the «Avisos» page, opening a notice,
 * marking everything read and, apart from reading, marking the notices seen (core#800). Like every signed-in route, closed to accounts that must still verify
 * their email.
 */
final class SiteNotificationsController extends Controller
{
    use BuildsPaginatedResponse;

    public const int PER_PAGE = 20;

    /** Rows in the bell's list. */
    public const int RECENT = 8;

    /** Value of `?filtro=` that lists only the unread notices on the page. */
    public const string UNREAD_FILTER = 'sin-leer';

    public function __construct(
        private readonly SiteNotificationServiceInterface $notices,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        // Looking at the page is looking at the notices (core#800): the bell it shows says 0
        $this->notices->markSeen($user);
        $page = $this->getCurrentPage();
        // «Sin leer» (core#671); anything else lists everything
        $onlyUnread = $request->query('filtro') === self::UNREAD_FILTER;

        return Inertia::render('Notifications/Index', [
            'filter' => $onlyUnread ? 'unread' : 'all',
            'notifications' => $this->buildPaginatedResponse(
                items: $this->notices->latest($user, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $onlyUnread),
                total: $this->notices->total($user, $onlyUnread),
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
        return response()->json($this->counts($this->user($request)));
    }

    /**
     * The bell opened (core#800): its number goes to 0, the notices stay unread. Asked before the
     * list is loaded, so whatever arrives meanwhile is in the list and still counts.
     */
    public function seen(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->notices->markSeen($user);

        return response()->json($this->counts($user));
    }

    public function recent(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            ...$this->counts($user),
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
     * The bell gets the new counters as JSON; the «Avisos» page goes back where it was.
     */
    private function answer(Request $request, UserModel $user): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($this->counts($user));
        }

        return back();
    }

    /**
     * `unread` is what is still unread (the «Marcar todo como leído» button, the page);
     * `unseen` is the bell's number and the tab title's (core#800).
     *
     * @return array{unread: int, unseen: int}
     */
    private function counts(UserModel $user): array
    {
        return [
            'unread' => $this->notices->unreadCount($user),
            'unseen' => $this->notices->unseenCount($user),
        ];
    }

    private function user(Request $request): UserModel
    {
        $user = $request->user();
        abort_unless($user instanceof UserModel, 403);

        return $user;
    }
}
