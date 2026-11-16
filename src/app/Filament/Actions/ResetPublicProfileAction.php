<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * «Restablecer perfil público» (core#746), in the header of the user page: takes away a «Sobre mí» or a
 * banner that should not be there, and leaves the address, the visibility and the layout as they are.
 *
 * Authorized by UserPolicy::resetPublicProfile() (`profiles.moderate` on an account the person could edit),
 * which Filament checks both to show it and again when it runs, so a forged call does nothing. It is a write:
 * a read-only impersonation refuses it (RefuseChangesWhileOnlyLooking), since it is never marked as reading.
 */
final class ResetPublicProfileAction
{
    public const string NAME = 'resetPublicProfile';

    /**
     * @param  Closure(): UserModel  $recordResolver  the account whose profile is reset
     */
    public static function make(Closure $recordResolver): Action
    {
        return Action::make(self::NAME)
            ->record($recordResolver)
            ->authorize('resetPublicProfile')
            ->label(__('filament.users.actions.resetPublicProfile.label'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            // An anonymized account has no profile left to reset
            ->visible(static fn (): bool => $recordResolver()->anonymized_at === null)
            ->requiresConfirmation()
            ->modalHeading(static function () use ($recordResolver): string {
                $user = $recordResolver();

                return __('filament.users.actions.resetPublicProfile.heading', ['name' => $user->display_name ?: $user->name]);
            })
            ->modalDescription(__('filament.users.actions.resetPublicProfile.description'))
            ->modalIcon('heroicon-o-arrow-uturn-left')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('filament.users.actions.resetPublicProfile.confirm'))
            ->schema([
                Checkbox::make('confirmation')
                    ->label(__('filament.users.actions.resetPublicProfile.confirmation'))
                    ->accepted()
                    ->required(),
            ])
            ->action(static function () use ($recordResolver): void {
                $userId = (string) $recordResolver()->getKey();
                $reset = app(UserProfileServiceInterface::class)->resetContent($userId);

                // Ids only: what was taken away is the owner's and stays out of the log
                Log::info('Public profile reset from the panel', [
                    'actor_id' => (string) Auth::id(),
                    'user_id' => $userId,
                ]);

                $notification = Notification::make();
                $reset
                    ? $notification->title(__('filament.users.actions.resetPublicProfile.done'))->success()
                    : $notification->title(__('filament.users.actions.resetPublicProfile.nothing'))->info();
                $notification->send();
            });
    }
}
