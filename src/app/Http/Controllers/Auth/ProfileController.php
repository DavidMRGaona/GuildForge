<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\DTOs\UpdateUserDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly ResponseDTOFactoryInterface $dtoFactory,
    ) {
    }

    public function show(Request $request): Response
    {
        /** @var UserModel $user */
        $user = $request->user();

        return Inertia::render('Profile/Show', [
            'user' => $this->dtoFactory->createUserDTO($user),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $avatarPublicId = null;

        if ($request->hasFile('avatar')) {
            /** @var UploadedFile $file */
            $file = $request->file('avatar');

            $avatarPublicId = $this->authService->uploadAvatar(
                (string) $user->id,
                $file->getContent(),
                $file->getMimeType() ?? 'image/jpeg'
            );
        }

        /** @var array{name: string, email: string, display_name?: string|null} $validated */
        $validated = $request->validated();

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
