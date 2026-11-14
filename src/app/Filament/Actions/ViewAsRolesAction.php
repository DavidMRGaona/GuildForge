<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Application\Impersonation\Services\ImpersonationEligibilityRule;
use App\Application\Impersonation\Services\ImpersonationRolesInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Impersonation\Exceptions\ImpersonationNotAllowedException;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * «Ver como roles» (core#644): in the header of the user and role lists. Offered to whoever
 * may impersonate; the service checks everything again when confirmed, so a forged call
 * starts nothing.
 */
final class ViewAsRolesAction
{
    public const string NAME = 'viewAsRoles';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(__('filament.impersonation.roles_action.label'))
            ->icon('heroicon-o-users')
            ->color('gray')
            ->visible(static fn (): bool => ($actor = Auth::user()) instanceof Authenticatable
                && app(ImpersonationEligibilityRule::class)->mayImpersonate($actor))
            ->modalHeading(__('filament.impersonation.roles_action.heading'))
            ->modalDescription(__('filament.impersonation.roles_action.description'))
            ->modalIcon('heroicon-o-users')
            ->modalSubmitActionLabel(__('filament.impersonation.roles_action.confirm'))
            ->schema(static function (): array {
                $roles = app(ImpersonationRolesInterface::class);
                $baseRoleId = $roles->baseRoleId();
                $options = [];
                foreach ($roles->offered() as $role) {
                    $options[$role->id] = $role->displayName;
                }

                return [
                    CheckboxList::make('roles')
                        ->label(__('filament.impersonation.roles_action.roles'))
                        ->helperText(__('filament.impersonation.roles_action.roles_help'))
                        ->options($options)
                        ->default($baseRoleId === null ? [] : [$baseRoleId])
                        ->disableOptionWhen(static fn (string $value): bool => $value === $baseRoleId)
                        // Filament's own `in` rule skips disabled options, which would reject the
                        // base role that is always submitted.
                        ->in(array_keys($options))
                        ->required(),
                    Checkbox::make('simulate')
                        ->label(__('filament.impersonation.action.simulate'))
                        ->helperText(__('filament.impersonation.action.simulate_help'))
                        ->default(false),
                ];
            })
            ->action(static function (Action $action, array $data, Component $livewire): void {
                $actor = Auth::user();

                if (! $actor instanceof Authenticatable) {
                    return;
                }

                $mode = ($data['simulate'] ?? false) === true ? ImpersonationMode::Simulate : ImpersonationMode::ReadOnly;

                try {
                    app(ImpersonationServiceInterface::class)->startWithRoles($actor, array_values($data['roles'] ?? []), $mode);
                } catch (ImpersonationNotAllowedException) {
                    Notification::make()
                        ->title(__('filament.impersonation.roles_action.refused'))
                        ->danger()
                        ->send();

                    return;
                }

                $action->success();
                $livewire->redirect(route('home'));
            });
    }
}
