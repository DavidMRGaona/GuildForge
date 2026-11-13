<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\ValueObjects\ProposalReview;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Scopes\HidesUnsubmittedProposals;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Review columns of content that can be proposed from the web (events and articles, core#570).
 *
 * - Drafts never submitted are hidden from every query (HidesUnsubmittedProposals); the proposals
 *   flow sees them through includingUnsubmittedProposals().
 * - An unapproved proposal is never stored as published, whoever saves it (a forged panel toggle,
 *   code or a module): every public output reads is_published, so this is what keeps proposals
 *   out of listings, details, search, calendars, feeds, the sitemap and publication listeners.
 *
 * The review columns are not fillable: only the proposals flow writes them, with forceFill().
 */
trait HasProposalReview
{
    public static function bootHasProposalReview(): void
    {
        static::addGlobalScope(new HidesUnsubmittedProposals);

        static::saving(static function (self $model): void {
            if (ProposalStatus::isUnapproved($model->proposalStatus())) {
                $model->setAttribute('is_published', false);
                $model->keepProposalUnpublished();
            }
        });
    }

    /**
     * Anything else that publishes the row besides is_published, cleared while it is unapproved.
     */
    protected function keepProposalUnpublished(): void {}

    public function proposalStatus(): ?ProposalStatus
    {
        $status = $this->getAttribute('proposal_status');

        return $status instanceof ProposalStatus ? $status : null;
    }

    /**
     * The review data, or null for content created in the panel.
     */
    public function proposalReview(): ?ProposalReview
    {
        $status = $this->proposalStatus();

        if (! $status instanceof ProposalStatus) {
            return null;
        }

        return new ProposalReview(
            status: $status,
            proposedBy: $this->stringAttribute('proposed_by'),
            note: $this->stringAttribute('proposal_note'),
            reason: $this->stringAttribute('proposal_reason'),
            submittedAt: $this->instantAttribute('submitted_at'),
            reviewedAt: $this->instantAttribute('reviewed_at'),
            reviewedBy: $this->stringAttribute('reviewed_by'),
        );
    }

    /**
     * Drafts never submitted included: for the proposals flow only.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeIncludingUnsubmittedProposals(Builder $query): Builder
    {
        return $query->withoutGlobalScope(HidesUnsubmittedProposals::class);
    }

    /**
     * Proposals not approved yet (drafts, in review or rejected), unsubmitted drafts included.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnapprovedProposals(Builder $query): Builder
    {
        return $query->includingUnsubmittedProposals()
            ->whereIn($this->qualifyColumn('proposal_status'), ProposalStatus::unapprovedValues());
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'proposed_by');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'reviewed_by');
    }

    private function stringAttribute(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) ? $value : null;
    }

    private function instantAttribute(string $key): ?DateTimeImmutable
    {
        $value = $this->getAttribute($key);

        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null;
    }
}
