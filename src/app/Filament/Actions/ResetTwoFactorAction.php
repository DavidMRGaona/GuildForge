<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * «Restablecer el doble factor» (core#771), in the header of the user page: for whoever lost their phone
 * and their recovery codes. The account is left with its password alone, every session closed, and the
 * person is told by email.
 *
 * Authorized by UserPolicy::resetTwoFactor() (`users.reset_two_factor`, held only by the administrator
 * role, on an account that is neither one's own nor anonymized), which Filament checks both to show it and
 * again when it runs, so a forged call does nothing. It only shows while the account has two-factor
 * authentication on. It is a write: a read-only impersonation refuses it (RefuseChangesWhileOnlyLooking),
 * since it is never marked as reading.
 */
final class ResetTwoFactorAction
{
    public const string NAME = 'resetTwoFactor';

    /**
     * @param  Closure(): UserModel  $recordResolver  the account whose two-factor authentication is reset
     */
    public static function make(Closure $recordResolver): Action
    {
        return Action::make(self::NAME)
            ->record($recordResolver)
            ->authorize('resetTwoFactor')
            ->label(__('filament.users.actions.resetTwoFactor.label'))
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(static fn (): bool => app(TwoFactorStatusReaderInterface::class)->isEnabled((string) $recordResolver()->getKey()))
            ->requiresConfirmation()
            ->modalHeading(static function () use ($recordResolver): string {
                $user = $recordResolver();

                return __('filament.users.actions.resetTwoFactor.heading', ['name' => $user->display_name ?: $user->name]);
            })
            ->modalDescription(__('filament.users.actions.resetTwoFactor.description'))
            ->modalIcon('heroicon-o-shield-exclamation')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('filament.users.actions.resetTwoFactor.confirm'))
            ->schema([
                Checkbox::make('confirmation')
                    ->label(__('filament.users.actions.resetTwoFactor.confirmation'))
                    ->accepted()
                    ->required(),
            ])
            ->action(static function () use ($recordResolver): void {
                $userId = (string) $recordResolver()->getKey();

                try {
                    $reset = app(TwoFactorResetInterface::class)->reset($userId, (string) Auth::id());
                } catch (TwoFactorException) {
                    Notification::make()
                        ->title(__('filament.users.actions.resetTwoFactor.refused'))
                        ->danger()
                        ->send();

                    return;
                }

                // Only when something was taken away, and with ids only
                if ($reset) {
                    Log::info('Two-factor reset from the panel', [
                        'actor_id' => (string) Auth::id(),
                        'user_id' => $userId,
                    ]);
                }

                $notification = Notification::make();
                $reset
                    ? $notification->title(__('filament.users.actions.resetTwoFactor.done'))->success()
                    : $notification->title(__('filament.users.actions.resetTwoFactor.nothing'))->info();
                $notification->send();
            });
    }
}
