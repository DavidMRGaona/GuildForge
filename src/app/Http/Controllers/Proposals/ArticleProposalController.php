<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proposals;

use App\Application\Proposals\DTOs\OwnProposalDTO;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\ProposalException;
use App\Http\Requests\Proposals\ArticleProposalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The web form that proposes an article: create it, edit the draft, save it or send it to review
 * (core#579). Who may propose is checked by the request and again by the service.
 */
final class ArticleProposalController extends ProposalFormController
{
    public function create(Request $request): Response
    {
        $this->assertMayPropose($request);

        return $this->formPage($request, null);
    }

    public function store(ArticleProposalRequest $request): RedirectResponse
    {
        $image = $this->storeImage($request->file('image'));

        try {
            $id = $this->savingWithImage($image, fn (): string => $this->proposals->createArticle(
                $this->userId($request),
                $request->toDto($image),
            ));
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

    public function update(ArticleProposalRequest $request, string $id): RedirectResponse
    {
        $current = $this->editable($request, $id);

        if (! $current instanceof OwnProposalDTO) {
            return $current;
        }

        $stored = $request->hasFile('image') ? $this->storeImage($request->file('image')) : null;
        $image = match (true) {
            $request->hasFile('image') => $stored,
            $request->removesImage() => null,
            default => $current->imagePublicId,
        };

        try {
            // Only the image this request stored is let go of: the current one stays with the draft
            $this->savingWithImage($stored, fn () => $this->proposals->updateArticle($this->userId($request), $id, $request->toDto($image)));
        } catch (ProposalException $exception) {
            return $this->refusal($exception);
        }

        return $this->afterSaving($request, $id, $request->wantsToSubmit());
    }

    protected function type(): ProposalType
    {
        return ProposalType::Article;
    }

    protected function page(): string
    {
        return 'Proposals/ArticleForm';
    }

    protected function editRoute(): string
    {
        return 'proposals.articles.edit';
    }
}
