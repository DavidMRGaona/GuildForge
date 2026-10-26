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

        $user = new UserModel($data);

        return $this->saveAccountWithRoles($user, $submittedRoles, static function () use ($user): UserModel {
            $user->save();

            return $user;
        });
    }

    protected function afterCreate(): void
    {
        /** @var UserModel $user */
        $user = $this->record;

        // Mark email as verified since admin created the user
        $user->markEmailAsVerified();

        $user->notify(new WelcomeNotification(app(SiteBrandingServiceInterface::class)->siteName()));
    }
}
