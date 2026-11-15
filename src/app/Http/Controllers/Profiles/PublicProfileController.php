<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profiles;

use App\Application\Profiles\DTOs\ProfileEditorDTO;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\Services\ProfileEditorInterface;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\PublicProfileServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Domain\Profiles\ProfileVisibility;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public profile page, `/miembros/{handle}` (core#741). Whoever may not see it gets the 404 an
 * unknown address gets; an old address moves to the current one only for whoever may see it. What is
 * drawn (header, stats, the sections of each region) comes from ProfileRendererInterface (core#742),
 * asked only after the access rule let this person see the profile. The owner also gets what their
 * editor starts from (core#743); nobody else ever does.
 */
final class PublicProfileController extends Controller
{
    public function __construct(
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    /**
     * The lookup and the renderer share the request's profile rows (bound scoped): they are resolved per
     * call, never kept by a controller instance the route may reuse.
     */
    public function show(
        Request $request,
        string $handle,
        PublicProfileServiceInterface $profiles,
        ProfileRendererInterface $renderer,
        ProfileEditorInterface $editor,
    ): Response|RedirectResponse {
        $lookup = $profiles->findByHandle($handle, $request->user());

        if ($lookup->movedTo !== null) {
            // Whether it moves depends on who asks: never kept by a shared cache
            return redirect()->route('profiles.show', ['handle' => $lookup->movedTo], 301)
                ->header('Cache-Control', 'no-store, private');
        }

        $profile = $lookup->profile;

        abort_unless($profile instanceof PublicProfileDTO, 404);

        // Only now, once the access rule let this person see it: the renderer never decides that
        $resolved = $renderer->render(
            new ProfileOwnerDTO($profile->userId, $profile->handle, $profile->shownPublicName()),
            $request->user(),
        );

        return Inertia::render('Profiles/Show', [
            'profile' => $resolved->toArray(),
            'notices' => [
                'choosePublicName' => $lookup->isOwner && ! $profile->hasPublicName(),
                // Both when both hold: hidden, and nobody else could see it even if it were not
                'onlyOwner' => $lookup->isOwner && $profile->visibility === ProfileVisibility::Hidden,
            ],
            // Where the owner sets their public name: the account page
            'accountUrl' => $lookup->isOwner ? route('profile.show') : null,
            'editor' => $lookup->isOwner ? $this->editorFor($editor, $request, $profile->handle) : null,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: $profile->shownPublicName()
                    ?? (string) __('seo.titles.public_profile', ['handle' => $profile->handle]),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Asked again through ownerId(), the same rule the saving routes ask, so the editor is never drawn
     * for somebody they would refuse.
     *
     * @return array<string, mixed>|null
     */
    private function editorFor(ProfileEditorInterface $editor, Request $request, string $handle): ?array
    {
        $ownerId = $editor->ownerId($handle, $request->user());
        $data = $ownerId !== null ? $editor->editorFor($ownerId) : null;

        if (! $data instanceof ProfileEditorDTO) {
            return null;
        }

        return [
            ...$data->toArray(),
            'urls' => [
                'update' => route('profiles.update', ['handle' => $handle]),
                'availability' => route('profiles.handle-availability', ['handle' => $handle]),
                'banner' => route('profiles.banner.store', ['handle' => $handle]),
            ],
        ];
    }
}
