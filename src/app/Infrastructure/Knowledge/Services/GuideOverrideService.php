<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Knowledge\Services\GuideOverrideServiceInterface;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use Illuminate\Support\Facades\DB;

/**
 * One row per overridden guide (every locale at once); a row that changes nothing is removed.
 * Visibility reads the overrides on every request, so the search index needs no update.
 */
final readonly class GuideOverrideService implements GuideOverrideServiceInterface
{
    public function setHidden(GuideId $id, bool $hidden): void
    {
        $this->write($id, static fn (KnowledgeGuideOverrideModel $override): bool => $override->hidden = $hidden);
    }

    public function setFeatured(GuideId $id, ?bool $featured): void
    {
        $this->write($id, static fn (KnowledgeGuideOverrideModel $override): ?bool => $override->featured = $featured);
    }

    /**
     * @param  callable(KnowledgeGuideOverrideModel): mixed  $change
     */
    private function write(GuideId $id, callable $change): void
    {
        $official = ! $id->isSite() && KnowledgeGuideModel::query()
            ->where('guide_id', $id->value())
            ->where('source', '!=', GuideSource::Site->value)
            ->exists();
        if (! $official) {
            throw SiteGuideException::notAnOfficialGuide($id->value());
        }

        DB::transaction(static function () use ($id, $change): void {
            $override = KnowledgeGuideOverrideModel::query()->lockForUpdate()->firstOrNew(['guide_id' => $id->value()]);
            if (! $override->exists) {
                $override->hidden = false;
                $override->featured = null;
            }

            $change($override);

            if (! $override->hidden && $override->featured === null) {
                if ($override->exists) {
                    $override->delete();
                }

                return;
            }

            $override->save();
        });
    }
}
