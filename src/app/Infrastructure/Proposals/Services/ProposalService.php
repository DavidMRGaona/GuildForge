<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Content\Services\RichTextSanitizerInterface;
use App\Application\Proposals\Contracts\ProposalApprovalStepInterface;
use App\Application\Proposals\DTOs\ArticleProposalDataDTO;
use App\Application\Proposals\DTOs\EventProposalDataDTO;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Proposals\Enums\ProposalAuthorAction;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Events\ProposalApproved;
use App\Domain\Proposals\Events\ProposalRejected;
use App\Domain\Proposals\Events\ProposalReturned;
use App\Domain\Proposals\Events\ProposalSubmitted;
use App\Domain\Proposals\Events\ProposalWithdrawn;
use App\Domain\Proposals\Exceptions\InvalidProposalDataException;
use App\Domain\Proposals\Exceptions\InvalidProposalTransitionException;
use App\Domain\Proposals\Exceptions\ProposalLimitReachedException;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Domain\Proposals\Exceptions\ProposalNotFoundException;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final readonly class ProposalService implements ProposalServiceInterface
{
    private const int MAX_TITLE = 255;

    private const int MAX_TEXT = 5000;

    private const int HOUR = 3600;

    public function __construct(
        private ProposalEligibilityServiceInterface $eligibility,
        private ProposalSettingsServiceInterface $settings,
        private AuthorizationServiceInterface $authorization,
        private TimezoneServiceInterface $timezone,
        private RichTextSanitizerInterface $sanitizer,
        private Container $container,
        private EventProposalRequestServiceInterface $requests,
    ) {}

    public function createEvent(string $userId, EventProposalDataDTO $data): string
    {
        $this->assertEligible(ProposalType::Event, $userId);
        $attributes = $this->eventAttributes($data);
        $tagIds = $this->tagIds(ProposalType::Event, $data->tagIds);

        return DB::transaction(function () use ($userId, $attributes, $tagIds, $data): string {
            $this->lockAccount($userId);
            $this->assertDraftsBelowCap($userId);

            $event = new EventModel;
            $event->forceFill([
                ...$attributes,
                'is_published' => false,
                'proposal_status' => ProposalStatus::Draft,
                'proposed_by' => $userId,
            ]);
            $event->save();
            $event->tags()->sync($tagIds);
            $this->saveModuleRequests((string) $event->getKey(), $data);

            return (string) $event->getKey();
        });
    }

    public function updateEvent(string $userId, string $eventId, EventProposalDataDTO $data): void
    {
        $attributes = $this->eventAttributes($data);
        $tagIds = $this->tagIds(ProposalType::Event, $data->tagIds);

        DB::transaction(function () use ($userId, $eventId, $attributes, $tagIds, $data): void {
            $event = $this->authorsProposal(ProposalType::Event, $eventId, $userId, ProposalAuthorAction::Edit, 'edited');

            $event->forceFill($attributes);
            $event->save();
            $event->tags()->sync($tagIds);
            $this->saveModuleRequests($eventId, $data);
        });
    }

    public function createArticle(string $userId, ArticleProposalDataDTO $data): string
    {
        $this->assertEligible(ProposalType::Article, $userId);
        $attributes = $this->articleAttributes($data);
        $tagIds = $this->tagIds(ProposalType::Article, $data->tagIds);

        return DB::transaction(function () use ($userId, $attributes, $tagIds): string {
            $this->lockAccount($userId);
            $this->assertDraftsBelowCap($userId);

            $article = new ArticleModel;
            $article->forceFill([
                ...$attributes,
                'id' => (string) Str::uuid(),
                'slug' => $this->uniqueArticleSlug($attributes['title'], null),
                'is_published' => false,
                'published_at' => null,
                // The displayed author starts as the proposer; the proposal stays theirs if it changes
                'author_id' => $userId,
                'proposal_status' => ProposalStatus::Draft,
                'proposed_by' => $userId,
            ]);
            $article->save();
            $article->tags()->sync($tagIds);

            return (string) $article->getKey();
        });
    }

    public function updateArticle(string $userId, string $articleId, ArticleProposalDataDTO $data): void
    {
        $attributes = $this->articleAttributes($data);
        $tagIds = $this->tagIds(ProposalType::Article, $data->tagIds);

        DB::transaction(function () use ($userId, $articleId, $attributes, $tagIds): void {
            $article = $this->authorsProposal(ProposalType::Article, $articleId, $userId, ProposalAuthorAction::Edit, 'edited');

            if ($article->getAttribute('title') !== $attributes['title']) {
                // Never published, so no address to keep
                $attributes['slug'] = $this->uniqueArticleSlug($attributes['title'], (string) $article->getKey());
            }

            $article->forceFill($attributes);
            $article->save();
            $article->tags()->sync($tagIds);
        });
    }

    public function submit(string $userId, ProposalType $type, string $id): ProposalStatus
    {
        $settings = $this->settings->settings();
        $rateKey = 'proposals-submit:'.$userId;

        DB::transaction(function () use ($userId, $type, $id, $settings, $rateKey): void {
            $this->lockAccount($userId);
            $proposal = $this->authorsProposal($type, $id, $userId, ProposalAuthorAction::Submit, 'submitted');

            if ($this->pendingCountOf($userId) >= $settings->maxPending) {
                throw ProposalLimitReachedException::pending($settings->maxPending);
            }

            if (RateLimiter::tooManyAttempts($rateKey, $settings->submissionsPerHour)) {
                throw ProposalLimitReachedException::hourly($settings->submissionsPerHour);
            }

            $proposal->forceFill([
                'proposal_status' => ProposalStatus::PendingReview,
                'submitted_at' => now(),
            ]);
            $proposal->save();
        });

        RateLimiter::hit($rateKey, self::HOUR);

        $user = UserModel::query()->find($userId);

        if ($user instanceof UserModel && $this->authorization->can($user, $type->publishDirectlyPermission())) {
            try {
                $this->approveProposal($type, $id, null, $userId);

                return ProposalStatus::Approved;
            } catch (Throwable $e) {
                // It stays in review, as anyone else's would
                Log::warning('[Proposals] Publishing a proposal directly failed; it waits for review.', [
                    'type' => $type->value,
                    'id' => $id,
                    'exception' => $e,
                ]);
            }
        }

        $this->dispatchAfterCommit(new ProposalSubmitted($type, $id, $userId));

        return ProposalStatus::PendingReview;
    }

    public function withdraw(string $userId, ProposalType $type, string $id): void
    {
        DB::transaction(function () use ($userId, $type, $id): void {
            $proposal = $this->ownProposal($type, $id, $userId, ProposalAuthorAction::Withdraw, 'withdrawn');

            // The author's again until submitted: out of the panel, like a draft never sent
            $proposal->forceFill([
                'proposal_status' => ProposalStatus::Draft,
                'submitted_at' => null,
            ]);
            $proposal->save();
        });

        $this->dispatchAfterCommit(new ProposalWithdrawn($type, $id, $userId));
    }

    public function delete(string $userId, ProposalType $type, string $id): void
    {
        DB::transaction(function () use ($userId, $type, $id): void {
            $this->ownProposal($type, $id, $userId, ProposalAuthorAction::Delete, 'deleted')->delete();
        });
    }

    public function approve(string $reviewerId, ProposalType $type, string $id): void
    {
        $this->assertCanReview($reviewerId, $type);

        $this->approveProposal($type, $id, $reviewerId, $reviewerId);
    }

    public function returnToDraft(string $reviewerId, ProposalType $type, string $id, string $reason): void
    {
        $this->review($reviewerId, $type, $id, $reason, ProposalStatus::Draft, 'returned');
    }

    public function reject(string $reviewerId, ProposalType $type, string $id, string $reason): void
    {
        $this->review($reviewerId, $type, $id, $reason, ProposalStatus::Rejected, 'rejected');
    }

    public function deleteUnpublishedOf(string $userId): int
    {
        $deleted = 0;

        foreach (ProposalType::cases() as $type) {
            // One by one, so each deletion releases its images and tells its listeners
            foreach ($this->query($type)->unapprovedProposals()->where('proposed_by', $userId)->get() as $proposal) {
                $proposal->delete();
                $deleted++;
            }
        }

        return $deleted;
    }

    public function purgeRejected(): int
    {
        $days = $this->settings->settings()->rejectedRetentionDays;
        $before = $this->timezone->toStorage(now()->subDays($days))->format('Y-m-d H:i:s');
        $deleted = 0;

        foreach (ProposalType::cases() as $type) {
            $rejected = $this->query($type)
                ->includingUnsubmittedProposals()
                ->where('proposal_status', ProposalStatus::Rejected->value)
                ->where('reviewed_at', '<', $before)
                ->get();

            foreach ($rejected as $proposal) {
                $proposal->delete();
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Approves a proposal in review: the approval steps, the status, the reviewer and the
     * publication in one transaction. $reviewerId is null when submitting published it directly.
     */
    private function approveProposal(ProposalType $type, string $id, ?string $reviewerId, string $recordedReviewerId): void
    {
        $proposerId = DB::transaction(function () use ($type, $id, $reviewerId, $recordedReviewerId): ?string {
            $proposal = $this->lockedProposal($type, $id);
            $this->assertStatus($proposal, ProposalStatus::PendingReview, 'approved');

            foreach ($this->container->tagged(ProposalApprovalStepInterface::TAG) as $step) {
                if (! $step instanceof ProposalApprovalStepInterface) {
                    throw new LogicException('Every approval step must implement '.ProposalApprovalStepInterface::class.'.');
                }

                $step->apply($type, $id, $reviewerId);
            }

            $proposal->forceFill([
                'proposal_status' => ProposalStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by' => $recordedReviewerId,
                'is_published' => true,
            ]);

            if ($proposal instanceof ArticleModel) {
                $proposal->setAttribute('published_at', now());
            }

            $proposal->save();

            return $this->stringOrNull($proposal->getAttribute('proposed_by'));
        });

        $this->dispatchAfterCommit(new ProposalApproved($type, $id, $proposerId, $reviewerId, $reviewerId === null));
    }

    private function review(string $reviewerId, ProposalType $type, string $id, string $reason, ProposalStatus $to, string $action): void
    {
        $this->assertCanReview($reviewerId, $type);
        $reason = trim($reason);

        if ($reason === '') {
            throw InvalidProposalTransitionException::reasonRequired();
        }

        if (mb_strlen($reason) > self::MAX_TEXT) {
            throw InvalidProposalDataException::field('reason');
        }

        $proposerId = DB::transaction(function () use ($reviewerId, $type, $id, $reason, $to, $action): ?string {
            $proposal = $this->lockedProposal($type, $id);
            $this->assertStatus($proposal, ProposalStatus::PendingReview, $action);

            // submitted_at stays: a returned draft is still visible to the panel
            $proposal->forceFill([
                'proposal_status' => $to,
                'proposal_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewerId,
            ]);
            $proposal->save();

            return $this->stringOrNull($proposal->getAttribute('proposed_by'));
        });

        $this->dispatchAfterCommit($to === ProposalStatus::Rejected
            ? new ProposalRejected($type, $id, $proposerId, $reviewerId, $reason)
            : new ProposalReturned($type, $id, $proposerId, $reviewerId, $reason));
    }

    /**
     * What the author asks the modules for, with the draft (core#581). A form that sends nothing
     * about them leaves the stored ones as they are.
     */
    private function saveModuleRequests(string $eventId, EventProposalDataDTO $data): void
    {
        if ($data->moduleRequests !== null) {
            $this->requests->saveRequested($eventId, $data->moduleRequests);
        }
    }

    private function assertEligible(ProposalType $type, string $userId): void
    {
        $eligibility = $this->eligibility->check($type, $userId);

        if (! $eligibility->allowed) {
            throw ProposalNotAllowedException::because($eligibility->reason ?? 'denied');
        }
    }

    private function assertCanReview(string $reviewerId, ProposalType $type): void
    {
        $reviewer = Str::isUuid($reviewerId) ? UserModel::query()->find($reviewerId) : null;

        if (! $reviewer instanceof UserModel || ! $this->authorization->can($reviewer, $type->reviewPermission())) {
            throw ProposalNotAllowedException::because('review_denied');
        }
    }

    /**
     * The author's proposal for an action that needs them to be eligible (edit, submit).
     */
    private function authorsProposal(ProposalType $type, string $id, string $userId, ProposalAuthorAction $action, string $verb): EventModel|ArticleModel
    {
        $proposal = $this->lockedProposal($type, $id);
        $this->assertAuthor($proposal, $userId);
        $this->assertEligible($type, $userId);
        $this->assertAuthorMay($proposal, $action, $verb);

        return $proposal;
    }

    /**
     * The author's proposal for an action that only takes something back (withdraw, delete): an
     * active account is enough, eligibility is not needed.
     */
    private function ownProposal(ProposalType $type, string $id, string $userId, ProposalAuthorAction $action, string $verb): EventModel|ArticleModel
    {
        $proposal = $this->lockedProposal($type, $id);
        $this->assertAuthor($proposal, $userId);

        $user = UserModel::query()->find($userId);
        if (! $user instanceof UserModel || $user->anonymized_at !== null) {
            throw ProposalNotAllowedException::because('account_inactive');
        }

        $this->assertAuthorMay($proposal, $action, $verb);

        return $proposal;
    }

    private function assertAuthor(EventModel|ArticleModel $proposal, string $userId): void
    {
        if ($proposal->getAttribute('proposed_by') !== $userId) {
            throw ProposalNotAllowedException::because('not_author');
        }
    }

    private function assertAuthorMay(EventModel|ArticleModel $proposal, ProposalAuthorAction $action, string $verb): void
    {
        $status = $proposal->proposalStatus();

        if (! ProposalStatus::allowsAuthor($status, $action)) {
            throw InvalidProposalTransitionException::from($status ?? ProposalStatus::Approved, $verb);
        }
    }

    private function assertStatus(EventModel|ArticleModel $proposal, ProposalStatus $expected, string $verb): void
    {
        $status = $proposal->proposalStatus();

        if ($status !== $expected) {
            throw InvalidProposalTransitionException::from($status ?? ProposalStatus::Approved, $verb);
        }
    }

    /**
     * A proposal of the type, locked for the transaction. Content created in the panel is not one.
     */
    private function lockedProposal(ProposalType $type, string $id): EventModel|ArticleModel
    {
        // PostgreSQL rejects a malformed uuid instead of finding nothing
        $proposal = Str::isUuid($id)
            ? $this->query($type)
                ->includingUnsubmittedProposals()
                ->whereNotNull('proposal_status')
                ->whereKey($id)
                ->lockForUpdate()
                ->first()
            : null;

        if (! $proposal instanceof EventModel && ! $proposal instanceof ArticleModel) {
            throw ProposalNotFoundException::withId($id);
        }

        return $proposal;
    }

    /**
     * Serializes the caps of one person: concurrent requests cannot both pass a count.
     */
    private function lockAccount(string $userId): void
    {
        UserModel::query()->whereKey($userId)->lockForUpdate()->first();
    }

    private function assertDraftsBelowCap(string $userId): void
    {
        $max = $this->settings->settings()->maxOpenDrafts;

        if ($this->countOf($userId, ProposalStatus::Draft) >= $max) {
            throw ProposalLimitReachedException::drafts($max);
        }
    }

    private function pendingCountOf(string $userId): int
    {
        return $this->countOf($userId, ProposalStatus::PendingReview);
    }

    private function countOf(string $userId, ProposalStatus $status): int
    {
        $count = 0;

        foreach (ProposalType::cases() as $type) {
            $count += $this->query($type)
                ->includingUnsubmittedProposals()
                ->where('proposed_by', $userId)
                ->where('proposal_status', $status->value)
                ->count();
        }

        return $count;
    }

    /**
     * @return Builder<EventModel>|Builder<ArticleModel>
     */
    private function query(ProposalType $type): Builder
    {
        return match ($type) {
            ProposalType::Event => EventModel::query(),
            ProposalType::Article => ArticleModel::query(),
        };
    }

    /**
     * @return array{title: string, description: string, start_date: \DateTimeImmutable, end_date: \DateTimeImmutable, location: ?string, image_public_id: ?string, proposal_note: ?string}
     */
    private function eventAttributes(EventProposalDataDTO $data): array
    {
        if ($data->endDate < $data->startDate) {
            throw InvalidProposalDataException::field('dates');
        }

        return [
            'title' => $this->title($data->title),
            'description' => $this->sanitizer->sanitize($data->description, EditorImageOrigin::Events),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'location' => $this->optionalText($data->location, self::MAX_TITLE, 'location'),
            'image_public_id' => $this->image($data->imagePublicId, ProposalType::Event),
            'proposal_note' => $this->optionalText($data->note, self::MAX_TEXT, 'note'),
        ];
    }

    /**
     * @return array{title: string, content: string, excerpt: ?string, featured_image_public_id: ?string, proposal_note: ?string}
     */
    private function articleAttributes(ArticleProposalDataDTO $data): array
    {
        $content = $this->sanitizer->sanitize($data->content, EditorImageOrigin::Articles);

        if (trim(strip_tags($content)) === '' && ! str_contains($content, '<img')) {
            throw InvalidProposalDataException::field('content');
        }

        return [
            'title' => $this->title($data->title),
            'content' => $content,
            'excerpt' => $this->optionalText($data->excerpt, self::MAX_TITLE, 'excerpt'),
            'featured_image_public_id' => $this->image($data->featuredImagePublicId, ProposalType::Article),
            'proposal_note' => $this->optionalText($data->note, self::MAX_TEXT, 'note'),
        ];
    }

    private function title(string $title): string
    {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > self::MAX_TITLE) {
            throw InvalidProposalDataException::field('title');
        }

        return $title;
    }

    private function optionalText(?string $value, int $max, string $field): ?string
    {
        $value = $value === null ? '' : trim($value);

        if (mb_strlen($value) > $max) {
            throw InvalidProposalDataException::field($field);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Only an image in the type's own folder, which its media source claims: never someone else's file.
     */
    private function image(?string $path, ProposalType $type): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (preg_match('#^'.$type->value.'/[A-Za-z0-9_-][A-Za-z0-9/_.-]*$#', $path) !== 1 || str_contains($path, '..')) {
            throw InvalidProposalDataException::field('image');
        }

        return $path;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function tagIds(ProposalType $type, array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids), Str::isUuid(...)));

        if ($ids === []) {
            return [];
        }

        /** @var list<string> $existing */
        $existing = TagModel::query()->forType($type->value)->whereIn('id', $ids)->pluck('id')->all();

        return $existing;
    }

    /**
     * Unique among every article, drafts hidden from other queries included.
     */
    private function uniqueArticleSlug(string $title, ?string $exceptId): string
    {
        $base = Str::slug($title);
        $base = $base === '' ? 'articulo' : $base;
        $slug = $base;
        $counter = 2;

        while (ArticleModel::query()->withoutGlobalScopes()
            ->where('slug', $slug)
            ->when($exceptId !== null, static fn (Builder $query): Builder => $query->whereKeyNot($exceptId))
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function dispatchAfterCommit(object $event): void
    {
        DB::afterCommit(static fn () => Event::dispatch($event));
    }
}
