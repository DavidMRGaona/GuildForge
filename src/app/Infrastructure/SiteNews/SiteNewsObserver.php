<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNews;

use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Domain\SiteNews\SiteNewsKind;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\SiteNews\Jobs\AnnounceSiteNewsJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Announces the first publication of an event, an article or a gallery in everybody's bell
 * (core#613), whoever publishes it: the panel, an approved proposal or code. Only the first one:
 * taking it down and publishing it again, or editing it, says nothing. An event that has already
 * ended is no news and is not marked, so nothing is announced for it.
 *
 * The mark is written in the transaction of the save and the notices are queued once it commits,
 * so a rolled back publication neither announces nor uses up the announcement.
 */
final readonly class SiteNewsObserver
{
    public function __construct(
        private SiteNewsAnnouncements $announcements,
        private MailSubjectMuteServiceInterface $subjects,
    ) {}

    public function saved(Model $model): void
    {
        $kind = $this->kindOf($model);

        if (! $kind instanceof SiteNewsKind || $model->getAttribute('is_published') !== true) {
            return;
        }

        if (! $model->wasRecentlyCreated && ! $model->wasChanged('is_published')) {
            return;
        }

        if ($model instanceof EventModel && $model->end_date->isPast()) {
            return;
        }

        $id = (string) $model->getKey();

        if (! $this->announcements->claim($kind, $id)) {
            return;
        }

        $excluded = $this->alreadyTold($model);

        DB::afterCommit(static function () use ($kind, $id, $excluded): void {
            try {
                AnnounceSiteNewsJob::dispatch($kind, $id, $excluded);
            } catch (Throwable $exception) {
                // The publication stands; only its notices are lost
                Log::warning('The news of a publication could not be queued.', ['kind' => $kind->value, 'id' => $id, 'exception' => $exception->getMessage()]);
            }
        });
    }

    public function deleted(Model $model): void
    {
        $kind = $this->kindOf($model);

        if (! $kind instanceof SiteNewsKind) {
            return;
        }

        $id = (string) $model->getKey();

        // Never announced: there are no notices to forget
        if (! $this->announcements->forget($kind, $id)) {
            return;
        }

        DB::afterCommit(function () use ($kind, $id): void {
            try {
                $this->subjects->forgetSubject($kind->subjectType(), $id);
            } catch (Throwable $exception) {
                // The content is gone either way: only notices linking to it stay behind
                Log::warning('The news notices of deleted content could not be forgotten.', ['kind' => $kind->value, 'id' => $id, 'exception' => $exception->getMessage()]);
            }
        });
    }

    private function kindOf(Model $model): ?SiteNewsKind
    {
        return match (true) {
            $model instanceof EventModel => SiteNewsKind::Event,
            $model instanceof ArticleModel => SiteNewsKind::Article,
            $model instanceof GalleryModel => SiteNewsKind::Gallery,
            default => null,
        };
    }

    /**
     * Who publishes it knows, and who proposed it hears of the approval in its own notice.
     *
     * @return list<string>
     */
    private function alreadyTold(Model $model): array
    {
        $ids = [Auth::id(), $model->getAttribute('proposed_by')];

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): ?string => is_string($id) && $id !== '' ? $id : null, $ids),
            static fn (?string $id): bool => $id !== null,
        )));
    }
}
