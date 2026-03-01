<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GalleryStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected function getColumns(): int
    {
        return 2;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totalGalleries = GalleryModel::query()->count();
        $publishedGalleries = GalleryModel::query()->where('is_published', true)->count();
        $totalPhotos = PhotoModel::query()->count();

        return [
            Stat::make(__('Galerías publicadas'), (string) $publishedGalleries)
                ->description(__(':total galerías en total', ['total' => $totalGalleries]))
                ->icon('heroicon-o-photo')
                ->color('warning'),
            Stat::make(__('Total de fotos'), (string) $totalPhotos)
                ->description(__('Fotos en todas las galerías'))
                ->icon('heroicon-o-squares-2x2')
                ->color('info'),
        ];
    }
}
