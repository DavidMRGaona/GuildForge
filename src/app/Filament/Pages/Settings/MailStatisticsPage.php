<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Domain\Mail\Enums\EmailStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EmailLogModel;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class MailStatisticsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 13;

    protected static string $view = 'filament.pages.settings.mail-statistics';

    public static function getNavigationLabel(): string
    {
        return __('filament.mail_statistics.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.admin');
    }

    public function getTitle(): string
    {
        return __('filament.mail_statistics.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(EmailLogModel::query()->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('recipient')
                    ->label(__('filament.mail_statistics.table.recipient'))
                    ->searchable()
                    ->limit(30),

                TextColumn::make('subject')
                    ->label(__('filament.mail_statistics.table.subject'))
                    ->searchable()
                    ->limit(40),

                TextColumn::make('status')
                    ->label(__('filament.mail_statistics.table.status'))
                    ->badge()
                    ->color(fn (EmailStatus $state): string => match ($state) {
                        EmailStatus::Sent => 'success',
                        EmailStatus::Failed => 'danger',
                        EmailStatus::Bounced => 'warning',
                        EmailStatus::Complained => 'danger',
                    }),

                TextColumn::make('mailer')
                    ->label(__('filament.mail_statistics.table.mailer')),

                TextColumn::make('error_message')
                    ->label(__('filament.mail_statistics.table.error'))
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('filament.mail_statistics.table.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.mail_statistics.table.status'))
                    ->options(
                        collect(EmailStatus::cases())
                            ->mapWithKeys(fn (EmailStatus $status): array => [
                                $status->value => $status->label(),
                            ])
                            ->all()
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
