<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use Throwable;

/**
 * Where a panel help button leads: the guide in the help page, only when the signed-in user may
 * see it. Fails closed, since a help button is never worth an error page.
 */
final class PanelGuideUrl
{
    public static function for(string $guideId): ?string
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

        try {
            $guide = app(GuideVisibilityServiceInterface::class)->findVisible($user, $id, GuideAudience::Panel);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $guide instanceof Guide ? KnowledgeBasePage::getUrl(['guia' => $id->value()]) : null;
    }
}
