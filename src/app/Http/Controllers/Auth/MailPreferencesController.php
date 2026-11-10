<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateMailPreferencesRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves which optional mail the signed-in person receives (the «Avisos» tab of the profile), and
 * which notices they see in the notification centre (core#491). Both or nothing.
 * Like any signed-in route, it stays closed to accounts that must still verify their email.
 */
final class MailPreferencesController extends Controller
{
    public function __construct(
        private readonly MailPreferenceServiceInterface $preferences,
        private readonly SiteNotificationPreferenceServiceInterface $sitePreferences,
    ) {}

    public function update(UpdateMailPreferencesRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $field = 'preferences';

        try {
            DB::transaction(function () use ($user, $request, &$field): void {
                $this->preferences->update($user, $request->preferences());
                $field = 'site';
                $this->sitePreferences->update($user, $request->siteSwitches());
            });
        } catch (MailPreferenceNotEditableException $e) {
            throw ValidationException::withMessages([
                $field.'.'.$e->categoryKey => __('auth.mail_preferences_not_editable'),
            ]);
        }

        return back()->with('success', __('auth.mail_preferences_saved'));
    }
}
