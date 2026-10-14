<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Filament\Resources\RoleResource;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        /** @var RoleModel $record */
        $record = $this->record;

        return [
            DeleteAction::make()
                ->hidden(fn (): bool => $record->is_protected)
                ->before(function () use ($record): void {
                    $this->refreshUsersOf($record);
                }),
        ];
    }

    protected function afterSave(): void
    {
        /** @var RoleModel $record */
        $record = $this->record;

        // The permissions checkbox list writes the pivot directly: every holder of the role is affected
        $this->refreshUsersOf($record);
    }

    private function refreshUsersOf(RoleModel $role): void
    {
        $authorization = app(AuthorizationServiceInterface::class);

        foreach ($role->users()->get() as $user) {
            $authorization->refreshUser($user);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
