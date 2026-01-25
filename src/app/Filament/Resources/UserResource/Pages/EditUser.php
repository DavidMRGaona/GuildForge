<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\UserServiceInterface;
use App\Filament\Resources\UserResource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        /** @var UserModel $record */
        $record = $this->record;

        return [
            Action::make('deactivate')
                ->label(__('filament.users.actions.deactivate'))
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->visible(fn (): bool => ! $record->trashed() && $record->id !== Auth::id())
                ->requiresConfirmation()
                ->modalHeading(__('filament.users.actions.deactivate'))
                ->modalDescription(__('filament.users.actions.deactivateConfirm'))
                ->modalIcon('heroicon-o-no-symbol')
                ->modalIconColor('warning')
                ->action(function () use ($record): void {
                    $record->delete();
                    Notification::make()
                        ->title(__('filament.users.actions.deactivated'))
                        ->success()
                        ->send();
                    $this->redirect(UserResource::getUrl('index'));
                }),
            RestoreAction::make()
                ->label(__('filament.users.actions.restore'))
                ->visible(fn (): bool => $record->trashed() && ! $record->isAnonymized())
                ->modalHeading(__('filament.users.actions.restore'))
                ->modalDescription(__('filament.users.actions.restoreConfirm'))
                ->successNotificationTitle(__('filament.users.actions.restored')),
            Action::make('anonymize')
                ->label(__('filament.users.actions.anonymize'))
                ->icon('heroicon-o-eye-slash')
                ->color('danger')
                ->visible(fn (): bool => $record->trashed() && ! $record->isAnonymized() && $record->id !== Auth::id())
                ->modalHeading(__('filament.users.actions.anonymizeModal.heading', ['name' => $record->display_name ?? $record->name]))
                ->modalDescription(function () use ($record): string {
                    $userService = app(UserServiceInterface::class);
                    $contentCounts = $userService->countUserContent($record->id);
                    $articlesCount = $contentCounts['articles'];

                    if ($articlesCount === 0) {
                        return __('filament.users.actions.anonymizeModal.noContent');
                    }

                    $contentText = trans_choice('filament.users.actions.anonymizeModal.articlesCount', $articlesCount, ['count' => $articlesCount]);

                    return __('filament.users.actions.anonymizeModal.hasContent', ['content' => $contentText]);
                })
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->form(function () use ($record): array {
                    $userService = app(UserServiceInterface::class);
                    $contentCounts = $userService->countUserContent($record->id);
                    $articlesCount = $contentCounts['articles'];

                    if ($articlesCount === 0) {
                        return [];
                    }

                    return [
                        Radio::make('content_action')
                            ->label(__('filament.users.actions.anonymizeModal.contentActionLabel'))
                            ->options([
                                'transfer' => __('filament.users.actions.anonymizeModal.transfer'),
                                'anonymize' => __('filament.users.actions.anonymizeModal.keepAnonymized'),
                            ])
                            ->default('anonymize')
                            ->required()
                            ->live(),
                        Select::make('transfer_to_user_id')
                            ->label(__('filament.users.actions.anonymizeModal.transferTo'))
                            ->options(fn () => UserModel::query()
                                ->whereNull('deleted_at')
                                ->whereNull('anonymized_at')
                                ->where('id', '!=', $record->id)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->visible(fn (Get $get): bool => $get('content_action') === 'transfer'),
                    ];
                })
                ->modalSubmitActionLabel(__('filament.users.actions.anonymizeModal.confirm'))
                ->action(function (array $data) use ($record): void {
                    $userService = app(UserServiceInterface::class);
                    $contentCounts = $userService->countUserContent($record->id);
                    $articlesCount = $contentCounts['articles'];

                    if ($articlesCount === 0) {
                        $userService->anonymize($record->id);
                    } else {
                        $dto = new AnonymizeUserDTO(
                            userId: $record->id,
                            contentAction: $data['content_action'] ?? 'anonymize',
                            transferToUserId: $data['transfer_to_user_id'] ?? null,
                        );
                        $userService->anonymizeWithContentTransfer($dto);
                    }

                    Notification::make()
                        ->title(__('filament.users.actions.anonymized'))
                        ->success()
                        ->send();
                    $this->redirect(UserResource::getUrl('index'));
                }),
        ];
    }
}
