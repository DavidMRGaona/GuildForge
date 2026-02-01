<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

final class ModuleUpdatesWidget extends Widget
{
    protected static string $view = 'filament.widgets.module-updates-widget';

    protected static ?int $sort = 100;

    protected int|string|array $columnSpan = 1;

    public int $updatesCount = 0;

    public function mount(): void
    {
        // Use cached count to avoid hitting GitHub API on every dashboard load
        $this->updatesCount = Cache::remember(
            'module_updates_widget_count',
            now()->addMinutes(30),
            function (): int {
                try {
                    $updateChecker = app(ModuleUpdateCheckerInterface::class);
                    $updates = $updateChecker->checkAllForUpdates();

                    return $updates->count();
                } catch (\Throwable) {
                    return 0;
                }
            }
        );
    }

    public function refreshCount(): void
    {
        Cache::forget('module_updates_widget_count');
        $this->mount();
    }
}
