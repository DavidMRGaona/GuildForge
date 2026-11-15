<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Application\Services\SiteBrandingServiceInterface;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Concerns\SavesRolesThroughTheGrantRule;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\WelcomeNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateUser extends CreateRecord
{
    use SavesRolesThroughTheGrantRule;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        [$data, $submittedRoles] = $this->splitSubmittedRoles($data);

        // Whoever creates the account has not proved the address is the person's: it only counts
        // as verified when they say so, on purpose, and that is logged
        $markVerified = ($data[UserResource::MARK_EMAIL_VERIFIED_FIELD] ?? false) === true;
        unset($data[UserResource::MARK_EMAIL_VERIFIED_FIELD]);

        $user = new UserModel($data);

        return $this->saveAccountWithRoles($user, $submittedRoles, static function () use ($user, $markVerified): UserModel {
            if ($markVerified) {
                // Not mass assignable on purpose, like markEmailAsVerified()
                $user->forceFill(['email_verified_at' => $user->freshTimestamp()]);
            }

            $user->save();

            return $user;
        });
    }

    protected function afterCreate(): void
    {
        /** @var UserModel $user */
        $user = $this->record;

        $user->notify(new WelcomeNotification(app(SiteBrandingServiceInterface::class)->siteName()));

        if ($user->hasVerifiedEmail()) {
            // Ids only: the address is personal data and stays out of the log
            Log::info('Account email marked as verified from the panel', [
                'actor_id' => (string) Auth::id(),
                'user_id' => (string) $user->getKey(),
            ]);

            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
