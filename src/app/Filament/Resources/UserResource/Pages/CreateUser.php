<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

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

        $appName = (string) config('app.name', 'Runesword');
        $user->notify(new WelcomeNotification($appName));
    }
}
