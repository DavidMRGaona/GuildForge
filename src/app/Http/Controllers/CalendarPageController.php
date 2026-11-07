<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CalendarPageController extends Controller
{
    public function __construct(
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly PersonalCalendarServiceInterface $personalCalendar,
        private readonly EmailVerificationPolicyInterface $emailVerification,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Calendar/Index', [
            'seo' => $this->seo->forPage(new SeoPageDTO(title: (string) __('seo.titles.calendar')))->toArray(),
            'personalCalendar' => $this->offersPersonalCalendar($request),
        ]);
    }

    /**
     * Whether to offer the "only mine" filter: signed in, allowed to act as the
     * signed-in user and with some module able to say what people attend.
     */
    private function offersPersonalCalendar(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && ! $this->emailVerification->mustVerify($user)
            && $this->personalCalendar->isAvailable();
    }
}
