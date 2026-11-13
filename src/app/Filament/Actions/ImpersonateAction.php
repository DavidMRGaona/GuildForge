<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Application\Impersonation\Services\ImpersonationEligibilityRule;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Impersonation\Exceptions\ImpersonationNotAllowedException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * «Impersonar» (core#578): in the user list menu and in the header of the user page. Offered
 * only when ImpersonationEligibilityRule lets the signed-in administrator start with that
 * account, and checked again by the service when confirmed (a forged call starts nothing).
 */
final class ImpersonateAction
{
    public const string NAME = 'impersonate';

    /**
     * @param  (callable(): UserModel)|null  $recordResolver  where the record comes from when the action is not a row action
     */
    public static function make(?callable $recordResolver = null): Action
    {
        $resolve = static fn (Action $action): ?UserModel => $recordResolver !== null
            ? $recordResolver()
            : (($record = $action->getRecord()) instanceof UserModel ? $record : null);

        return Action::make(self::NAME)
            ->label(__('filament.impersonation.action.label'))
            ->icon('heroicon-o-user-circle')
            ->color('gray')
            ->visible(static function (Action $action) use ($resolve): bool {
                $actor = Auth::user();
                $target = $resolve($action);

                return $actor instanceof Authenticatable
                    && $target instanceof UserModel
                    && app(ImpersonationEligibilityRule::class)->mayStart($actor, $target);
            })
            ->modalHeading(static fn (Action $action): string => __('filament.impersonation.action.heading', [
                'name' => ($target = $resolve($action)) instanceof UserModel ? ($target->display_name ?: $target->name) : '',
            ]))
            ->modalDescription(__('filament.impersonation.action.description'))
            ->modalIcon('heroicon-o-user-circle')
            ->modalSubmitActionLabel(__('filament.impersonation.action.confirm'))
            ->schema([
                Checkbox::make('simulate')
                    ->label(__('filament.impersonation.action.simulate'))
                    ->helperText(__('filament.impersonation.action.simulate_help'))
                    ->default(false),
            ])
            ->action(static function (Action $action, array $data, Component $livewire) use ($resolve): void {
                $actor = Auth::user();
                $target = $resolve($action);

                if (! $actor instanceof Authenticatable || ! $target instanceof UserModel) {
                    return;
                }

                $mode = ($data['simulate'] ?? false) === true ? ImpersonationMode::Simulate : ImpersonationMode::ReadOnly;

                try {
                    app(ImpersonationServiceInterface::class)->start($actor, $target, $mode);
                } catch (ImpersonationNotAllowedException) {
                    Notification::make()
                        ->title(__('filament.impersonation.action.refused'))
                        ->danger()
                        ->send();

                    return;
                }

                $action->success();
                $livewire->redirect(route('home'));
            });
    }
}
