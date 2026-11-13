<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalAccessLevel;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Concerns\ChecksPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Who may propose events and articles from the web, and the caps that keep the review queue
 * short (core#570). Proposals are off for both types until turned on here.
 *
 * @property Schema $form
 */
final class ProposalSettings extends Page implements HasForms
{
    use ChecksPermissions;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'propuestas';

    protected string $view = 'filament.pages.settings.proposal-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /** @var array<string, string>|null */
    private ?array $roleOptions = null;

    public static function getNavigationLabel(): string
    {
        return __('filament.proposal_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('filament.proposal_settings.title');
    }

    public static function canAccess(): bool
    {
        return self::userCan('settings.manage');
    }

    public function mount(ProposalSettingsServiceInterface $settings): void
    {
        $stored = $settings->settings();
        $data = [
            'max_pending' => $stored->maxPending,
            'max_open_drafts' => $stored->maxOpenDrafts,
            'rejected_retention_days' => $stored->rejectedRetentionDays,
            'submissions_per_hour' => $stored->submissionsPerHour,
        ];

        foreach (ProposalType::cases() as $type) {
            $typeSettings = $stored->forType($type);
            $data[$this->field($type, 'enabled')] = $typeSettings->enabled;
            // An unreadable level shows as the default; saving writes it back readable
            $data[$this->field($type, 'access_level')] = ($typeSettings->accessLevel ?? ProposalAccessLevel::Registered)->value;
            $data[$this->field($type, 'roles')] = $typeSettings->roles;
        }

        $data['events_show_proposer'] = $stored->events->showProposer;

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->typeSection(ProposalType::Event),
                $this->typeSection(ProposalType::Article),
                Section::make(__('filament.proposal_settings.sections.limits'))
                    ->description(__('filament.proposal_settings.sections.limits_description'))
                    ->columns(2)
                    ->schema([
                        $this->numberField('max_pending', ProposalSettingsDTO::MAX_LIMIT),
                        $this->numberField('max_open_drafts', ProposalSettingsDTO::MAX_LIMIT),
                        $this->numberField('submissions_per_hour', ProposalSettingsDTO::MAX_LIMIT),
                        $this->numberField('rejected_retention_days', ProposalSettingsDTO::MAX_RETENTION_DAYS),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(ProposalSettingsServiceInterface $settings): void
    {
        $data = $this->form->getState();

        $settings->save(new ProposalSettingsDTO(
            events: $this->typeSettings(ProposalType::Event, $data),
            articles: $this->typeSettings(ProposalType::Article, $data),
            maxPending: $this->integer($data, 'max_pending'),
            maxOpenDrafts: $this->integer($data, 'max_open_drafts'),
            rejectedRetentionDays: $this->integer($data, 'rejected_retention_days'),
            submissionsPerHour: $this->integer($data, 'submissions_per_hour'),
        ));

        Notification::make()
            ->title(__('filament.proposal_settings.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }

    private function typeSection(ProposalType $type): Section
    {
        $key = $type->value;
        $levelField = $this->field($type, 'access_level');

        $fields = [
            Toggle::make($this->field($type, 'enabled'))
                ->label(__('filament.proposal_settings.fields.enabled'))
                ->helperText(__("filament.proposal_settings.fields.enabled_help_{$key}")),
            Select::make($levelField)
                ->label(__('filament.proposal_settings.fields.access_level'))
                ->options($this->accessLevelOptions($type))
                ->native(false)
                ->required()
                ->live()
                ->in(array_map(static fn (ProposalAccessLevel $level): string => $level->value, ProposalAccessLevel::cases())),
            Select::make($this->field($type, 'roles'))
                ->label(__('filament.proposal_settings.fields.roles'))
                ->helperText(__('filament.proposal_settings.fields.roles_help'))
                ->multiple()
                ->options(fn (): array => $this->roleOptions())
                ->native(false)
                ->visible(fn (Get $get): bool => $get($levelField) === ProposalAccessLevel::Roles->value)
                ->required(fn (Get $get): bool => $get($levelField) === ProposalAccessLevel::Roles->value)
                // Filament checks the options of single selects only
                ->rules([$this->onlyRoleOptions(...)]),
        ];

        if ($type === ProposalType::Event) {
            $fields[] = Toggle::make('events_show_proposer')
                ->label(__('filament.proposal_settings.fields.show_proposer'))
                ->helperText(__('filament.proposal_settings.fields.show_proposer_help'));
        }

        return Section::make(__("filament.proposal_settings.sections.{$key}"))
            ->schema($fields);
    }

    private function numberField(string $name, int $max): TextInput
    {
        return TextInput::make($name)
            ->label(__("filament.proposal_settings.fields.{$name}"))
            ->helperText(__("filament.proposal_settings.fields.{$name}_help"))
            ->integer()
            ->required()
            ->minValue(ProposalSettingsDTO::MIN_LIMIT)
            ->maxValue($max);
    }

    /**
     * @return array<string, string>
     */
    private function accessLevelOptions(ProposalType $type): array
    {
        return [
            ProposalAccessLevel::Registered->value => __('filament.proposal_settings.access_levels.registered'),
            ProposalAccessLevel::Roles->value => __('filament.proposal_settings.access_levels.roles'),
            ProposalAccessLevel::Permission->value => __('filament.proposal_settings.access_levels.permission', [
                'permission' => __('authorization.permissions.'.$type->proposePermission()),
            ]),
        ];
    }

    /**
     * Every role, the association member role included, by its display name.
     *
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        if ($this->roleOptions !== null) {
            return $this->roleOptions;
        }

        $options = [];
        foreach (RoleModel::query()->orderBy('display_name')->get(['name', 'display_name']) as $role) {
            $options[$role->name] = $role->display_name;
        }

        return $this->roleOptions = $options;
    }

    private function onlyRoleOptions(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $allowed = array_keys($this->roleOptions());

            foreach (is_array($value) ? $value : [$value] as $role) {
                if (! in_array($role, $allowed, true)) {
                    $fail(__('validation.in', ['attribute' => $attribute]));

                    return;
                }
            }
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function typeSettings(ProposalType $type, array $data): ProposalTypeSettingsDTO
    {
        $level = ProposalAccessLevel::tryFrom((string) ($data[$this->field($type, 'access_level')] ?? ''));
        $roles = $data[$this->field($type, 'roles')] ?? [];

        return new ProposalTypeSettingsDTO(
            enabled: (bool) ($data[$this->field($type, 'enabled')] ?? false),
            accessLevel: $level ?? ProposalAccessLevel::Registered,
            // Hidden (so empty) unless the level is Roles
            roles: is_array($roles) ? array_values(array_filter($roles, is_string(...))) : [],
            showProposer: $type === ProposalType::Event && (bool) ($data['events_show_proposer'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function field(ProposalType $type, string $name): string
    {
        return $type->value.'_'.$name;
    }
}
