<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateMailPreferencesRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Saves which optional mail the signed-in person receives (the «Correos» tab of the profile).
 * Like any signed-in route, it stays closed to accounts that must still verify their email.
 */
final class MailPreferencesController extends Controller
{
    public function __construct(
        private readonly MailPreferenceServiceInterface $preferences,
    ) {}

    public function update(UpdateMailPreferencesRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        try {
            $this->preferences->update($user, $request->preferences());
        } catch (MailPreferenceNotEditableException $e) {
            throw ValidationException::withMessages([
                'preferences.'.$e->categoryKey => __('auth.mail_preferences_not_editable'),
            ]);
        }

        return back()->with('success', __('auth.mail_preferences_saved'));
    }
}
