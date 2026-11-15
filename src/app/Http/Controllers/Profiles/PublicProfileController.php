<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profiles;

use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\Services\PublicProfileServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Profiles\ProfileVisibility;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public profile page, `/miembros/{handle}` (core#741). Whoever may not see it gets the 404 an
 * unknown address gets; an old address moves to the current one only for whoever may see it.
 */
final class PublicProfileController extends Controller
{
    public function __construct(
        private readonly PublicProfileServiceInterface $profiles,
        private readonly TimezoneServiceInterface $timezone,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function show(Request $request, string $handle): Response|RedirectResponse
    {
        $lookup = $this->profiles->findByHandle($handle, $request->user());

        if ($lookup->movedTo !== null) {
            return redirect()->route('profiles.show', ['handle' => $lookup->movedTo], 301);
        }

        $profile = $lookup->profile;

        abort_unless($profile instanceof PublicProfileDTO, 404);

        return Inertia::render('Profiles/Show', [
            'profile' => [
                'handle' => $profile->handle,
                'url' => route('profiles.show', ['handle' => $profile->handle]),
                // Never the account name: without a public name only the owner sees the page
                'publicName' => $profile->hasPublicName() ? trim((string) $profile->publicName) : null,
                'avatarPublicId' => $profile->avatarPublicId,
                'bannerPublicId' => $profile->bannerPublicId,
                'membershipBadge' => $lookup->membershipBadge,
                // The month people joined, as the display zone tells it (`2024-06`)
                'memberSince' => $profile->memberSince instanceof \DateTimeImmutable ? $this->timezone->format($profile->memberSince, 'Y-m') : null,
            ],
            'isOwner' => $lookup->isOwner,
            'notices' => [
                'choosePublicName' => $lookup->isOwner && ! $profile->hasPublicName(),
                'onlyOwner' => $lookup->isOwner && $profile->hasPublicName() && $profile->visibility === ProfileVisibility::Hidden,
            ],
            'editUrl' => $lookup->isOwner ? route('profile.show') : null,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: $profile->hasPublicName()
                    ? trim((string) $profile->publicName)
                    : (string) __('seo.titles.public_profile', ['handle' => $profile->handle]),
                noindex: true,
            ))->toArray(),
        ]);
    }
}
