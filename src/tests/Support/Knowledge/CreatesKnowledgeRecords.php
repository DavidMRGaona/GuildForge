<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideAliasModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;

/**
 * Inserts catalog rows directly, for tests that exercise the read side.
 */
trait CreatesKnowledgeRecords
{
    protected function knowledgeCategory(string $id, string $audience = 'site', int $order = 10): void
    {
        KnowledgeCategoryModel::query()->create([
            'category_id' => $id,
            'module' => null,
            'audience' => $audience,
            'sort_order' => $order,
            'icon' => 'heroicon-o-rocket-launch',
            'label_key' => 'knowledge.categories.'.$id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function knowledgeGuide(string $guideId, array $overrides = []): KnowledgeGuideModel
    {
        return KnowledgeGuideModel::query()->create([
            'guide_id' => $guideId,
            'locale' => 'es',
            'source' => 'core',
            'module' => null,
            'audience' => 'site',
            'category_id' => 'getting-started',
            'sort_order' => 10,
            'title' => 'Guide '.$guideId,
            'summary' => 'Summary',
            'keywords' => [],
            'body_format' => 'markdown',
            'body' => 'Body of the guide.',
            'body_text' => 'Body of the guide.',
            'conditions' => [],
            'related' => [],
            'featured' => false,
            'content_hash' => str_repeat('a', 40),
            'active' => true,
            ...$overrides,
        ]);
    }

    protected function knowledgeAlias(string $alias, string $guideId): void
    {
        KnowledgeGuideAliasModel::query()->create(['alias_id' => $alias, 'guide_id' => $guideId]);
    }
}
