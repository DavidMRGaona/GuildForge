<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Domain\Authorization\Enums\RoleAuditSource;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The read-only role history shown on the user and role edit pages.
 */
trait ListsRoleAuditEntries
{
    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.roles.audit.title');
    }

    public function table(Table $table): Table
    {
        $reveal = $this->revealsAccountNames();

        $columns = [
            TextColumn::make('created_at')
                ->label(__('filament.roles.audit.columns.date'))
                ->formatStateUsing(fn (mixed $state): string => $state instanceof DateTimeInterface
                    ? app(TimezoneServiceInterface::class)->format($state, 'd/m/Y H:i')
                    : '')
                ->sortable(),
            TextColumn::make('event')
                ->label(__('filament.roles.audit.columns.event'))
                ->formatStateUsing(fn (string $state): string => self::eventLabel($state)),
            TextColumn::make('role_name')
                ->label(__('filament.roles.audit.columns.role'))
                // The current display name while the role exists; the stored name once it is gone
                ->state(fn (RoleAuditEntryModel $record): string => $record->role?->display_name ?: $record->role_name),
        ];

        if ($this->showsAffectedAccount()) {
            $columns[] = TextColumn::make('user_id')
                ->label(__('filament.roles.audit.columns.account'))
                ->state(fn (RoleAuditEntryModel $record): string => self::accountLabel($record->user_id, $record->user, $reveal));
        }

        $columns[] = TextColumn::make('changes')
            ->label(__('filament.roles.audit.columns.changes'))
            ->state(fn (RoleAuditEntryModel $record): array => self::summarize($record->changes ?? []))
            ->listWithLineBreaks()
            ->placeholder('—')
            ->wrap();
        $columns[] = TextColumn::make('actor_id')
            ->label(__('filament.roles.audit.columns.actor'))
            ->state(fn (RoleAuditEntryModel $record): string => $record->actor_id === null
                ? __('filament.roles.audit.system')
                : self::accountLabel($record->actor_id, $record->actor, $reveal));
        $columns[] = TextColumn::make('source')
            ->label(__('filament.roles.audit.columns.source'))
            ->formatStateUsing(fn (string $state): string => self::sourceLabel($state));

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['actor', 'user', 'role']))
            ->columns($columns)
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->emptyStateHeading(__('filament.roles.audit.empty'))
            ->paginated([10, 25, 50]);
    }

    /**
     * The role page lists entries of many accounts, so it names the affected one.
     */
    abstract protected function showsAffectedAccount(): bool;

    private static function eventLabel(string $event): string
    {
        $case = RoleAuditEvent::tryFrom($event);

        return $case === null ? $event : __('filament.roles.audit.events.'.$case->value);
    }

    private static function sourceLabel(string $source): string
    {
        $case = RoleAuditSource::tryFrom($source);

        return $case === null ? $source : __('filament.roles.audit.sources.'.$case->value);
    }

    /**
     * The role page is open to whoever may view roles, who need not be able to see accounts: they get no
     * names there. The user page is only open to someone who can view that very account.
     */
    private function revealsAccountNames(): bool
    {
        if (! $this->showsAffectedAccount()) {
            return true;
        }

        $viewer = Auth::user();

        return $viewer instanceof UserModel && $viewer->can('viewAny', UserModel::class);
    }

    private static function accountLabel(?string $id, ?Model $account, bool $reveal): string
    {
        if ($id === null) {
            return '—';
        }

        if (! $reveal) {
            return __('filament.roles.audit.unknownAccount');
        }

        if (! $account instanceof Model || $account->getAttribute('anonymized_at') !== null) {
            return __('filament.roles.audit.anonymizedActor');
        }

        $displayName = $account->getAttribute('display_name');

        return is_string($displayName) && $displayName !== '' ? $displayName : (string) $account->getAttribute('name');
    }

    /**
     * @param  array{permissions_added?: list<string>, permissions_removed?: list<string>, fields?: array<string, array{from: mixed, to: mixed}>}  $changes
     * @return list<string>
     */
    private static function summarize(array $changes): array
    {
        $lines = [];

        if (($changes['permissions_added'] ?? []) !== []) {
            $lines[] = __('filament.roles.audit.permissionsAdded', ['list' => self::permissionLabels($changes['permissions_added'])]);
        }

        if (($changes['permissions_removed'] ?? []) !== []) {
            $lines[] = __('filament.roles.audit.permissionsRemoved', ['list' => self::permissionLabels($changes['permissions_removed'])]);
        }

        foreach ($changes['fields'] ?? [] as $field => $change) {
            $label = trans()->has('filament.roles.audit.fields.'.$field)
                ? __('filament.roles.audit.fields.'.$field)
                : $field;
            $lines[] = $label.': '.self::formatValue($change['from'] ?? null).' → '.self::formatValue($change['to'] ?? null);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $keys
     */
    private static function permissionLabels(array $keys): string
    {
        $registry = app(PermissionRegistryInterface::class);

        return implode(', ', array_map(
            static function (string $key) use ($registry): string {
                $definition = $registry->find($key);

                return $definition === null ? $key : $definition->label;
            },
            $keys,
        ));
    }

    private static function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? __('filament.roles.audit.yes') : __('filament.roles.audit.no'),
            is_scalar($value) => (string) $value,
            default => '—',
        };
    }
}
