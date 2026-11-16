<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profiles;

use App\Application\Profiles\Services\ProfileEditorInterface;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Profiles\Exceptions\InvalidProfileHandleException;
use App\Domain\Profiles\Exceptions\ProfileHandleChangeTooSoonException;
use App\Domain\Profiles\Exceptions\ProfileHandleTakenException;
use App\Domain\Profiles\ProfileHandleAvailability;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profiles\UpdatePublicProfileRequest;
use App\Http\Requests\Profiles\UploadProfileBannerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The owner editing their public profile from the profile itself (core#743). Every action asks first
 * whether the person is the owner of the profile at that address (the form requests, or ownerId()
 * here) and answers anybody else with the 404 of an unknown address. Writing is the profile service's.
 */
final class PublicProfileEditController extends Controller
{
    public function update(
        UpdatePublicProfileRequest $request,
        UserProfileServiceInterface $profiles,
        TimezoneServiceInterface $timezone,
    ): RedirectResponse {
        $update = $request->toDto();

        try {
            $profiles->update($request->ownerId(), $update);
        } catch (InvalidProfileHandleException $exception) {
            throw ValidationException::withMessages(['handle' => $exception->reserved
                ? __('profiles.editor.errors.handle_unavailable')
                : __('profiles.editor.errors.handle_malformed')]);
        } catch (ProfileHandleTakenException) {
            throw ValidationException::withMessages(['handle' => __('profiles.editor.errors.handle_unavailable')]);
        } catch (ProfileHandleChangeTooSoonException $exception) {
            throw ValidationException::withMessages(['handle' => __('profiles.editor.errors.handle_too_soon', [
                'date' => $timezone->format($exception->availableAt, 'd/m/Y'),
            ])]);
        }

        return redirect()
            ->route('profiles.show', ['handle' => ProfileHandle::normalise($update->handle)])
            ->with('success', __('profiles.editor.saved'));
    }

    /**
     * Asked while the owner types an address: never says whose an unavailable one is. Read-only, so
     * a read-only impersonation may ask too.
     */
    public function availability(
        Request $request,
        string $handle,
        ProfileEditorInterface $editor,
        UserProfileServiceInterface $profiles,
    ): JsonResponse {
        $ownerId = $editor->ownerId($handle, $request->user());

        abort_if($ownerId === null, 404);

        $candidate = $request->query('direccion');
        $status = is_string($candidate) && mb_strlen($candidate) <= ProfileHandle::MAX_LENGTH * 2
            ? $profiles->handleAvailability($ownerId, $candidate)
            : ProfileHandleAvailability::Malformed;

        return response()
            ->json(['status' => $status->value])
            ->header('Cache-Control', 'no-store, private');
    }

    public function storeBanner(
        UploadProfileBannerRequest $request,
        UserProfileServiceInterface $profiles,
        LoggerInterface $log,
    ): RedirectResponse {
        try {
            $profiles->replaceBanner($request->ownerId(), $request->banner());
        } catch (Throwable $exception) {
            $log->error('[Profiles] A profile banner could not be stored.', [
                'user_id' => $request->ownerId(),
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages(['banner' => __('profiles.editor.errors.banner_failed')]);
        }

        return back()->with('success', __('profiles.editor.banner_saved'));
    }

    public function destroyBanner(
        Request $request,
        string $handle,
        ProfileEditorInterface $editor,
        UserProfileServiceInterface $profiles,
    ): RedirectResponse {
        $ownerId = $editor->ownerId($handle, $request->user());

        abort_if($ownerId === null, 404);

        $profiles->removeBanner($ownerId);

        return back()->with('success', __('profiles.editor.banner_removed'));
    }
}
