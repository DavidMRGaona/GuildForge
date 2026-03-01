<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;

/**
 * @property Form $form
 */
final class DashboardSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.settings.dashboard-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament.dashboard_settings.navigation');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.settings');
    }

    public function getTitle(): string
    {
        return __('filament.dashboard_settings.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(DashboardWidgetConfigServiceInterface $configService): void
    {
        $this->form->fill([
            'widgets' => $this->buildWidgetRows($configService),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('widgets')
                    ->label('')
                    ->schema([
                        TextInput::make('label')
                            ->label(__('filament.dashboard_settings.fields.widget'))
                            ->disabled()
                            ->dehydrated(),

                        Hidden::make('class'),

                        Hidden::make('type'),

                        Toggle::make('enabled')
                            ->label(__('filament.dashboard_settings.fields.enabled'))
                            ->inline(false),

                        TextInput::make('sort')
                            ->label(__('filament.dashboard_settings.fields.sort'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->required(),

                        TextInput::make('limit')
                            ->label(__('filament.dashboard_settings.fields.limit'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->visible(fn (callable $get): bool => $get('type') === 'table')
                            ->helperText(__('filament.dashboard_settings.fields.limit_help')),
                    ])
                    ->columns(5)
                    ->reorderable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->defaultItems(0),
            ])
            ->statePath('data');
    }

    public function save(DashboardWidgetConfigServiceInterface $configService): void
    {
        $formData = $this->form->getState();

        $config = [];
        foreach ($formData['widgets'] ?? [] as $row) {
            $entry = [
                'enabled' => (bool) $row['enabled'],
                'sort' => (int) $row['sort'],
            ];

            if ($row['type'] === 'table' && isset($row['limit']) && $row['limit'] !== '') {
                $entry['limit'] = (int) $row['limit'];
            }

            $config[$row['class']] = $entry;
        }

        $configService->saveConfig($config);

        Notification::make()
            ->title(__('filament.dashboard_settings.saved'))
            ->success()
            ->send();
    }

    public function resetToDefaults(DashboardWidgetConfigServiceInterface $configService): void
    {
        $configService->clearConfig();

        $this->form->fill([
            'widgets' => $this->buildWidgetRows($configService),
        ]);

        Notification::make()
            ->title(__('filament.dashboard_settings.reset'))
            ->success()
            ->send();
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label(__('filament.dashboard_settings.reset_button'))
                ->color('gray')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading(__('filament.dashboard_settings.reset_confirm_title'))
                ->modalDescription(__('filament.dashboard_settings.reset_confirm_description'))
                ->action(fn () => $this->resetToDefaults(app(DashboardWidgetConfigServiceInterface::class))),
        ];
    }

    /**
     * Build the repeater rows from current config merged with defaults.
     *
     * @return array<int, array{label: string, class: string, type: string, enabled: bool, sort: int, limit: int|null}>
     */
    private function buildWidgetRows(DashboardWidgetConfigServiceInterface $configService): array
    {
        $defaults = $configService->getDefaults();
        $config = $configService->getConfig();
        $rows = [];

        foreach ($defaults as $widgetClass => $defaultValues) {
            $saved = $config[$widgetClass] ?? [];

            $isTable = is_subclass_of($widgetClass, TableWidget::class);
            $isStats = is_subclass_of($widgetClass, StatsOverviewWidget::class);

            $rows[] = [
                'label' => $this->getWidgetLabel($widgetClass),
                'class' => $widgetClass,
                'type' => $isTable ? 'table' : ($isStats ? 'stats' : 'other'),
                'enabled' => $saved['enabled'] ?? $defaultValues['enabled'],
                'sort' => $saved['sort'] ?? $defaultValues['sort'],
                'limit' => $isTable ? ($saved['limit'] ?? 5) : null,
            ];
        }

        // Sort rows by sort value for display
        usort($rows, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $rows;
    }

    /**
     * Get a human-readable label for a widget class.
     */
    private function getWidgetLabel(string $widgetClass): string
    {
        $shortName = class_basename($widgetClass);

        // Try translation first
        $translationKey = 'filament.dashboard_settings.widgets.'.$this->classToSnake($shortName);
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        // Fallback: derive from class name (e.g. UpcomingEventsWidget → Upcoming events)
        $name = str_replace('Widget', '', $shortName);
        $name = (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);

        return ucfirst(mb_strtolower($name));
    }

    /**
     * Convert a class basename to snake_case.
     */
    private function classToSnake(string $className): string
    {
        $snake = (string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $className);

        return mb_strtolower($snake);
    }
}
