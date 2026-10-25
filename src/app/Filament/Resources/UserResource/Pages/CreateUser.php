<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Filament\Resources\UserResource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\WelcomeNotification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        /** @var UserModel $user */
        $user = $this->record;

        // Mark email as verified since admin created the user
        $user->markEmailAsVerified();

        // After the roles checkbox list has synced the chosen roles, which would drop it otherwise
        app(MemberRoleServiceInterface::class)->assignTo($user);

        $user->notify(new WelcomeNotification(app(SiteBrandingServiceInterface::class)->siteName()));
    }
}
