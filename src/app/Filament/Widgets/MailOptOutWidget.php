<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Application\Mail\Services\MailCategoryOptOutStatisticsInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Concerns\ChecksPermissions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

/**
 * Who turned off each optional mail category (core#332): counts only, never names or
 * addresses. A lot of opt-outs in one category says its mail bothers people or arrives too often.
 */
class MailOptOutWidget extends TableWidget
{
    use ChecksPermissions;

    protected static ?int $sort = 82;

    // Shown on the mail statistics page only, not on the dashboard
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return self::userCan('mail.view_stats');
    }

    public function getTableHeading(): string
    {
        return __('filament.mail_opt_outs.heading');
    }

    public function table(Table $table): Table
    {
        $report = app(MailCategoryOptOutStatisticsInterface::class)->report();

        $records = [];
        foreach ($report->categories as $row) {
            $records[$row->key] = [
                'section' => $row->section,
                'label' => $row->label,
                'opted_out' => $row->optedOut,
                'eligible' => $row->eligible,
                'percentage' => $row->percentage,
            ];
        }

        return $table
            ->heading(__('filament.mail_opt_outs.heading'))
            ->description(trans_choice('filament.mail_opt_outs.paused', $report->pausedAccounts, [
                'count' => $report->pausedAccounts,
            ]))
            ->records(static fn (): array => $records)
            ->columns([
                TextColumn::make('label')
                    ->label(__('filament.mail_opt_outs.columns.category'))
                    ->wrap(),
                TextColumn::make('section')
                    ->label(__('filament.mail_opt_outs.columns.section'))
                    ->visibleFrom('md'),
                TextColumn::make('opted_out')
                    ->label(__('filament.mail_opt_outs.columns.opted_out'))
                    ->alignCenter(),
                TextColumn::make('eligible')
                    ->label(__('filament.mail_opt_outs.columns.eligible'))
                    ->alignCenter()
                    ->visibleFrom('sm'),
                TextColumn::make('percentage')
                    ->label(__('filament.mail_opt_outs.columns.percentage'))
                    ->formatStateUsing(static fn (float $state): string => Number::format($state, maxPrecision: 1, locale: 'es').' %')
                    ->alignCenter(),
            ])
            ->recordActions([
                RowDetailsAction::makeIconButton(),
            ])
            ->emptyStateHeading(__('filament.mail_opt_outs.empty'))
            ->paginated(false);
    }
}
