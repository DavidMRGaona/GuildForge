<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Application\Mail\Services\MailStatisticsServiceInterface;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MailStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 81;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = app(MailStatisticsServiceInterface::class)->getStats();

        return [
            Stat::make(__('filament.mail.widget.sent_today'), (string) $stats->sentToday)
                ->description(__('filament.mail.widget.this_month', ['count' => $stats->sentThisMonth]))
                ->icon('heroicon-o-paper-airplane')
                ->color('success'),

            Stat::make(__('filament.mail.widget.failed_today'), (string) $stats->failedToday)
                ->description(__('filament.mail.widget.this_month', ['count' => $stats->failedThisMonth]))
                ->icon('heroicon-o-x-circle')
                ->color($stats->failedToday > 0 ? 'danger' : 'gray'),

            Stat::make(__('filament.mail.widget.delivery_rate'), $stats->deliveryRate.'%')
                ->icon('heroicon-o-chart-bar')
                ->color($stats->deliveryRate >= 95 ? 'success' : ($stats->deliveryRate >= 80 ? 'warning' : 'danger')),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
