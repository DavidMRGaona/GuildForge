<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence\Eloquent\Repositories;

use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Entities\KnowledgeCategory;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideAliasModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentGuideRepository implements GuideRepositoryInterface
{
    public function allActive(GuideAudience $audience, string $locale): array
    {
        $models = $this->activeQuery($locale)
            ->where('audience', $audience->value)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $guideIds = [];
        foreach ($models as $model) {
            $guideIds[] = $model->guide_id;
        }
        $overrides = $this->overridesFor($guideIds);

        $guides = [];
        foreach ($models as $model) {
            $guides[] = $this->toDomain($model, $overrides[$model->guide_id] ?? null);
        }

        return $guides;
    }

    public function findActive(GuideId $id, string $locale): ?Guide
    {
        $model = $this->activeQuery($locale)->where('guide_id', $id->value())->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model, KnowledgeGuideOverrideModel::query()->where('guide_id', $model->guide_id)->first());
    }

    public function resolveAlias(GuideId $alias): ?GuideId
    {
        $model = KnowledgeGuideAliasModel::query()->where('alias_id', $alias->value())->first();

        return $model !== null ? GuideId::fromString($model->guide_id) : null;
    }

    public function categories(GuideAudience $audience): array
    {
        $models = KnowledgeCategoryModel::query()
            ->where('audience', $audience->value)
            ->orderBy('sort_order')
            ->orderBy('category_id')
            ->get();

        $categories = [];
        foreach ($models as $model) {
            $categories[] = new KnowledgeCategory(
                id: $model->category_id,
                module: $model->module,
                audience: GuideAudience::from($model->audience),
                order: $model->sort_order,
                icon: $model->icon,
                labelKey: $model->label_key,
            );
        }

        return $categories;
    }

    public function hasLocale(string $locale): bool
    {
        return $this->activeQuery($locale)->exists();
    }

    /**
     * @return Builder<KnowledgeGuideModel>
     */
    private function activeQuery(string $locale): Builder
    {
        return KnowledgeGuideModel::query()->where('active', true)->where('locale', $locale);
    }

    /**
     * @param  list<string>  $guideIds
     * @return array<string, KnowledgeGuideOverrideModel>
     */
    private function overridesFor(array $guideIds): array
    {
        if ($guideIds === []) {
            return [];
        }

        return KnowledgeGuideOverrideModel::query()
            ->whereIn('guide_id', $guideIds)
            ->get()
            ->keyBy('guide_id')
            ->all();
    }

    private function toDomain(KnowledgeGuideModel $model, ?KnowledgeGuideOverrideModel $override): Guide
    {
        return new Guide(
            id: GuideId::fromString($model->guide_id),
            source: GuideSource::from($model->source),
            module: $model->module,
            locale: $model->locale,
            audience: GuideAudience::from($model->audience),
            categoryId: $model->category_id,
            order: $model->sort_order,
            title: $model->title,
            summary: $model->summary,
            keywords: $model->keywords,
            bodyFormat: GuideBodyFormat::from($model->body_format),
            body: $model->body,
            bodyText: $model->body_text,
            conditions: GuideConditions::fromArray($model->conditions),
            related: array_map(GuideId::fromString(...), $model->related),
            featured: $model->featured || ($override->featured ?? false),
            hidden: $override->hidden ?? false,
            firstSeenAt: $model->first_seen_at,
            contentChangedAt: $model->content_changed_at,
        );
    }
}
