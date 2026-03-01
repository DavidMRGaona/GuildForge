<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Application\Mail\Services\EmailQuotaServiceInterface;
use App\Application\Mail\Services\MailStatisticsServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MailHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 80;

    protected function getColumns(): int
    {
        return 1;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $settingsService = app(SettingsServiceInterface::class);
        $isEnabled = (bool) $settingsService->get('mail_enabled', '1');

        if (! $isEnabled) {
            return [
                Stat::make(__('filament.mail.widget.email'), __('filament.mail.widget.disabled'))
                    ->description(__('filament.mail.widget.disabled_description'))
                    ->icon('heroicon-o-envelope')
                    ->color('gray'),
            ];
        }

        $stats = app(MailStatisticsServiceInterface::class)->getStats();
        $quotaStatus = app(EmailQuotaServiceInterface::class)->getQuotaStatus();

        $color = 'success';
        $statusText = __('filament.mail.widget.operational');

        if ($quotaStatus->isLimitReached) {
            $color = 'danger';
            $statusText = __('filament.mail.widget.limit_reached');
        } elseif ($quotaStatus->isWarning) {
            $color = 'warning';
            $statusText = __('filament.mail.widget.quota_warning');
        } elseif ($stats->failedToday > 0) {
            $color = 'warning';
            $statusText = __('filament.mail.widget.errors_today', ['count' => $stats->failedToday]);
        }

        return [
            Stat::make(__('filament.mail.widget.email'), $statusText)
                ->description(__('filament.mail.widget.stats_description', [
                    'sent' => $stats->sentToday,
                    'rate' => $stats->deliveryRate,
                ]))
                ->icon('heroicon-o-envelope')
                ->color($color),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
