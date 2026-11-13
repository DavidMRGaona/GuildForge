<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Application\DTOs\UpdateUserDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Mail\Services\MailDigestServiceInterface;
use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Proposals\DTOs\OwnProposalDTO;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalQueryServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Mail\ValueObjects\MailPause;
use App\Domain\Mail\ValueObjects\MutedMailSubject;
use App\Domain\Proposals\Enums\ProposalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\OwnProposalResource;
use App\Infrastructure\Mail\Preferences\MailSubjectTypeLabel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    /** Profile tab id of the mail preferences; the same as `MAIL_TAB` in useProfileTabs.ts. */
    private const string MAIL_TAB = 'mail';

    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly ResponseDTOFactoryInterface $dtoFactory,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly PersonalCalendarServiceInterface $personalCalendar,
        private readonly CalendarFeedTokenServiceInterface $calendarFeedTokens,
        private readonly TimezoneServiceInterface $timezone,
        private readonly MailPreferenceServiceInterface $mailPreferences,
        private readonly MailPauseServiceInterface $mailPause,
        private readonly MailSubjectMuteServiceInterface $mailSubjectMutes,
        private readonly MailDigestServiceInterface $mailDigests,
        private readonly ProposalQueryServiceInterface $proposalQueries,
        private readonly ProposalEligibilityServiceInterface $proposalAccess,
    ) {}

    public function show(Request $request): Response
    {
        /** @var UserModel $user */
        $user = $request->user();

        $props = [
            'user' => $this->dtoFactory->createUserDTO($user),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.profile'),
                noindex: true,
            ))->toArray(),
            // Whether the address exists, never the address itself (only its hash is stored)
            'calendarFeed' => [
                'available' => $this->personalCalendar->isAvailable(),
                'issuedAt' => $this->timezone->iso($this->calendarFeedTokens->issuedAt((string) $user->id)),
            ],
            'proposals' => $this->proposalsFor($user),
        ];

        // Only for the «Avisos» tab (`?tab=mail`): listing the categories evaluates each one's
        // audience (permissions), which the other tabs never need
        if ($request->query('tab') === self::MAIL_TAB) {
            $pause = $this->mailPause->current($user);
            $props['mailPreferences'] = [
                'sections' => $this->mailPreferences->sectionsFor($user),
                'pause' => [
                    'active' => $pause instanceof MailPause,
                    // Null while paused means «until the person resumes»
                    'until' => $this->timezone->iso($pause?->until),
                    // Last day the pause covers, as the person sees it: the end is where the next day starts
                    'lastDay' => $pause?->until instanceof \DateTimeImmutable ? $this->timezone->format($pause->until->modify('-1 second'), 'Y-m-d') : null,
                ],
                // Things muted from a mail footer (core#334), with the names stored when muted
                'mutedSubjects' => array_map(fn (MutedMailSubject $muted): array => [
                    'type' => $muted->type,
                    'id' => $muted->id,
                    'label' => $muted->label,
                    'typeLabel' => MailSubjectTypeLabel::translate($muted->typeLabel),
                    'mutedAt' => $this->timezone->iso($muted->mutedAt),
                ], $this->mailSubjectMutes->mutedFor($user)),
                // When the daily digest goes out (core#333), display zone
                'digestTime' => $this->mailDigests->time(),
            ];
        }

        return Inertia::render('Profile/Show', $props);
    }

    /**
     * «Mis propuestas» (core#579): the person's proposals, what they may propose and how much of
     * the limits they have used. The tab shows when they may propose or already have proposals.
     *
     * @return array<string, mixed>
     */
    private function proposalsFor(UserModel $user): array
    {
        $userId = (string) $user->id;
        $items = array_map(
            static fn (OwnProposalDTO $proposal): array => OwnProposalResource::make($proposal)->resolve(),
            $this->proposalQueries->ownedBy($userId),
        );
        $canPropose = [
            'event' => $this->proposalAccess->check(ProposalType::Event, $userId)->allowed,
            'article' => $this->proposalAccess->check(ProposalType::Article, $userId)->allowed,
        ];
        $limits = $this->proposalQueries->limitsOf($userId);

        return [
            'available' => $items !== [] || $canPropose['event'] || $canPropose['article'],
            'canPropose' => $canPropose,
            'items' => $items,
            'limits' => [
                'pendingUsed' => $limits->pendingUsed,
                'pendingMax' => $limits->pendingMax,
                'draftsUsed' => $limits->draftsUsed,
                'draftsMax' => $limits->draftsMax,
                'submissionsLeft' => $limits->submissionsLeftThisHour,
                'submissionsPerHour' => $limits->submissionsPerHour,
            ],
        ];
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $avatarPublicId = null;

        if ($request->hasFile('avatar')) {
            $avatarPublicId = $this->authService->uploadAvatarFromFile(
                (string) $user->id,
                $request->file('avatar')
            );
        }

        /** @var array{name?: string, email?: string, display_name?: string|null} $validated */
        $validated = $request->validated();

        if (! isset($validated['name'], $validated['email'])) {
            if ($avatarPublicId !== null) {
                $this->authService->updateAvatar((string) $user->id, $avatarPublicId);
            }

            return back()->with('success', __('auth.profile_updated'));
        }

        $dto = UpdateUserDTO::fromArray([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'email' => $validated['email'],
            'avatar_public_id' => $avatarPublicId,
        ]);

        $this->authService->updateProfile((string) $user->id, $dto);

        return back()->with('success', __('auth.profile_updated'));
    }

    public function changePassword(ChangePasswordRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $result = $this->authService->changePassword(
            (string) $user->id,
            $request->validated('current_password'),
            $request->validated('password'),
        );

        if (! $result) {
            return back()->with('error', __('auth.password_incorrect'));
        }

        return back()->with('success', __('auth.password_changed'));
    }
}
