<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Application\Mail\Services\MailConfigurationServiceInterface;
use App\Application\Mail\Services\MailTestServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Concerns\ManagesPageSettings;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
final class MailSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesPageSettings;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.pages.settings.mail-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament.mail.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.admin');
    }

    public function getTitle(): string
    {
        return __('filament.mail.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(SettingsServiceInterface $settingsService): void
    {
        $this->form->fill($this->loadSettings($settingsService));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('MailSettings')
                    ->tabs([
                        $this->generalTab(),
                        $this->smtpTab(),
                        $this->sesTab(),
                        $this->resendTab(),
                        $this->quotasTab(),
                        $this->testingTab(),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(SettingsServiceInterface $settingsService): void
    {
        $formData = $this->form->getState();
        $this->saveSettings($settingsService, $formData);

        // Apply changes to runtime config immediately
        app(MailConfigurationServiceInterface::class)->applyToRuntime();

        Notification::make()
            ->title(__('filament.mail.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<string>
     */
    protected function getSettingsKeys(): array
    {
        return [
            'mail_enabled',
            'mail_driver',
            'mail_from_address',
            'mail_from_name',
            'mail_smtp_host',
            'mail_smtp_port',
            'mail_smtp_username',
            'mail_smtp_password',
            'mail_smtp_encryption',
            'mail_smtp_timeout',
            'mail_ses_region',
            'mail_ses_access_key_id',
            'mail_ses_secret_access_key',
            'mail_resend_api_key',
            'mail_quota_daily_limit',
            'mail_quota_monthly_limit',
            'mail_quota_warning_threshold',
        ];
    }

    /**
     * @return array<string>
     */
    protected function getJsonFields(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getImageFields(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getEncryptedFields(): array
    {
        return ['mail_smtp_password', 'mail_ses_secret_access_key', 'mail_resend_api_key'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultSettings(): array
    {
        return [
            'mail_enabled' => '1',
            'mail_driver' => 'smtp',
            'mail_from_address' => '',
            'mail_from_name' => '',
            'mail_smtp_host' => '',
            'mail_smtp_port' => '587',
            'mail_smtp_username' => '',
            'mail_smtp_password' => '',
            'mail_smtp_encryption' => 'tls',
            'mail_smtp_timeout' => '30',
            'mail_ses_region' => 'eu-west-1',
            'mail_ses_access_key_id' => '',
            'mail_ses_secret_access_key' => '',
            'mail_resend_api_key' => '',
            'mail_quota_daily_limit' => '0',
            'mail_quota_monthly_limit' => '0',
            'mail_quota_warning_threshold' => '80',
        ];
    }

    private function generalTab(): Tab
    {
        return Tab::make(__('filament.mail.tabs.general'))
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Toggle::make('mail_enabled')
                    ->label(__('filament.mail.fields.enabled'))
                    ->helperText(__('filament.mail.fields.enabled_help')),

                Select::make('mail_driver')
                    ->label(__('filament.mail.fields.driver'))
                    ->helperText(__('filament.mail.fields.driver_help'))
                    ->options([
                        'smtp' => 'SMTP',
                        'ses' => 'Amazon SES',
                        'resend' => 'Resend',
                        'mail' => 'PHP Mail',
                        'log' => 'Log (solo pruebas)',
                    ])
                    ->native(false)
                    ->live()
                    ->required(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('mail_from_address')
                            ->label(__('filament.mail.fields.from_address'))
                            ->helperText(__('filament.mail.fields.from_address_help'))
                            ->email()
                            ->maxLength(255),

                        TextInput::make('mail_from_name')
                            ->label(__('filament.mail.fields.from_name'))
                            ->helperText(__('filament.mail.fields.from_name_help'))
                            ->maxLength(255),
                    ]),
            ]);
    }

    private function smtpTab(): Tab
    {
        return Tab::make(__('filament.mail.tabs.smtp'))
            ->icon('heroicon-o-server')
            ->schema([
                Section::make(__('filament.mail.sections.smtp'))
                    ->description(__('filament.mail.sections.smtp_description'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('mail_smtp_host')
                                    ->label(__('filament.mail.fields.smtp_host'))
                                    ->maxLength(255),

                                TextInput::make('mail_smtp_port')
                                    ->label(__('filament.mail.fields.smtp_port'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(65535),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('mail_smtp_username')
                                    ->label(__('filament.mail.fields.smtp_username'))
                                    ->maxLength(255),

                                TextInput::make('mail_smtp_password')
                                    ->label(__('filament.mail.fields.smtp_password'))
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('mail_smtp_encryption')
                                    ->label(__('filament.mail.fields.smtp_encryption'))
                                    ->options([
                                        '' => __('filament.mail.encryption_options.none'),
                                        'tls' => __('filament.mail.encryption_options.tls'),
                                        'ssl' => __('filament.mail.encryption_options.ssl'),
                                    ])
                                    ->native(false),

                                TextInput::make('mail_smtp_timeout')
                                    ->label(__('filament.mail.fields.smtp_timeout'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(300)
                                    ->suffix('s'),
                            ]),
                    ]),
            ])
            ->visible(fn (Get $get): bool => ($get('mail_driver') ?? 'smtp') === 'smtp');
    }

    private function sesTab(): Tab
    {
        return Tab::make(__('filament.mail.tabs.ses'))
            ->icon('heroicon-o-cloud')
            ->schema([
                Section::make(__('filament.mail.sections.ses'))
                    ->description(__('filament.mail.sections.ses_description'))
                    ->schema([
                        Select::make('mail_ses_region')
                            ->label(__('filament.mail.fields.ses_region'))
                            ->options([
                                'us-east-1' => __('filament.mail.ses_regions.us-east-1'),
                                'us-east-2' => __('filament.mail.ses_regions.us-east-2'),
                                'us-west-1' => __('filament.mail.ses_regions.us-west-1'),
                                'us-west-2' => __('filament.mail.ses_regions.us-west-2'),
                                'eu-west-1' => __('filament.mail.ses_regions.eu-west-1'),
                                'eu-west-2' => __('filament.mail.ses_regions.eu-west-2'),
                                'eu-west-3' => __('filament.mail.ses_regions.eu-west-3'),
                                'eu-central-1' => __('filament.mail.ses_regions.eu-central-1'),
                                'eu-south-1' => __('filament.mail.ses_regions.eu-south-1'),
                                'ap-southeast-1' => __('filament.mail.ses_regions.ap-southeast-1'),
                                'ap-southeast-2' => __('filament.mail.ses_regions.ap-southeast-2'),
                                'ap-northeast-1' => __('filament.mail.ses_regions.ap-northeast-1'),
                            ])
                            ->native(false),

                        TextInput::make('mail_ses_access_key_id')
                            ->label(__('filament.mail.fields.ses_access_key_id'))
                            ->maxLength(255),

                        TextInput::make('mail_ses_secret_access_key')
                            ->label(__('filament.mail.fields.ses_secret_access_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ]),
            ])
            ->visible(fn (Get $get): bool => ($get('mail_driver') ?? 'smtp') === 'ses');
    }

    private function resendTab(): Tab
    {
        return Tab::make(__('filament.mail.tabs.resend'))
            ->icon('heroicon-o-paper-airplane')
            ->schema([
                Section::make(__('filament.mail.sections.resend'))
                    ->description(__('filament.mail.sections.resend_description'))
                    ->schema([
                        TextInput::make('mail_resend_api_key')
                            ->label(__('filament.mail.fields.resend_api_key'))
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ]),
            ])
            ->visible(fn (Get $get): bool => ($get('mail_driver') ?? 'smtp') === 'resend');
    }

    private function quotasTab(): Tab
    {
        return Tab::make(__('filament.mail.tabs.quotas'))
            ->icon('heroicon-o-chart-bar')
            ->schema([
                Section::make(__('filament.mail.sections.quotas'))
                    ->description(__('filament.mail.sections.quotas_description'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('mail_quota_daily_limit')
                                    ->label(__('filament.mail.fields.quota_daily_limit'))
                                    ->helperText(__('filament.mail.fields.quota_daily_limit_help'))
                                    ->numeric()
                                    ->minValue(0),

                                TextInput::make('mail_quota_monthly_limit')
                                    ->label(__('filament.mail.fields.quota_monthly_limit'))
                                    ->helperText(__('filament.mail.fields.quota_monthly_limit_help'))
                                    ->numeric()
                                    ->minValue(0),
                            ]),

                        TextInput::make('mail_quota_warning_threshold')
                            ->label(__('filament.mail.fields.quota_warning_threshold'))
                            ->helperText(__('filament.mail.fields.quota_warning_threshold_help'))
                            ->numeric()
                            ->minValue(50)
                            ->maxValue(100)
                            ->suffix('%'),
                    ]),
            ]);
    }

    private function testingTab(): Tab
    {
        return Tab::make(__('filament.mail.tabs.testing'))
            ->icon('heroicon-o-beaker')
            ->schema([
                Section::make(__('filament.mail.sections.testing'))
                    ->description(__('filament.mail.sections.testing_description'))
                    ->schema([
                        Actions::make([
                            Action::make('testConnection')
                                ->label(__('filament.mail.actions.test_connection'))
                                ->icon('heroicon-o-signal')
                                ->action(fn () => $this->testConnection()),
                        ]),

                        TextInput::make('testRecipient')
                            ->label(__('filament.mail.fields.test_recipient'))
                            ->helperText(__('filament.mail.fields.test_recipient_help'))
                            ->email()
                            ->maxLength(255),

                        Actions::make([
                            Action::make('sendTestEmail')
                                ->label(__('filament.mail.actions.send_test_email'))
                                ->icon('heroicon-o-paper-airplane')
                                ->action(fn () => $this->sendTestEmail()),
                        ]),
                    ]),
            ]);
    }

    public function testConnection(): void
    {
        $result = app(MailTestServiceInterface::class)->testSmtpConnection();

        if ($result->success) {
            Notification::make()
                ->title(__('filament.mail.notifications.connection_success'))
                ->body(__('filament.mail.notifications.connection_success_body', [
                    'response' => $result->serverResponse ?? '-',
                    'time' => $result->responseTimeMs ?? 0,
                ]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('filament.mail.notifications.connection_failed'))
                ->body($result->errorMessage ?? '')
                ->danger()
                ->send();
        }
    }

    public function sendTestEmail(): void
    {
        $recipient = trim((string) ($this->data['testRecipient'] ?? ''));

        if ($recipient === '') {
            Notification::make()
                ->title(__('filament.mail.notifications.test_email_failed'))
                ->body(__('validation.required', ['attribute' => __('filament.mail.fields.test_recipient')]))
                ->danger()
                ->send();

            return;
        }

        $result = app(MailTestServiceInterface::class)->sendTestEmail($recipient);

        if ($result->success) {
            Notification::make()
                ->title(__('filament.mail.notifications.test_email_sent'))
                ->body(__('filament.mail.notifications.test_email_sent_body', [
                    'time' => $result->responseTimeMs ?? 0,
                ]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('filament.mail.notifications.test_email_failed'))
                ->body($result->errorMessage ?? '')
                ->danger()
                ->send();
        }
    }
}
