<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Domain\Profiles\PublicName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChoosePublicNameRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Elige tu nombre público» (core#855), shown once to an account without a public name by
 * EnsurePublicNameIsChosen. Once it has one, the screen sends it on to the page it asked for.
 */
final class PublicNameController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->user($request);

        if (! PublicName::isMissing($user->display_name)) {
            return redirect()->intended(route('home'));
        }

        return Inertia::render('Auth/ChoosePublicName', [
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.choose_public_name'),
                noindex: true,
            ))->toArray(),
            // A suggestion only: the first word of the account name, never a surname
            'suggestion' => PublicName::suggestFrom($user->name),
        ]);
    }

    public function store(ChoosePublicNameRequest $request): RedirectResponse
    {
        $user = $this->user($request);

        if (! $this->authService->choosePublicName((string) $user->id, (string) $request->validated('display_name'))) {
            return redirect()->intended(route('home'));
        }

        return redirect()->intended(route('home'))
            ->with('success', __('auth.public_name.saved'));
    }

    private function user(Request $request): UserModel
    {
        /** @var UserModel $user the route requires a signed-in account */
        $user = $request->user();

        return $user;
    }
}
