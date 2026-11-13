<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proposals;

use App\Application\Proposals\DTOs\OwnProposalDTO;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\ProposalException;
use App\Http\Requests\Proposals\EventProposalRequest;
use App\Http\Resources\ProposalRequestDefinitionResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The web form that proposes an event: create it, edit the draft, save it or send it to review
 * (core#579). Who may propose is checked by the request and again by the service.
 */
final class EventProposalController extends ProposalFormController
{
    public function create(Request $request): Response
    {
        $this->assertMayPropose($request);

        return $this->formPage($request, null);
    }

    public function store(EventProposalRequest $request): RedirectResponse
    {
        try {
            $id = $this->proposals->createEvent(
                $this->userId($request),
                $request->toDto($this->timezone, $this->storeImage($request->file('image'))),
            );
        } catch (ProposalException $exception) {
            return $this->refusal($exception);
        }

        return $this->afterSaving($request, $id, $request->wantsToSubmit());
    }

    public function edit(Request $request, string $id): Response|RedirectResponse
    {
        $this->assertMayPropose($request);
        $proposal = $this->editable($request, $id);

        return $proposal instanceof OwnProposalDTO ? $this->formPage($request, $proposal) : $proposal;
    }

    public function update(EventProposalRequest $request, string $id): RedirectResponse
    {
        $current = $this->editable($request, $id);

        if (! $current instanceof OwnProposalDTO) {
            return $current;
        }

        $image = match (true) {
            $request->hasFile('image') => $this->storeImage($request->file('image')),
            $request->removesImage() => null,
            default => $current->imagePublicId,
        };

        try {
            $this->proposals->updateEvent($this->userId($request), $id, $request->toDto($this->timezone, $image));
        } catch (ProposalException $exception) {
            return $this->refusal($exception);
        }

        return $this->afterSaving($request, $id, $request->wantsToSubmit());
    }

    /**
     * The requests the enabled modules offer below the form (core#581).
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'moduleRequests' => ProposalRequestDefinitionResource::collection(
                app(EventProposalRequestServiceInterface::class)->definitions(),
            )->resolve(),
        ];
    }

    protected function type(): ProposalType
    {
        return ProposalType::Event;
    }

    protected function page(): string
    {
        return 'Proposals/EventForm';
    }

    protected function editRoute(): string
    {
        return 'proposals.events.edit';
    }
}
