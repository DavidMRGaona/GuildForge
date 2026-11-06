<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Knowledge\DTOs\SiteGuideDTO;
use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Application\Knowledge\Services\SiteGuideServiceInterface;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideAliasModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The site's guides are rows of the catalog with source `site`, in the fallback locale (the only one
 * the help centre is written in). Draft or published is the row's `active` flag.
 */
final readonly class SiteGuideService implements SiteGuideServiceInterface
{
    private const int SLUG_LENGTH = 60;

    /** Same limits as the panel form; the title column holds 255 */
    public const int TITLE_MAX = 120;

    public const int SUMMARY_MAX = 300;

    private const string EMPTY_SLUG = 'guia';

    public function __construct(
        private GuideRepositoryInterface $guides,
        private GuideRendererInterface $renderer,
        private PermissionRegistryInterface $permissions,
        private KnowledgeCapabilityRegistryInterface $capabilities,
        private KnowledgeIndexerInterface $indexer,
    ) {}

    public function create(SiteGuideDTO $guide): GuideId
    {
        $this->validate($guide, null);

        $id = DB::transaction(function () use ($guide): GuideId {
            $id = $this->newId($guide->title);

            KnowledgeGuideModel::query()->create([
                ...$this->attributes($guide),
                'guide_id' => $id->value(),
                'locale' => $this->locale(),
                'source' => GuideSource::Site->value,
                'module' => null,
                'first_seen_at' => $guide->published ? now() : null,
                'content_changed_at' => null,
            ]);

            return $id;
        });

        $this->indexer->refresh($id);

        return $id;
    }

    public function update(GuideId $id, SiteGuideDTO $guide): void
    {
        $model = $this->siteGuide($id);
        $this->validate($guide, $id);

        DB::transaction(function () use ($model, $guide): void {
            $attributes = $this->attributes($guide);

            if ($guide->published && $model->first_seen_at === null) {
                $attributes['first_seen_at'] = now();
            } elseif ($guide->published && $model->content_hash !== $attributes['content_hash']) {
                $attributes['content_changed_at'] = now();
            }

            $model->update($attributes);
        });

        $this->indexer->refresh($id);
    }

    public function delete(GuideId $id): void
    {
        $this->siteGuide($id);
        $this->indexer->forget($id);

        DB::transaction(static function () use ($id): void {
            KnowledgeGuideModel::query()->where('guide_id', $id->value())->delete();
            KnowledgeGuideOverrideModel::query()->where('guide_id', $id->value())->delete();
            KnowledgeGuideAliasModel::query()
                ->where('guide_id', $id->value())
                ->orWhere('alias_id', $id->value())
                ->delete();
            DB::table('knowledge_guide_feedback')->where('guide_id', $id->value())->delete();
        });
    }

    private function siteGuide(GuideId $id): KnowledgeGuideModel
    {
        $model = KnowledgeGuideModel::query()
            ->where('guide_id', $id->value())
            ->where('source', GuideSource::Site->value)
            ->first();

        if (! $id->isSite() || $model === null) {
            throw SiteGuideException::notASiteGuide($id->value());
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SiteGuideDTO $guide): array
    {
        $content = [
            'audience' => $guide->audience->value,
            'category_id' => $guide->categoryId,
            'sort_order' => $guide->order,
            'title' => trim($guide->title),
            'summary' => trim($guide->summary),
            'keywords' => array_values(array_unique(array_filter(array_map(trim(...), $guide->keywords), static fn (string $keyword): bool => $keyword !== ''))),
            'body_format' => GuideBodyFormat::Html->value,
            'body' => $guide->body,
            'conditions' => $guide->conditions->toArray(),
            'related' => array_map(static fn (GuideId $related): string => $related->value(), $guide->related),
            'featured' => $guide->featured,
        ];

        return [
            ...$content,
            'body_text' => $this->plainText($guide->body),
            'content_hash' => sha1((string) json_encode($content)),
            'active' => $guide->published,
        ];
    }

    private function validate(SiteGuideDTO $guide, ?GuideId $self): void
    {
        if (trim($guide->title) === '') {
            throw SiteGuideException::missingText('title');
        }
        if (trim($guide->summary) === '') {
            throw SiteGuideException::missingText('summary');
        }
        if (mb_strlen(trim($guide->title)) > self::TITLE_MAX) {
            throw SiteGuideException::textTooLong('title', self::TITLE_MAX);
        }
        if (mb_strlen(trim($guide->summary)) > self::SUMMARY_MAX) {
            throw SiteGuideException::textTooLong('summary', self::SUMMARY_MAX);
        }

        $categoryExists = false;
        foreach ($this->guides->categories($guide->audience) as $category) {
            $categoryExists = $categoryExists || $category->id === $guide->categoryId;
        }
        if (! $categoryExists) {
            throw SiteGuideException::unknownCategory($guide->categoryId, $guide->audience->value);
        }

        $conditions = $guide->conditions;
        foreach ([...$conditions->permissionsAll, ...$conditions->permissionsAny] as $permission) {
            if (! $this->permissions->has($permission)) {
                throw SiteGuideException::unknownCondition('permission', $permission);
            }
        }
        $capabilities = $this->capabilities->keys();
        foreach ($conditions->capabilityKeys() as $capability) {
            if (! in_array($capability, $capabilities, true)) {
                throw SiteGuideException::unknownCondition('capability', $capability);
            }
        }
        // A site guide has no module of its own; a capability of a disabled module already fails closed
        if ($conditions->modules !== []) {
            throw SiteGuideException::unknownCondition('module', $conditions->modules[0]);
        }

        foreach ($guide->related as $related) {
            $exists = KnowledgeGuideModel::query()->where('guide_id', $related->value())->exists();
            if (! $exists || ($self instanceof GuideId && $self->equals($related))) {
                throw SiteGuideException::invalidRelated($related->value());
            }
        }
    }

    private function newId(string $title): GuideId
    {
        $slug = rtrim(Str::limit(Str::slug($title, '-', 'es'), self::SLUG_LENGTH, ''), '-');
        $base = GuideId::SITE_PREFIX.'.'.($slug !== '' ? $slug : self::EMPTY_SLUG);

        $candidate = $base;
        for ($suffix = 2; $this->taken($candidate); $suffix++) {
            $candidate = $base.'-'.$suffix;
        }

        return GuideId::fromString($candidate);
    }

    private function taken(string $id): bool
    {
        return KnowledgeGuideModel::query()->where('guide_id', $id)->exists()
            || KnowledgeGuideAliasModel::query()->where('alias_id', $id)->exists();
    }

    private function plainText(string $body): string
    {
        $previous = App::getLocale();
        App::setLocale($this->locale());

        try {
            return $this->renderer->toPlainText($body, GuideBodyFormat::Html);
        } finally {
            App::setLocale($previous);
        }
    }

    private function locale(): string
    {
        $locale = config('knowledge.fallback_locale');

        return is_string($locale) && $locale !== '' ? $locale : 'es';
    }
}
