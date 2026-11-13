<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Proposals\DTOs\OwnProposalDTO;
use App\Application\Proposals\DTOs\StoredProposalRequestDTO;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Proposals\Enums\ProposalAuthorAction;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A proposal for its author: status, dates, reason and what they may still do (the same rule the
 * service applies, so a button never offers what the server refuses).
 *
 * @property OwnProposalDTO $resource
 */
final class OwnProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(TimezoneServiceInterface::class);
        $proposal = $this->resource;
        $isEvent = $proposal->type === ProposalType::Event;

        return [
            'id' => $proposal->id,
            'type' => $isEvent ? 'event' : 'article',
            'status' => $proposal->status->value,
            'title' => $proposal->title,
            'body' => $proposal->body,
            'excerpt' => $proposal->excerpt,
            'location' => $proposal->location,
            'startDate' => $proposal->startDate !== null ? $timezone->toDisplayWallTime($proposal->startDate, 'Y-m-d') : null,
            'startTime' => $proposal->startDate !== null ? $timezone->toDisplayWallTime($proposal->startDate, 'H:i') : null,
            'endDate' => $proposal->endDate !== null ? $timezone->toDisplayWallTime($proposal->endDate, 'Y-m-d') : null,
            'endTime' => $proposal->endDate !== null ? $timezone->toDisplayWallTime($proposal->endDate, 'H:i') : null,
            'startsAt' => $timezone->iso($proposal->startDate),
            'imagePublicId' => $proposal->imagePublicId,
            'tagIds' => $proposal->tagIds,
            'note' => $proposal->note,
            'reason' => $proposal->reason,
            'submittedAt' => $timezone->iso($proposal->submittedAt),
            'reviewedAt' => $timezone->iso($proposal->reviewedAt),
            'updatedAt' => $timezone->iso($proposal->updatedAt),
            'publicPath' => $proposal->status === ProposalStatus::Approved && $proposal->slug !== null
                ? ($isEvent ? '/eventos/' : '/articulos/').$proposal->slug
                : null,
            // What the author asked the modules for: the values to edit and the module's line for them
            'requests' => array_map(static fn (StoredProposalRequestDTO $request): array => [
                'key' => $request->key,
                'label' => $request->label,
                'active' => $request->active,
                'values' => $request->requestedValues,
                'description' => $request->description,
                'applied' => $request->appliedAt instanceof \DateTimeImmutable,
            ], $proposal->requests),
            'can' => [
                'edit' => ProposalStatus::allowsAuthor($proposal->status, ProposalAuthorAction::Edit),
                'submit' => ProposalStatus::allowsAuthor($proposal->status, ProposalAuthorAction::Submit),
                'withdraw' => ProposalStatus::allowsAuthor($proposal->status, ProposalAuthorAction::Withdraw),
                'delete' => ProposalStatus::allowsAuthor($proposal->status, ProposalAuthorAction::Delete),
            ],
        ];
    }
}
