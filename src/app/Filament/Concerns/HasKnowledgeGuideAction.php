<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use Filament\Actions\Action;

/**
 * Adds a header action that opens a panel help guide, only when the signed-in user may see it.
 */
trait HasKnowledgeGuideAction
{
    protected function knowledgeGuideAction(string $guideId): ?Action
    {
        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        try {
            $id = GuideId::fromString($guideId);
        } catch (InvalidGuideIdException) {
            return null;
        }

        if (! app(GuideVisibilityServiceInterface::class)->findVisible($user, $id, GuideAudience::Panel) instanceof Guide) {
            return null;
        }

        return Action::make('contextualHelp')
            ->label(__('knowledge.contextual_help'))
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->url(KnowledgeBasePage::getUrl(['guia' => $id->value()]));
    }
}
