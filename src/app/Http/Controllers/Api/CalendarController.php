<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Requests\Api\CalendarRequest;
use App\Http\Resources\CalendarEntryResource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarAggregatorServiceInterface $calendarAggregatorService,
        private readonly PersonalCalendarServiceInterface $personalCalendar,
        private readonly EmailVerificationPolicyInterface $emailVerification,
    ) {}

    public function index(CalendarRequest $request): JsonResponse
    {
        if ($request->onlyMine()) {
            return $this->mine($request);
        }

        $entries = $this->calendarAggregatorService->findByDateRange(
            $request->startDateTime(),
            $request->endDateTime(),
        );

        return response()->json(
            CalendarEntryResource::collection($entries)->resolve()
        );
    }

    /**
     * The signed-in person's own entries. The route is public, so the email
     * verification middleware does not cover it: the same rule is applied here,
     * with the same JSON answer.
     */
    private function mine(CalendarRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof UserModel) {
            return response()->json(
                ['message' => __('calendar.personal.sign_in_required')],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($this->emailVerification->mustVerify($user)) {
            return response()->json(
                [
                    'message' => __('auth.email_verification_required'),
                    'error' => EnsureEmailIsVerifiedWhenRequired::JSON_ERROR_CODE,
                ],
                Response::HTTP_FORBIDDEN,
                [EnsureEmailIsVerifiedWhenRequired::JSON_ERROR_HEADER => '1'],
            );
        }

        $entries = $this->personalCalendar->entriesFor(
            $user->id,
            $request->startDateTime(),
            $request->endDateTime(),
        );

        // Never kept by a shared cache: the answer depends on who asks
        return response()->json(
            CalendarEntryResource::collection($entries)->resolve(),
            Response::HTTP_OK,
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
