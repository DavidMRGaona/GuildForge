<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Scopes;

use App\Domain\Proposals\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Keeps proposal drafts never sent to review (no submitted_at) out of every query of events and
 * articles: the panel, its widgets, module selectors and relations. They belong to their author
 * until submitted. Only the proposals flow removes it, explicitly, with
 * includingUnsubmittedProposals() (and usage checks that must see every row, such as the media
 * library's). Returned drafts (with submitted_at) stay visible.
 *
 * @implements Scope<Model>
 */
final class HidesUnsubmittedProposals implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $status = $model->qualifyColumn('proposal_status');
        $submittedAt = $model->qualifyColumn('submitted_at');

        $builder->where(static function (Builder $query) use ($status, $submittedAt): void {
            $query->whereNull($status)
                ->orWhere($status, '!=', ProposalStatus::Draft->value)
                ->orWhereNotNull($submittedAt);
        });
    }
}
