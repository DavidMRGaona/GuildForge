<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Catalog;

use App\Domain\Knowledge\Entities\KnowledgeCategory;
use App\Domain\Knowledge\Enums\GuideAudience;

final class CoreKnowledgeCategories
{
    /**
     * @return list<KnowledgeCategory>
     */
    public static function all(): array
    {
        $definitions = [
            ['getting-started', GuideAudience::Site, 'heroicon-o-rocket-launch'],
            ['account', GuideAudience::Site, 'heroicon-o-user-circle'],
            ['events-and-games', GuideAudience::Site, 'heroicon-o-calendar-days'],
            ['campaigns', GuideAudience::Site, 'heroicon-o-map'],
            ['tournaments', GuideAudience::Site, 'heroicon-o-trophy'],
            ['membership', GuideAudience::Site, 'heroicon-o-identification'],
            ['venue', GuideAudience::Site, 'heroicon-o-building-storefront'],
            ['community', GuideAudience::Site, 'heroicon-o-user-group'],
            ['panel-basics', GuideAudience::Panel, 'heroicon-o-squares-2x2'],
            ['content', GuideAudience::Panel, 'heroicon-o-document-text'],
            ['users-and-roles', GuideAudience::Panel, 'heroicon-o-users'],
            ['site-settings', GuideAudience::Panel, 'heroicon-o-cog-6-tooth'],
            ['modules', GuideAudience::Panel, 'heroicon-o-puzzle-piece'],
            ['activities', GuideAudience::Panel, 'heroicon-o-ticket'],
        ];

        $categories = [];
        foreach ($definitions as $index => [$id, $audience, $icon]) {
            $categories[] = new KnowledgeCategory(
                id: $id,
                module: null,
                audience: $audience,
                order: ($index + 1) * 10,
                icon: $icon,
                labelKey: 'knowledge.categories.'.$id,
            );
        }

        return $categories;
    }
}
