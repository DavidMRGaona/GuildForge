<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class UpcomingEventsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $upcomingEventsCount = EventModel::query()
            ->where('is_published', true)
            ->where('start_date', '>=', Carbon::now())
            ->count();

        $totalEventsCount = EventModel::query()->count();

        return [
            Stat::make(__('Próximos eventos'), (string) $upcomingEventsCount)
                ->description(__('Eventos publicados con fecha futura'))
                ->icon('heroicon-o-calendar')
                ->color('success'),
            Stat::make(__('Total de eventos'), (string) $totalEventsCount)
                ->description(__('Todos los eventos registrados'))
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),
        ];
    }
}
