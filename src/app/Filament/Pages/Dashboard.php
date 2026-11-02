<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

final class Dashboard extends BaseDashboard
{
    use HasKnowledgeGuideAction;

    /**
     * Get the widgets for the dashboard, filtered and sorted by admin configuration.
     *
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $widgets = parent::getWidgets();
        $configService = app(DashboardWidgetConfigServiceInterface::class);
        $config = $configService->getConfig();

        // If no config exists, use default behavior
        if ($config === []) {
            return $widgets;
        }

        // Filter disabled widgets
        $widgets = array_filter(
            $widgets,
            static function (string|WidgetConfiguration $widget) use ($configService): bool {
                $class = $widget instanceof WidgetConfiguration ? $widget->widget : $widget;

                return $configService->isEnabled($class);
            },
        );

        // Re-sort by configured order
        usort($widgets, static function (string|WidgetConfiguration $a, string|WidgetConfiguration $b) use ($configService): int {
            $classA = $a instanceof WidgetConfiguration ? $a->widget : $a;
            $classB = $b instanceof WidgetConfiguration ? $b->widget : $b;
            $sortA = $configService->getSort($classA, $classA::getSort());
            $sortB = $configService->getSort($classB, $classB::getSort());

            return $sortA <=> $sortB;
        });

        return $widgets;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([$this->knowledgeGuideAction('core.panel.tour')]);
    }
}
