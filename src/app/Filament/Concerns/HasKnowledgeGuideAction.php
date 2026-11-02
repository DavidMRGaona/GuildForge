<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\PanelGuideUrl;
use Filament\Actions\Action;

/**
 * Adds a header action that opens a panel help guide, only when the signed-in user may see it.
 */
trait HasKnowledgeGuideAction
{
    protected function knowledgeGuideAction(string $guideId): ?Action
    {
        $url = PanelGuideUrl::for($guideId);
        if ($url === null) {
            return null;
        }

        return Action::make('contextualHelp')
            ->label(__('knowledge.contextual_help'))
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->url($url);
    }
}
